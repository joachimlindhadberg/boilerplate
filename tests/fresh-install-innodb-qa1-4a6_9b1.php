<?php

declare(strict_types=1);
/**
 * 4A.6.9b1 – read-only fresh-install InnoDB schema validation.
 * Source-level only; deliberately does not connect to or mutate a database.
 */
$root = dirname(__DIR__);
$count = 0;
$check = static function (bool $ok, string $label) use (&$count): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    ++$count;
    echo "PASS: {$label}\n";
};

$schema = file_get_contents($root . '/database/install/empty-schema.sql');
$fixture = file_get_contents($root . '/tests/integration/fixtures/base-schema.sql');
$check(is_string($schema) && strlen($schema) > 0, 'Fresh-install baseline exists');
$check(is_string($fixture) && strlen($fixture) > 0, 'Isolated integration fixture exists');

// Same delimiter logic as FirstInstall::createSchema(); do not execute SQL here.
$withoutComments = preg_replace('/^--[^\r\n]*\r?\n/m', '', $schema);
$check(is_string($withoutComments), 'Installer-compatible SQL comment stripping succeeds');
$statements = array_values(array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $withoutComments) ?: [])));
$check(count($statements) >= 50, 'Installer statement parser retains complete SQL baseline');
$check(count($statements) === 81, 'Installer statement count remains unchanged');
$tables = [];
$alters = 0;
foreach ($statements as $statement) {
    if (preg_match('/^CREATE TABLE `([a-z_]+)`\s*\(/i', $statement, $match)) {
        $name = $match[1];
        $check(!isset($tables[$name]), "Table {$name} occurs once");
        $tables[$name] = $statement;
        $check((bool)preg_match('/\)\s+ENGINE=InnoDB\s+DEFAULT CHARSET=utf8mb4\s+COLLATE=utf8mb4_(?:unicode|danish)_ci\s*$/i', $statement), "Table {$name} uses explicit InnoDB + utf8mb4");
    } elseif (preg_match('/^ALTER TABLE `[a-z_]+`/i', $statement)) {
        ++$alters;
    } else {
        $check((bool)preg_match('/^SET NAMES utf8mb4$/i', $statement), 'Installer statement is explicitly whitelisted');
    }
}
$check(count($tables) === 25, 'Fresh baseline retains 25 tables');
$check($alters === 55, 'Fresh baseline retains 55 ALTER TABLE statements');
$check(isset($tables['page_revisions']), 'page_revisions is present');
$check(!str_contains($schema, 'DROP TABLE') && !str_contains($schema, 'DELETE FROM'), 'Fresh baseline contains no destructive statements');
foreach ([$schema, $fixture] as $i => $source) {
    $check((bool)preg_match('/CREATE TABLE `page_revisions` \(.*?\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;/s', $source), $i === 0 ? 'Production page_revisions has explicit InnoDB' : 'Test fixture page_revisions has explicit InnoDB');
}
$check(!str_contains($schema, 'INSERT INTO'), 'Fresh baseline contains no user data');

echo "4A.6.9b1 Fresh-Install InnoDB QA: {$count} PASS / 0 FAIL (read-only source checks)\n";
