<?php

declare(strict_types=1);

/** z11 compatibility: Navigation overview uses Core actions and the safe menu delete workflow. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $name) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . "\n";
    if ($ok) ++$pass; else ++$fail;
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
function admin_csrf_token(): string { return 'synthetic-token'; }
$_SERVER['REQUEST_METHOD'] = 'GET';
$view = $root . '/public_html/admin/views/navigation/index.php';
$source = (string)file_get_contents($view);
$layout = "require __DIR__ . '/../partials/layout.php';";
$check(substr_count($source, $layout) === 1, 'Original Core admin layout retained');
$check(str_contains($source, 'AdminListUI::actionGroup('), 'Navigation overview uses Core action groups');
$check(!str_contains($source, 'admin-button--compact'), 'Legacy list-specific text action removed');
$check(str_contains($source, "'type' => 'button'") && str_contains($source, "'disabled' => true"), 'Assigned menu gets native disabled action');
$check(str_contains($source, "'tone' => 'danger'"), 'Delete uses Core danger tone');
$check(!str_contains($source, 'AdminListUI::filterTabs('), 'No fictitious filters introduced into Navigation');
$testPath = tempnam(sys_get_temp_dir(), 'navigation-z11-');
try {
    $includeSource = str_replace($layout, 'echo $bodyHtml;', $source);
    $partsRoot = $root . '/public_html/admin/views/navigation/partials/';
    $includeSource = str_replace("require __DIR__ . '/partials/context.php';", 'require ' . var_export($partsRoot . 'context.php', true) . ';', $includeSource);
    $includeSource = str_replace("require __DIR__ . '/partials/header.php';", 'require ' . var_export($partsRoot . 'header.php', true) . ';', $includeSource);
    file_put_contents($testPath, $includeSource);
    $render = static function (array $menuRows) use ($testPath): string {
        $menus = $menuRows;
        $mode = 'list'; $selectedMenu = null; $headerSlug = 'primary'; $footerSlug = 'footer';
        $notice = null; $errors = [];
        ob_start(); include $testPath;
        return (string)ob_get_clean();
    };
    $sample = [
        ['id'=>1, 'name'=>'Hovedmenu', 'slug'=>'primary', 'item_count'=>6],
        ['id'=>2, 'name'=>'Footer', 'slug'=>'footer', 'item_count'=>4],
        ['id'=>7, 'name'=>'Kampagne <script>alert(1)</script>', 'slug'=>'campaign', 'item_count'=>2],
    ];
    $html = $render($sample);
    $check(substr_count($html, 'admin-table-actions--icons')===3, 'Exactly one Core action group per menu');
    $check(preg_match_all('/<(?:a|button)\b[^>]*class="admin-icon-action(?:\s|")/', $html)===6, 'Each menu displays exactly Edit and Delete');
    $check(str_contains($html, 'href="/admin/navigation?mode=edit&amp;id=7"'), 'Edit uses original navigation endpoint');
    $check(str_contains($html, 'data-admin-open-delete="nav-list-delete-dialog"') && str_contains($html, 'data-nav-delete-id="7"'), 'Unassigned delete opens the shared modal (z11a)');
    $check(!str_contains($html, 'href="/admin/navigation?mode=delete&amp;id=1"') && !str_contains($html, 'href="/admin/navigation?mode=delete&amp;id=2"'), 'Assigned header/footer menus do not expose delete links');
    $check(substr_count($html, ' disabled')===2, 'Two assigned menus have disabled Delete buttons');
    $check(str_contains($html, 'Kampagne &lt;script&gt;alert(1)&lt;/script&gt;'), 'Untrusted menu names are escaped');
    $check(!str_contains($html, '<script>alert(1)</script>'), 'No executable HTML from menu names');
    $check(str_contains($html, 'aria-label="Rediger: Kampagne &lt;script&gt;'), 'Edit has row-specific accessible name');
    $check(str_contains($html, 'aria-label="Slet menu: Kampagne &lt;script&gt;'), 'Delete has row-specific accessible name');
    $check(str_contains($html, t('nav.cannot_delete_assigned')), 'Assigned menu tooltip explains why deletion is blocked');
    $check(str_contains($html, 'Header') && str_contains($html, 'Footer'), 'Existing menu placements are preserved');
    $check(str_contains($html, '>6</td>') && str_contains($html, '>2</td>'), 'Server-supplied menu item counts are unchanged');
    $check(str_contains($html, 'href="/admin/navigation?mode=new"'), 'Create menu route retained');
    $empty = $render([]);
    $check(str_contains($empty, 'admin-empty-state') && !str_contains($empty, 'admin-table-actions--icons'), 'Empty list has no invented actions');
    $check(str_contains($html, 'method="post"') && str_contains($html, 'name="csrf_token"'), 'Shared dialog makes only a CSRF-protected POST request (z11a)');
} finally { @unlink($testPath); }
$controller = (string)file_get_contents($root . '/public_html/admin/navigation.php');
$confirm = (string)file_get_contents($root . '/public_html/admin/views/navigation/delete.php');
$model = (string)file_get_contents($root . '/core/classes/Navigation.php');
$check(str_contains($controller, "'delete' => 'navigation/delete'"), 'Existing GET confirmation view routing retained');
$check(str_contains($controller, "\$mode === 'delete'") && str_contains($controller, "\$action === 'delete_menu'"), 'Deleting requires explicit action on delete route');
$check(str_contains($controller, 'admin_verify_csrf(') && str_contains($controller, "\$navigation->deleteMenu(\$menuId)"), 'Server checks CSRF before deletion');
$check(str_contains($controller, "empty(\$_POST['confirmed'])"), 'Server requires explicit deletion confirmation');
$check(str_contains($confirm, 'method="post"') && str_contains($confirm, 'name="csrf_token"') && str_contains($confirm, 'name="confirmed"'), 'Existing delete confirmation uses POST and CSRF');
$check(str_contains($model, 'function deleteMenu(int $id)') && str_contains($model, 'if ($assigned > 0)'), 'Server independently blocks assigned menu deletion');
foreach (['da','en','de','sv','nb'] as $locale) {
    $check(trim((string)($dictionary['nav.cannot_delete_assigned'][$locale] ?? '')) !== '', "Disabled-state explanation translated: {$locale}");
}
$check(file_get_contents($root.'/public_html/admin/assets/scss/admin.scss')===file_get_contents($root.'/public_html/admin/assets/css/admin.css'), 'Core admin SCSS and compiled CSS unchanged and synchronized');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", (string)file_get_contents($root.'/public_html/incl/init.php'), $match) === 1 && version_compare($match[1], '2.4.4.64', '>='), 'Version retains z11 compatibility');
echo "Core Navigation List Alignment V2.4.4z11: {$pass} PASS / {$fail} FAIL\n";
exit($fail ? 1 : 0);
