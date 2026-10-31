<?php

declare(strict_types=1);

/** Read-only list for the minimalist update picker. Both sources must be checked. */
final class UpdateReleaseChoices
{
    private UpdateReleaseLookup $lookup;

    public function __construct(?UpdateReleaseLookup $lookup = null)
    {
        $this->lookup = $lookup ?? new UpdateReleaseLookup();
    }

    /** @return array{status:string,releases:array<int,array{kind:string,version:string,url:string}>} */
    public function find(string $installedVersion): array
    {
        $empty = ['status' => 'error', 'releases' => []];
        if (!UpdateReleaseVersion::valid($installedVersion)) return $empty;
        $stable = $this->lookup->check($installedVersion, 'stable');
        $preview = $this->lookup->checkPreview($installedVersion);
        $valid = ['available', 'current', 'no_release'];
        foreach ([$stable, $preview] as $response) {
            if (!in_array($response['status'] ?? null, $valid, true)) {
                $empty['status'] = (string)($response['status'] ?? 'error');
                return $empty; // Incomplete discovery must not be shown as complete.
            }
        }
        $releases = [];
        $stableVersion = (string)($stable['latest_version'] ?? '');
        $previewVersion = (string)($preview['latest_version'] ?? '');
        if ($stable['status'] === 'available') {
            $releases[] = ['kind' => 'stable', 'version' => $stableVersion, 'url' => (string)$stable['release_url']];
        }
        if ($preview['status'] === 'available' && ($stableVersion === ''
            || version_compare($previewVersion, $stableVersion, '>'))) {
            $releases[] = ['kind' => 'beta', 'version' => $previewVersion, 'url' => (string)$preview['release_url']];
        }
        return ['status' => $releases !== [] ? 'available'
            : (($stable['status'] === 'no_release' && $preview['status'] === 'no_release') ? 'no_release' : 'current'),
            'releases' => $releases];
    }
}
