<?php

declare(strict_types=1);
/** z11a: Navigation list and editor use consistent modal-based delete UX. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    $ok ? ++$pass : ++$fail;
};
require_once $root . '/core/classes/AdminEscaper.php';
require_once $root . '/core/classes/AdminIcons.php';
require_once $root . '/core/classes/AdminListUI.php';
require_once $root . '/core/classes/AdminDeleteDialog.php';
$dictionary = json_decode((string)file_get_contents($root . '/core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
function t(string $key, array $params = []): string {
    global $dictionary;
    $translation = (string)($dictionary[$key]['da'] ?? $key);
    foreach ($params as $placeholder => $value) $translation = str_replace('{' . $placeholder . '}', (string)$value, $translation);
    return $translation;
}
function admin_e(mixed $value): string { return AdminEscaper::text($value); }
function admin_icon(string $name, string $class = ''): string { return AdminIcons::render($name, $class); }
function admin_icon_action_button(string $name, string $label, array $attributes = [], string $tone = 'neutral'): string { return AdminIcons::actionButton($name, $label, $attributes, $tone); }
function admin_csrf_token(): string { return 'modal-csrf-token'; }
$_SERVER['REQUEST_METHOD'] = 'GET';
$parts = $root . '/public_html/admin/views/navigation/partials/';
$render = static function (string $name, array $menuRows, ?array $selected = null, string $placement = 'none') use ($root, $parts): string {
    $source = (string)file_get_contents($root . '/public_html/admin/views/navigation/' . $name . '.php');
    $source = str_replace("require __DIR__ . '/partials/context.php';", 'require ' . var_export($parts . 'context.php', true) . ';', $source);
    $source = str_replace("require __DIR__ . '/partials/header.php';", 'require ' . var_export($parts . 'header.php', true) . ';', $source);
    $source = str_replace("require __DIR__ . '/../partials/layout.php';", 'echo $bodyHtml;', $source);
    $temporary = tempnam(sys_get_temp_dir(), 'nav-modal-z11a-');
    file_put_contents($temporary, $source);
    try {
        $menus = $menuRows; $selectedMenu = $selected; $mode = $name === 'edit' ? 'edit' : 'list';
        $items = []; $pages = []; $headerSlug = 'primary'; $footerSlug = 'footer'; $errors = []; $notice = null;
        ob_start(); include $temporary;
        return (string)ob_get_clean();
    } finally { @unlink($temporary); }
};
$sample = [
    ['id'=>1, 'name'=>'Header', 'slug'=>'primary', 'item_count'=>2],
    ['id'=>2, 'name'=>'Footer', 'slug'=>'footer', 'item_count'=>1],
    ['id'=>7, 'name'=>'Menu <img src=x onerror=alert(1)>', 'slug'=>'test', 'item_count'=>0],
];
$html = $render('index', $sample);
$check(str_contains($html, 'Admin') === false || str_contains($html, 'admin-'), 'Navigation list renders normally');
$check(substr_count($html, 'id="nav-list-delete-dialog"') === 1, 'Exactly one shared delete modal in list');
$check(substr_count($html, 'data-admin-open-delete="nav-list-delete-dialog"') === 1, 'Only unassigned menu can open modal');
$check(str_contains($html, 'data-nav-delete-id="7"'), 'Unassigned delete trigger carries ID');
$check(!str_contains($html, 'data-nav-delete-id="1"') && !str_contains($html, 'data-nav-delete-id="2"'), 'Assigned menus cannot open delete modal');
$check(substr_count($html, ' disabled') === 2, 'Header/Footer deletes disabled');
$check(!str_contains($html, 'href="/admin/navigation?mode=delete'), 'Delete actions never navigate to confirmation page');
$check(str_contains($html, 'action="/admin/navigation?mode=delete&amp;id=0"'), 'Modal starts with inert delete target');
$check(str_contains($html, 'method="post"') && str_contains($html, 'name="csrf_token" value="modal-csrf-token"'), 'Modal submits by POST with CSRF');
$check(str_contains($html, 'name="action" value="delete_menu"') && str_contains($html, 'name="confirmed" value="1"'), 'Modal submits original explicit confirmation fields');
$check(str_contains($html, 'data-nav-list-delete-name'), 'Modal has dedicated safe text target');
$check(!str_contains($html, '<img src=x onerror=alert(1)>'), 'Malicious menu name cannot inject HTML');
$check(str_contains($html, 'Menu &lt;img src=x onerror=alert(1)&gt;'), 'Menu name is escaped');
$empty = $render('index', []);
$check(substr_count($empty, 'id="nav-list-delete-dialog"')===1 && !str_contains($empty, 'data-nav-delete-id'), 'Empty list has safe dialog but no fake actions');
$assignedEdit = $render('edit', $sample, $sample[0], 'header');
$check(str_contains($assignedEdit, 'data-admin-open-delete="nav-delete-dialog" disabled'), 'Editor disables delete for assigned menu');
$check(str_contains($assignedEdit, t('nav.cannot_delete_assigned')), 'Editor provides reason for blocked delete');
$check(!str_contains($assignedEdit, 'id="nav-delete-dialog"'), 'Assigned editor does not expose a deletion modal');
$freeEdit = $render('edit', $sample, $sample[2], 'none');
$check(str_contains($freeEdit, 'data-admin-open-delete="nav-delete-dialog" ') && !str_contains($freeEdit, 'data-admin-open-delete="nav-delete-dialog" disabled'), 'Unassigned editor retains enabled modal trigger');
$check(substr_count($freeEdit, 'id="nav-delete-dialog"')===1, 'Unassigned editor retains its Core-styled modal');
$check(str_contains($freeEdit, 'name="csrf_token"') && str_contains($freeEdit, 'name="confirmed"'), 'Editor existing CSRF + confirmation still present');
$js = (string)file_get_contents($root . '/public_html/admin/assets/js/admin.js');
$check(str_contains($js, "dialog.id === 'nav-list-delete-dialog'"), 'Core delegated modal handler supports Navigation');
$check(str_contains($js, 'button.dataset.navDeleteId') && str_contains($js, "!/^[1-9][0-9]*$/.test(id)"), 'Core checks positive numeric menu ID');
$check(str_contains($js, "form.action = '/admin/navigation?mode=delete&id=' + id;"), 'Core sets existing server endpoint only after validated ID');
$check(str_contains($js, "name.textContent = button.dataset.navDeleteName || '';"), 'Core assigns menu name through textContent');
$controller = (string)file_get_contents($root . '/public_html/admin/navigation.php');
$model = (string)file_get_contents($root . '/core/classes/Navigation.php');
$check(str_contains($controller, 'admin_verify_csrf(') && str_contains($controller, "empty(\$_POST['confirmed'])"), 'Server continues to require CSRF and confirmation');
$check(str_contains($model, 'if ($assigned > 0)') && str_contains($model, 'DELETE FROM navigation_menus'), 'Server independently blocks deletion of assigned menu');
$check(file_get_contents($root.'/public_html/admin/assets/scss/admin.scss') === file_get_contents($root.'/public_html/admin/assets/css/admin.css'), 'Core SCSS/CSS remain synchronized');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'/", (string)file_get_contents($root.'/public_html/incl/init.php'), $version) === 1 && version_compare($version[1], '2.4.4.65', '>='), 'Release retains z11a compatibility');
echo "Navigation delete modal V2.4.4z11a: {$pass} PASS / {$fail} FAIL\n";
exit($fail ? 1 : 0);
