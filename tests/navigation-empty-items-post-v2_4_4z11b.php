<?php

declare(strict_types=1);
/**
 * z11b: Saving an empty Navigation menu must not read an absent POST items key.
 * Isolated model regression: no app bootstrap, credentials, MySQL or CMS writes.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $description) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $description . "\n";
    $ok ? ++$pass : ++$fail;
};

$controller = (string)file_get_contents($root . '/public_html/admin/navigation.php');
$check(str_contains($controller, "\$submittedItems = \$_POST['items'] ?? [];"), 'Missing items[] key defaults to an empty array');
$check(str_contains($controller, 'if (!is_array($submittedItems)) throw new InvalidArgumentException'), 'Malformed submitted items are rejected');
$check(str_contains($controller, "(string)(\$_POST['placement'] ?? 'none'), \$submittedItems);"), 'Controller submits the validated normalized array');
$check(!str_contains($controller, ", \$_POST['items']);"), 'Controller does not access items key without a fallback');
$check(str_contains($controller, "\$mode === 'delete'") && str_contains($controller, 'admin_verify_csrf('), 'Existing modal delete endpoint and CSRF unchanged');

// This fake exercises the unmodified Navigation model without any database connection.
class Db
{
    public array $rows = [];
    public array $writes = [];
    public bool $started = false;
    public bool $committed = false;
    public static function transactionActive(): bool { return false; }
    public function fetchArray(string $sql, array $params = []): array
    {
        if (str_starts_with($sql, 'SELECT * FROM navigation_menus')) return [['id' => 17, 'name' => 'Empty', 'slug' => 'empty']];
        if (str_contains($sql, 'FROM navigation_items i LEFT JOIN pages')) return $this->rows;
        throw new RuntimeException('Unexpected SELECT: ' . $sql);
    }
    public function fetchValue(string $sql, array $params = []): mixed
    {
        if (str_contains($sql, 'SELECT setting_value FROM settings')) return 'other';
        throw new RuntimeException('Unexpected scalar SELECT: ' . $sql);
    }
    public function beginTransaction(): void { $this->started = true; }
    public function query(string $sql, array $params = []): void { $this->writes[] = [$sql, $params]; }
    public function commit(): void { $this->committed = true; }
    public function rollback(): void { $this->committed = false; }
}
class RequestCache
{
    public static function remember(string $namespace, string $key, callable $loader): mixed { return $loader(); }
}
require_once $root . '/core/classes/Navigation.php';

$db = new Db();
$navigation = new Navigation($db);
$navigation->saveEditor(17, 'Empty menu renamed', 'none', []);
$check($db->started && $db->committed, 'Empty menu can be saved successfully with an empty items array');
$check(count($db->writes) === 2, 'Empty menu updates only the menu and existing item detach set');
$check(str_starts_with($db->writes[0][0], 'UPDATE navigation_menus SET name=?'), 'Existing menu name save is preserved');
$check(!array_filter($db->writes, static fn(array $write): bool => str_starts_with($write[0], 'DELETE')), 'Empty menu save performs no deletions');

$dbWithItems = new Db();
$dbWithItems->rows = [['id' => 54, 'menu_id' => 17, 'parent_id' => null, 'label' => 'Keep me']];
$navigationWithItems = new Navigation($dbWithItems);
$rejected = false;
try {
    $navigationWithItems->saveEditor(17, 'Incomplete post', 'none', []);
} catch (InvalidArgumentException $e) {
    $rejected = str_contains($e->getMessage(), 'Genindlæs');
}
$check($rejected, 'Missing items in a populated menu remains an explicit validation error');
$check($dbWithItems->writes === [] && !$dbWithItems->started, 'Incomplete menu POST cannot mutate any stored data');

$version = (string)file_get_contents($root . '/public_html/incl/init.php');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'/", $version, $v) === 1 && version_compare($v[1], '2.4.4.66', '>='), 'Internal version 2.4.4.66');
$check(file_get_contents($root . '/public_html/admin/assets/scss/admin.scss') === file_get_contents($root . '/public_html/admin/assets/css/admin.css'), 'Core SCSS and CSS remain synchronized');
echo "Navigation empty items POST V2.4.4z11b: {$pass} PASS / {$fail} FAIL\n";
exit($fail > 0 ? 1 : 0);
