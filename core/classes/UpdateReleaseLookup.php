<?php

declare(strict_types=1);

/**
 * Read-only stable/beta release lookup. Trust is limited to GitHub's fixed API host
 * and a publisher-controlled repository identifier; no supplied URLs, uploads,
 * tokens, redirects, files, sessions or database writes are involved.
 *
 * The official public Boilerplate CMS repository is pinned by the publisher.
 * Customers do not need to configure a repository, token or API key.
 * Explicit repository injection is retained only for isolated unit testing.
 */
final class UpdateReleaseLookup
{
    private const MAX_RESPONSE_BYTES = 262144;
    private const OFFICIAL_REPOSITORY = 'joachimlindhadberg/boilerplate';
    private string $repository;
    /** @var null|Closure(string):array{status:int,body:string} */
    private ?Closure $transport;

    public function __construct(?string $repository = null, ?callable $transport = null)
    {
        $this->repository = trim($repository ?? self::OFFICIAL_REPOSITORY);
        $this->transport = $transport === null ? null : Closure::fromCallable($transport);
    }

    public static function validRepository(string $repository): bool
    {
        return strlen($repository) <= 140
            && (bool) preg_match('~^[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?/[A-Za-z0-9][A-Za-z0-9._-]{0,99}$~D', $repository);
    }

    /** @return array{status:string,latest_version:string,release_url:string} */
    public function check(string $installedVersion, string $channel = 'stable'): array
    {
        $result = ['status' => 'error', 'latest_version' => '', 'release_url' => ''];
        if (!UpdateReleaseVersion::valid($installedVersion)) {
            return $result;
        }
        if ($this->repository === '') {
            $result['status'] = 'not_configured';
            return $result;
        }
        if (!self::validRepository($this->repository)) {
            return $result; // Never issue a request for malformed repository names.
        }

        [$owner, $repo] = explode('/', $this->repository, 2);
        $channel = UpdateReleaseVersion::channel($channel);
        $apiUrl = UpdateReleaseVersion::api(rawurlencode($owner) . '/' . rawurlencode($repo), $channel);
        try {
            $response = $this->transport !== null
                ? ($this->transport)($apiUrl)
                : self::fetchGithub($apiUrl);
        } catch (Throwable $e) {
            $result['status'] = 'connection_error';
            return $result; // Keep private transport errors out of admin output.
        }
        if (!is_array($response) || !isset($response['status'], $response['body'])
            || !is_int($response['status']) || !is_string($response['body'])
            || strlen($response['body']) > self::MAX_RESPONSE_BYTES) {
            $result['status'] = 'invalid_response';
            return $result;
        }
        if ($response['status'] === 404) {
            $result['status'] = 'no_release';
            return $result;
        }
        if ($response['status'] === 403 || $response['status'] === 401) {
            $result['status'] = 'github_rejected';
            return $result; // Could be API permission or rate limiting; do not guess.
        }
        if ($response['status'] === 429) {
            $result['status'] = 'rate_limited';
            return $result;
        }
        if ($response['status'] >= 500 && $response['status'] <= 599) {
            $result['status'] = 'service_unavailable';
            return $result;
        }
        if ($response['status'] !== 200) {
            $result['status'] = 'invalid_response'; // Redirects never followed.
            return $result;
        }
        try {
            $release = json_decode($response['body'], true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $result['status'] = 'invalid_response';
            return $result;
        }
        if (!is_array($release) || ($channel === 'beta' && !array_is_list($release))) {
            $result['status'] = 'invalid_response';
            return $result;
        }
        $selected = UpdateReleaseVersion::select($release, $channel);
        if ($selected === null) {
            if ($channel === 'stable' && ($release['draft'] ?? null) === false
                && ($release['prerelease'] ?? null) === false
                && is_string($release['tag_name'] ?? null)) {
                $result['status'] = 'invalid_tag';
            } else {
                $result['status'] = $channel === 'beta' ? 'no_release' : 'invalid_response';
            }
            return $result;
        }
        $tag = $selected['tag_name'];
        $latest = $selected['_bp_version'];
        $result['latest_version'] = $latest;
        $result['status'] = version_compare($latest, $installedVersion, '>') ? 'available' : 'current';
        if ($result['status'] === 'available') {
            // Construct a trusted, HTTPS release link instead of using untrusted
            // html_url from third-party JSON (which could be a phishing URL).
            $result['release_url'] = 'https://github.com/' . rawurlencode($owner) . '/'
                . rawurlencode($repo) . '/releases/tag/' . rawurlencode($tag);
        }
        return $result;
    }

    /** @return array{status:int,body:string} */
    private static function fetchGithub(string $url): array
    {
        $headers = ['Accept: application/vnd.github+json', 'User-Agent: Boilerplate-CMS-Update-Checker'];
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) throw new RuntimeException('Unable to open HTTP request.');
            $body = '';
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > self::MAX_RESPONSE_BYTES) return 0;
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            try {
                if (curl_exec($ch) !== true) throw new RuntimeException('Update network request failed.');
                return ['status' => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE), 'body' => $body];
            } finally {
                // Since PHP 8.0 cURL uses objects; curl_close() is deprecated in 8.5.
                unset($ch);
            }
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers) . "\r\n",
                'timeout' => 8,
                'follow_location' => 0,
                'max_redirects' => 0,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) throw new RuntimeException('Update network request failed.');
        try {
            $body = stream_get_contents($stream, self::MAX_RESPONSE_BYTES + 1);
            $meta = stream_get_meta_data($stream);
            $status = 0;
            foreach ($meta['wrapper_data'] ?? [] as $header) {
                if (preg_match('~^HTTP/\d(?:\.\d)?\s+(\d{3})~i', (string)$header, $m)) {
                    $status = (int)$m[1];
                    break;
                }
            }
        } finally {
            fclose($stream);
        }
        if (!is_string($body) || strlen($body) > self::MAX_RESPONSE_BYTES) {
            throw new RuntimeException('Update response too large.');
        }
        return ['status' => $status, 'body' => $body];
    }
}
