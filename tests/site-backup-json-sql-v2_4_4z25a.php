<?php
/**
 * z25a: isolated SQL literal serializer regression tests.
 * These tests never bootstrap CMS, connect to MySQL, import SQL or write files.
 * Uses a stub mysqli escape method so this runs even without ext-mysqli.
 */
declare(strict_types=1);

if (!class_exists('mysqli')) {
    class mysqli {
        public function real_escape_string(string $value): string { return addslashes($value); }
    }
}
final class BackupEscapeDbStub extends mysqli {
    public function __construct() {}
    public function real_escape_string(string $value): string {
        return strtr($value, ["\\" => "\\\\", "'" => "\\'", '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\x00" => '\\0', "\x1a" => '\\Z']);
    }
}
require_once dirname(__DIR__) . '/core/classes/SiteBackup.php';
$method = new ReflectionMethod(SiteBackup::class, 'sqlLiteral');
$db = new BackupEscapeDbStub();
$passed = $failed = 0;
$check = static function (bool $success, string $name) use (&$passed, &$failed): void {
    $success ? $passed++ : $failed++;
    echo ($success ? 'PASS: ' : 'FAIL: ') . $name . "\n";
};
$field = static fn(int $type, int $flags, int $charset): object => (object)[
    'type' => $type, 'flags' => $flags, 'charsetnr' => $charset,
];
$json = '{"title":"Lær os at kende","active":true,"rows":[1,2],"quote":"O\'Brien"}';
$jsonSql = $method->invoke(null, $db, $json, $field(245, 128, 63));
$check(str_starts_with($jsonSql, "'") && str_ends_with($jsonSql, "'"), 'MySQL JSON type 245 is quoted text, not a binary literal');
$check($jsonSql === "'" . $db->real_escape_string($json) . "'", 'MySQL JSON preserves UTF-8 characters and escaping');
$check(!str_contains($jsonSql, '0x7b'), 'JSON SQL never inserts 0x binary data');
$check($method->invoke(null, $db, '{}', $field(245, 0, 45)) === "'{}'", 'JSON without BINARY flag also uses text');
$check($method->invoke(null, $db, '[]', $field(245, 128, 63)) === "'[]'", 'JSON arrays serialize as text');
$check($method->invoke(null, $db, 'null', $field(245, 128, 63)) === "'null'", 'JSON null scalar serialized distinctly from SQL NULL');
$blob = "\x00\xff\x27\x5c";
$check($method->invoke(null, $db, $blob, $field(252, 128, 63)) === '0x00ff275c', 'BLOB fields retain hex encoding');
$check($method->invoke(null, $db, "\x00\xff", $field(253, 128, 63)) === '0x00ff', 'VARBINARY retains hex encoding');
$check($method->invoke(null, $db, 'Hej', $field(253, 0, 45)) === "'Hej'", 'Normal UTF-8 VARCHAR remains quoted text');
$check($method->invoke(null, $db, '24', $field(3, 0, 63)) === "'24'", 'Non-JSON numeric string retains previous behavior');
$source = (string)file_get_contents(dirname(__DIR__) . '/core/classes/SiteBackup.php');
$check(strpos($source, 'if ((int) $field->type === $jsonType)') < strpos($source, 'if (($field->flags & $binaryFlag)'), 'JSON branch is evaluated before the binary flags');
$check(str_contains($source, '$jsonType = defined(\'MYSQLI_TYPE_JSON\') ? MYSQLI_TYPE_JSON : 245;'), 'Fallback supports mysqli implementations without JSON constant');
$check(!str_contains($source, 'CAST(0x'), 'JSON restore does not require implicit binary casts');
$check(str_contains($source, 'START TRANSACTION WITH CONSISTENT SNAPSHOT') && str_contains($source, 'SET FOREIGN_KEY_CHECKS = 1;'), 'Existing backup snapshot and restore boundary preserved');
$version = (string)file_get_contents(dirname(__DIR__) . '/public_html/incl/init.php');
$check(preg_match("~BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'~", $version, $v) === 1 && version_compare($v[1], '2.4.4.113', '>='), 'Hotfix version 2.4.4.113');
$check(is_file(dirname(__DIR__) . '/docs/releases/BACKUP-JSON-RESTORE-HOTFIX-V2.4.4z25a.md'), 'Release notes shipped');
printf("SiteBackup JSON SQL z25a: %d PASS / %d FAIL (no DB / HTTP / writes)\n", $passed, $failed);
exit($failed ? 1 : 0);
