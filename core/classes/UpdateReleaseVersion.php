<?php

declare(strict_types=1);

/** The small, publisher-controlled version/channel contract shared by lookup, ZIP and installer. */
final class UpdateReleaseVersion
{
    public const PATTERN = '~\A\d+\.\d+\.\d+(?:\.\d+)?(?:-(?:beta|rc)\.[1-9]\d*)?\z~D';
    public const SOURCE_PATTERN = "~define\\('BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3}(?:-(?:beta|rc)\\.[1-9][0-9]*)?)'\\)~";

    public static function valid(string $version): bool
    {
        return (bool)preg_match(self::PATTERN, $version);
    }

    public static function channel(string $channel): string
    {
        return $channel === 'beta' ? 'beta' : 'stable';
    }

    public static function isPrerelease(string $version): bool
    {
        return str_contains($version, '-');
    }

    /** Return a release only when its tag and GitHub state describe the same kind of version. */
    public static function normalizeRelease(mixed $release, string $channel): ?array
    {
        if (!is_array($release) || ($release['draft'] ?? null) !== false
            || !is_bool($release['prerelease'] ?? null)
            || !is_string($release['tag_name'] ?? null)) return null;
        $tag = $release['tag_name'];
        $version = str_starts_with($tag, 'v') ? substr($tag, 1) : $tag;
        if (!self::valid($version)
            || self::isPrerelease($version) !== $release['prerelease']
            || ($channel === 'stable' && $release['prerelease'])) return null;
        $release['_bp_version'] = $version;
        return $release;
    }

    /** Stable uses GitHub /latest; beta compares recent official releases, including stable ones. */
    public static function select(mixed $json, string $channel): ?array
    {
        $channel = self::channel($channel);
        if ($channel === 'stable') return self::normalizeRelease($json, 'stable');
        if (!is_array($json) || !array_is_list($json)) return null;
        $best = null;
        foreach ($json as $item) {
            $candidate = self::normalizeRelease($item, 'beta');
            if ($candidate !== null && ($best === null
                || version_compare($candidate['_bp_version'], $best['_bp_version'], '>'))) {
                $best = $candidate;
            }
        }
        return $best;
    }

    public static function api(string $repository, string $channel): string
    {
        $endpoint = self::channel($channel) === 'beta' ? '/releases?per_page=10' : '/releases/latest';
        return 'https://api.github.com/repos/' . $repository . $endpoint;
    }
}
