<?php

declare(strict_types=1);

/**
 * S5.2: The verified release's read-only Core/module migration inventory.
 *
 * This is an integration seam, NOT a database executor or recovery approval.
 * It must NEVER modify the installed database, the release ZIP, or CMS files.
 * A review-only result NEVER authorizes UpdateOneClickInstaller::install() to
 * proceed with a migration-bearing ZIP.
 */
final class UpdateOneClickMigrationReview
{
    private string $root;

    public function __construct(string $root)
    {
        $canonical = realpath($root);
        if (!is_string($canonical) || !is_dir($canonical) || is_link($root)) {
            throw new RuntimeException('Unsafe One-Click migration review root.');
        }
        $this->root = rtrim($canonical, DIRECTORY_SEPARATOR);
    }

    /**
     * @param list<array{id:string,state:string}> $states READ-ONLY MigrationRunner::status().
     * @return array{status:string,install_ready:false,migrations:list<string>}
     */
    public function inspect(string $archive, string $expectedSha, array $states, int $announcedNew): array
    {
        $blocked = ['status' => 'blocked', 'install_ready' => false, 'migrations' => []];
        if ($announcedNew < 1 || $announcedNew > 64
            || preg_match('/\A[a-f0-9]{64}\z/D', $expectedSha) !== 1) {
            return $blocked;
        }

        // Reuse the real F4a source-history preflight. It does not execute PHP,
        // run SQL, or authorize the release; it pins the exact ZIP digest.
        try {
            $report = (new UpdateDatabaseReleasePreflight($this->root))
                ->inspect($archive, $expectedSha, $states);
        } catch (Throwable $e) {
            return $blocked;
        }
        if (($report['status'] ?? null) !== 'review'
            || ($report['install_ready'] ?? null) !== false
            || !is_array($report['new_migrations'] ?? null)
            || count($report['new_migrations']) !== $announcedNew) {
            return $blocked;
        }

        $ids = $report['new_migrations'];
        foreach ($ids as $id) {
            if (!is_string($id) || preg_match(
                '/\A(?:core|module:[a-z0-9][a-z0-9_-]*):[0-9]{8}_[a-z0-9_-]+\z/D',
                $id
            ) !== 1) {
                return $blocked;
            }
        }
        if (count(array_unique($ids)) !== count($ids)) return $blocked;
        sort($ids, SORT_STRING);
        return ['status' => 'review_only', 'install_ready' => false, 'migrations' => $ids];
    }
}
