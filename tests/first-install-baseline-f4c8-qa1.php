<?php

declare(strict_types=1);

/**
 * F4c.8 QA1 — read-only inventory/equivalence check against genuine CMS Core.
 * Does NOT connect to MySQL, perform installation, or modify any CMS file.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';

$passed = 0;
$check = static function (bool $ok, string $name) use (&$passed): void {
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . "\n";
    ++$passed;
};

$installPath = $root . '/core/classes/FirstInstall.php';
$schemaPath = $root . '/database/install/empty-schema.sql';
$migrationPath = $root . '/database/updates/20261105_000_page_access.php';
$check(is_file($installPath) && is_file($schemaPath) && is_file($migrationPath), 'Real installer, fresh schema and page_access migration available');
$check(class_exists(FirstInstall::class), 'Genuine CMS FirstInstall class available');
$check(class_exists(MigrationRunner::class), 'Genuine CMS MigrationRunner class available');

$source = file_get_contents($installPath);
$schema = file_get_contents($schemaPath);
$check(is_string($source) && is_string($schema), 'Installer and schema readable without database');

$expected = [
    'core:20261013_000_core_schema',
    'core:20261013_010_media_reference_index',
    'core:20261016_000_pages_author_audit',
    'core:20261019_200_dashboard_cards',
    'core:20261105_000_page_access',
    'module:forms:20261013_100_forms_schema',
    'module:gallery:20261013_100_gallery_schema',
    'module:gallery:20261019_100_gallery_lightbox',
];
sort($expected);
$check(count($expected) === 8, 'Exactly eight reviewed migration identifiers');

$inventoryStart = strpos($source, '        $expected = [');
$inventoryEnd = $inventoryStart === false ? false : strpos($source, '        ];', $inventoryStart);
$check($inventoryStart !== false && $inventoryEnd !== false, 'FirstInstall strict migration allowlist located');
$inventory = substr($source, $inventoryStart, $inventoryEnd - $inventoryStart);
preg_match_all("/'((?:core|module:[a-z0-9_-]+):[0-9]{8}_[a-z0-9_-]+)'/", $inventory, $matches);
$declared = $matches[1];
sort($declared);
$check($declared === $expected, 'FirstInstall stamps exactly the eight reviewed migration identifiers');
$check(str_contains($source, 'if ($actual !== $expected) throw new RuntimeException('), 'Unknown migration files still fail closed');
$check(str_contains($source, "hash_file('sha256', \$file)"), 'Per-migration SHA-256 stamping remains intact');

$dummy = new class extends Db {
    public function __construct() {}
};
$discovered = (new MigrationRunner($dummy, $root))->discover();
$ids = array_keys($discovered);
sort($ids);
$check($ids === $expected, 'Real MigrationRunner discovers exactly the reviewed migrations');
foreach ($discovered as $id => $entry) {
    $check(is_file($entry['path']) && !is_link($entry['path'])
        && hash_file('sha256', $entry['path']) === $entry['sha256'],
        'Discovered source checksum valid: ' . $id);
}

$needle = 'CREATE TABLE `page_access` (';
$check(substr_count($schema, $needle) === 1, 'Fresh baseline defines page_access exactly once');
$pagesPrimary = strpos($schema, "ALTER TABLE `pages`\n  ADD PRIMARY KEY");
$pageTable = strpos($schema, $needle);
$authTable = strpos($schema, 'CREATE TABLE `auth_sessions` (');
$check($pagesPrimary !== false && $pageTable !== false && $authTable !== false
    && $pagesPrimary < $pageTable && $pageTable < $authTable,
    'page_access created after pages primary key and before auth_sessions');

$match = [];
$check(preg_match('/CREATE TABLE `page_access` \(.*?\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;/s', $schema, $match) === 1,
    'Baseline includes complete InnoDB page_access DDL');

$recorder = new class extends Db {
    /** @var list<string> */
    public array $statements = [];
    public function __construct() {}
    public function query(string $sql, array $params = []): void {
        if ($params !== []) throw new RuntimeException('Migration unexpectedly accepts SQL parameters');
        $this->statements[] = $sql;
    }
};
$migration = require $migrationPath;
$check(is_callable($migration), 'Original page_access migration remains callable');
$migration($recorder);
$check(count($recorder->statements) === 1, 'Original page_access migration emits one fixed DDL statement');

$normalize = static function (string $sql): string {
    $plain = strtolower(str_replace('`', '', trim($sql)));
    return (string)preg_replace('/\s+/', '', $plain);
};
$expectedDdl = str_replace('createtableifnotexists', 'createtable', $normalize($recorder->statements[0]));
$actualDdl = rtrim($normalize($match[0]), ';');
$check($expectedDdl === $actualDdl,
    'Fresh page_access DDL is structurally equivalent to the original migration SQL');
$check(str_contains($actualDdl, 'foreignkey(page_id)referencespages(id)ondeletecascade'),
    'Page access foreign key retains ON DELETE CASCADE');
$check(str_contains($actualDdl, 'password_hashvarchar(255)notnull'),
    'Page access retains non-null hashed-password column');
$check(str_contains($actualDdl, 'page_idintnotnullprimarykey'),
    'Page access retains single-page primary key');

$check(str_contains($source, "if (self::configured(\$root)) throw new DomainException('The installation is already configured.');"),
    'Existing installation remains protected from fresh installer');
$check(str_contains($source, 'self::assertEmpty($handle);'),
    'Existing nonempty database remains protected');
$check(str_contains($source, 'self::createSchema($handle);')
    && str_contains($source, 'self::stampMigrations($handle, $root);'),
    'Original fresh-install and migration-stamping flow retained');

echo "F4c.8 FirstInstall baseline contracts: {$passed} PASS / 0 FAIL (read-only; no MySQL)\n";
