<?php

declare(strict_types=1);
/**
 * 4A.6.9b2 – Regression for failure after 25 CREATE TABLEs and before triggers.
 * The fresh-install parser executes DDL one statement at a time. A parent column
 * must be fully finalized before a child foreign-key references it (MySQL 1833).
 * Source only: no DB connections, destructive queries, or runtime mutations.
 */
$root = getenv('CMS_QA_ROOT') ?: dirname(__DIR__);
$n = 0;
$check = static function (bool $ok, string $label) use (&$n): void {
    if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    $n++;
    echo "PASS: $label\n";
};
$schema = file_get_contents($root . '/database/install/empty-schema.sql');
$fixture = file_get_contents($root . '/tests/integration/fixtures/base-schema.sql');
$installer = file_get_contents($root . '/core/classes/FirstInstall.php');
$check(is_string($schema) && $schema !== '', 'Production fresh-install schema readable');
$check(is_string($fixture) && $fixture !== '', 'Integration fixture readable');
$check(is_string($installer) && $installer !== '', 'Installer available');
$schemaStatements = static function (string $sql): array {
    $withoutComments = preg_replace('/^--[^\r\n]*\r?\n/m', '', $sql);
    if (!is_string($withoutComments)) return [];
    return array_values(array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $withoutComments) ?: [])));
};
$statements = $schemaStatements($schema);
$check(count($statements) === 81, 'Installer SQL splitter retains 81 statements');
$createCount = 0;
$alterCount = 0;
$usersFinalizedAt = null;
$authCreatedAt = null;
foreach ($statements as $i => $stmt) {
    $check((bool)preg_match('/^(?:SET NAMES utf8mb4|CREATE TABLE `[a-z_]+`|ALTER TABLE `[a-z_]+`)/i', $stmt), "Installer allows statement #" . ($i + 1));
    if (str_starts_with($stmt, 'CREATE TABLE ')) {
        $createCount++;
        $check((bool)preg_match('/\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_(?:unicode|danish)_ci$/', $stmt), 'Table uses explicit InnoDB: ' . ($i + 1));
        if (str_starts_with($stmt, 'CREATE TABLE `auth_sessions` ')) {
            $authCreatedAt = $i;
            $check(str_contains($stmt, 'CONSTRAINT `auth_sessions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE'), 'auth_sessions foreign-key preserved');
        }
    }
    if (str_starts_with($stmt, 'ALTER TABLE ')) $alterCount++;
    if (str_starts_with($stmt, "ALTER TABLE `users`\n  MODIFY `id` int NOT NULL AUTO_INCREMENT")) $usersFinalizedAt = $i;
}
$check($createCount === 25, 'All 25 tables retained');
$check($alterCount === 55, 'All 55 historical ALTER TABLE operations retained');
$check($usersFinalizedAt !== null, 'Parent users.id AUTO_INCREMENT DDL present');
$check($authCreatedAt !== null, 'Child auth_sessions table present');
$check($usersFinalizedAt < $authCreatedAt, 'Parent users.id finalized BEFORE child foreign key (MySQL 1833 regression)');
$check($statements[count($statements) - 1] === $statements[$authCreatedAt], 'Child foreign key created after all historical ALTERs');
$fixtureStatements = $schemaStatements($fixture);
$fixtureUserFinalizedAt = null;
$fixtureAuthCreatedAt = null;
foreach ($fixtureStatements as $i => $stmt) {
    if (str_starts_with($stmt, 'CREATE TABLE `auth_sessions` ')) $fixtureAuthCreatedAt = $i;
    if (str_starts_with($stmt, "ALTER TABLE `users`\n  MODIFY `id` int NOT NULL AUTO_INCREMENT")) $fixtureUserFinalizedAt = $i;
}
$check($fixtureUserFinalizedAt !== null && $fixtureAuthCreatedAt !== null && $fixtureUserFinalizedAt < $fixtureAuthCreatedAt, 'Integration fixture also creates auth_sessions after parent user AUTO_INCREMENT');
$check(
    $fixtureAuthCreatedAt !== null && $authCreatedAt !== null
    && $fixtureStatements[$fixtureAuthCreatedAt] === $statements[$authCreatedAt],
    'Integration fixture auth_sessions matches production definition'
);
$check(str_contains($installer, 'self::createSchema($handle);') && str_contains($installer, 'self::createTriggers($handle);'), 'Installer still executes schema before triggers');
$check(!str_contains($schema, 'DROP TABLE') && !str_contains($schema, 'INSERT INTO'), 'Fresh-install baseline does not delete or seed site data');
echo "4A.6.9b2 Fresh-Install FK Order: {$n} PASS / 0 FAIL (source-only)\n";
