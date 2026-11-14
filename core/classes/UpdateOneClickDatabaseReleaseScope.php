<?php

declare(strict_types=1);

/**
 * S5.2: release-declared recovery scope, pinned by the OFFICIAL release ZIP.
 * This is NOT executable PHP, an SQL statement, or permission to DROP objects.
 * Every new Core/module migration must explicitly name new tables and triggers.
 */
final class UpdateOneClickDatabaseReleaseScope
{
    public const MEMBER = 'boilerplate/database/update-recovery-objects.json';
    private const MIGRATION = '/\A(?:core|module:[a-z0-9][a-z0-9_-]*):[0-9]{8}_[a-z0-9_-]+\z/D';
    private const OBJECT = '/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/D';

    /** @param list<string> $ids @return array{tables:list<string>,triggers:list<string>} */
    public static function fromZip(ZipArchive $zip, array $ids): array
    {
        $index = $zip->locateName(self::MEMBER, ZipArchive::FL_NOCASE);
        if (!is_int($index) || $zip->getNameIndex($index) !== self::MEMBER) {
            throw new RuntimeException('Migration-bearing release requires an exact recovery scope manifest.');
        }
        $stat = $zip->statIndex($index);
        if (!is_array($stat) || ($stat['size'] ?? 0) < 2 || $stat['size'] > 65536) {
            throw new RuntimeException('Invalid recovery scope size.');
        }
        $source = $zip->getFromIndex($index);
        if (!is_string($source) || strlen($source) !== $stat['size']) {
            throw new RuntimeException('Cannot read verified recovery scope.');
        }
        return self::parse($source, $ids);
    }

    /** @param list<string> $ids @return array{tables:list<string>,triggers:list<string>} */
    public static function parse(string $json, array $ids): array
    {
        if (strlen($json) > 65536 || count($ids) < 1 || count($ids) > 64
            || count($ids) !== count(array_unique($ids))) {
            throw new RuntimeException('Invalid recovery migration set.');
        }
        foreach ($ids as $id) {
            if (!is_string($id) || preg_match(self::MIGRATION, $id) !== 1) {
                throw new RuntimeException('Untrusted migration in recovery scope.');
            }
        }
        $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($data) || array_keys($data) !== ['format','version','migrations']
            || $data['format'] !== 'boilerplate-update-recovery-objects'
            || $data['version'] !== 1 || !is_array($data['migrations'])) {
            throw new RuntimeException('Unsupported recovery manifest.');
        }
        $given = array_keys($data['migrations']);
        sort($given, SORT_STRING); sort($ids, SORT_STRING);
        if ($given !== $ids) throw new RuntimeException('Every new migration needs exactly one declaration.');
        $tables=[]; $triggers=[];
        foreach ($ids as $id) {
            $entry = $data['migrations'][$id];
            if (!is_array($entry) || array_keys($entry) !== ['tables','triggers']
                || !array_is_list($entry['tables']) || !array_is_list($entry['triggers'])) {
                throw new RuntimeException('Invalid recovery object declaration.');
            }
            foreach (['tables','triggers'] as $key) {
                if (count($entry[$key]) > 128) throw new RuntimeException('Excessive declared objects.');
                foreach ($entry[$key] as $name) {
                    if (!is_string($name) || preg_match(self::OBJECT, $name) !== 1) {
                        throw new RuntimeException('Unsafe declared database object.');
                    }
                    if ($key === 'tables') $tables[] = $name;
                    else $triggers[] = $name;
                }
            }
        }
        if (count($tables) !== count(array_unique($tables))
            || count($triggers) !== count(array_unique($triggers))) {
            throw new RuntimeException('Duplicate declared database object.');
        }
        sort($tables, SORT_STRING); sort($triggers, SORT_STRING);
        return ['tables'=>$tables,'triggers'=>$triggers];
    }
}
