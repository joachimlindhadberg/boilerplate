<?php
/** V2.4.4z20c – The Forms module has no Core section sidebar at any screen. */
declare(strict_types=1);
$root = dirname(__DIR__);
define('PUBLICPATH', $root . '/public_html');
function t(string $key): string { return str_starts_with($key, 'forms:') ? substr($key, 6) : $key; }
function admin_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_csrf_token(): string { return 'test-csrf'; }
function admin_icon(string $name, string $class = ''): string { return '<svg aria-hidden="true"></svg>'; }
function admin_icon_action_button(string $icon, string $label, array $attributes = [], string $tone = 'neutral'): string { return AdminIcons::actionButton($icon, $label, $attributes, $tone); }
function admin_render_pagination(int $page, int $pages, callable $link, string $label): void { /* fixture only */ }
require_once $root . '/core/classes/Autoload.php';
$passes = $fails = 0;
function check(bool $ok, string $description): void {
    global $passes, $fails;
    if ($ok) { ++$passes; echo "PASS: $description\n"; }
    else { ++$fails; echo "FAIL: $description\n"; }
}
$viewPath = $root . '/public_html/admin/views/partials/section-workspace.php';
$render = static function (array $spec) use ($viewPath): string {
    $sectionWorkspace = $spec;
    $bodyHtml = '<section id="test-payload">Content</section>';
    ob_start();
    require $viewPath;
    return (string)ob_get_clean();
};
$baseSpec = [
    'heading' => 'Formularer',
    'description' => 'Beskrivelse',
    'active' => 'forms',
    'label' => 'Forms navigation',
    'items' => [
        ['id' => 'forms', 'label' => 'Formularer', 'href' => '/admin/forms'],
        ['id' => 'submissions', 'label' => 'Indsendelser', 'href' => '/admin/forms/submissions'],
        ['id' => 'settings', 'label' => 'Indstillinger', 'href' => '/admin/forms/settings'],
    ],
];
$default = $render($baseSpec);
check(str_contains($default, '<aside class="admin-section-layout__sidebar"'), 'Existing Core sidebar is still the default for other modules');
check(str_contains($default, '<nav class="admin-section-nav"'), 'Legacy Core navigation CSS class unchanged');
check(str_contains($default, 'admin-section-layout__content'), 'Legacy Core content container retained');
check(!str_contains($default, 'admin-section-layout--full'), 'No unexpected full-width override for default modules');
$inline = $render($baseSpec + ['navigationMode' => 'inline']);
check(str_contains($inline, 'admin-section-layout--full'), 'Inline Core layout is full-width');
check(!str_contains($inline, '<aside'), 'Inline navigation renders no sidebar/aside');
check(str_contains($inline, 'admin-section-nav--inline'), 'Inline navigation uses reusable Core variant');
foreach (['/admin/forms', '/admin/forms/submissions', '/admin/forms/settings'] as $route) {
    check(str_contains($inline, 'href="' . $route . '"'), 'Global route is discoverable: ' . $route);
}
check(substr_count($inline, 'aria-current="page"') === 1, 'Current global link remains accessible');
check(str_contains($inline, 'id="test-payload"'), 'Inline layout still renders content');
$none = $render($baseSpec + ['navigationMode' => 'none']);
check(str_contains($none, 'admin-section-layout--full'), 'No-navigation Core layout is full-width');
check(!str_contains($none, '<aside') && !str_contains($none, '<nav'), 'None mode emits no extra nav or aside');
check(str_contains($none, 'id="test-payload"'), 'None mode retains page content');
$invalid = $render($baseSpec + ['navigationMode' => 'unknown']);
check(str_contains($invalid, '<aside class="admin-section-layout__sidebar"'), 'Unknown mode fails back to original Core sidebar');
$evil = $render(array_replace($baseSpec, ['navigationMode' => 'inline', 'label' => '"><script>alert(1)</script>']));
check(!str_contains($evil, '<script>') && str_contains($evil, '&lt;script&gt;'), 'Unsafe workspace aria-label cannot inject HTML');

$adapter = require $root . '/modules/forms/admin/workspace.php';
$indexData = [
    'forms' => [], 'deleteDependenciesById' => [], 'search' => '',
    'page' => 1, 'pages' => 1, 'total' => 0, 'notice' => null,
];
ob_start();
$adapter('index', $indexData);
$index = (string)ob_get_clean();
check(!str_contains($index, '<aside class="admin-section-layout__sidebar"'), 'Forms list has no Core left sidebar');
check(!str_contains($index, 'admin-section-nav--inline') && !str_contains($index, '<nav class="admin-section-nav'), 'Forms list no longer duplicates global navigation after z20d');
check(str_contains($index, 'href="/admin/forms/settings"'), 'Global Forms settings accessible from list');
check(str_contains($index, 'admin-section-layout--full'), 'Forms list uses full-width Core workspace');
check(str_contains($index, 'data-admin-live-list'), 'Form list live-search region is unchanged');
check(str_contains($index, 'forms-list-delete-dialog'), 'Form list shared Core delete dialog is unchanged');

$newData = [
    'formData' => ['name' => '', 'status' => 'active', 'save_submissions' => 1, 'success_action' => 'message'],
    'fields' => [], 'builderRows' => [], 'errors' => [], 'fieldTypes' => ['text' => 'Text'],
    'formTemplates' => [], 'publishedPages' => [], 'notificationEmail' => '',
    'isEdit' => false, 'formId' => 0, 'deleteDependencies' => ['pages' => 0, 'submissions' => 0],
    'notice' => null,
];
ob_start();
$adapter('new', $newData);
$new = (string)ob_get_clean();
check(!str_contains($new, '<aside class="admin-section-layout__sidebar"'), 'Forms editor has no Core left sidebar');
check(!str_contains($new, 'admin-section-nav--inline'), 'Forms editor does not duplicate its own tabs with global top links');
check(str_contains($new, 'admin-section-layout--full'), 'Forms editor gets full width');
check(str_contains($new, 'data-admin-editor-tabs') && str_contains($new, 'data-admin-editor-panel="fields"'), 'Local editor tabs and Builder retained');
check(str_contains($new, '<aside class="admin-detail-sidebar"'), 'Distinct editor properties panel and status retained');
check(str_contains($new, 'name="status"') && str_contains($new, 'name="save_submissions"'), 'Status and save_submissions remain inside editor POST');

$adapterSource = (string)file_get_contents($root . '/modules/forms/admin/workspace.php');
check(str_contains($adapterSource, "'navigationMode' => 'none'"), 'All Forms screens use no duplicated navigation after z20d');
$css = (string)file_get_contents($root . '/public_html/admin/assets/css/admin.css');
check(str_contains($css, '.admin-section-layout--full { display: block; }'), 'Core full-width layout has a non-grid override at desktop');
check(str_contains($css, '.admin-section-nav--inline') && str_contains($css, 'flex-direction: row;'), 'Inline links stay horizontal on desktop');
check($css === file_get_contents($root . '/public_html/admin/assets/scss/admin.scss'), 'Core SCSS/CSS byte identical');
check(str_contains($adapterSource, "if (\$hasControllerWorkspace)"), 'Legacy cached-controller compatibility preserved');
$moduleSource = (string)file_get_contents($root . '/modules/forms/admin/views/partials/form-editor.php');
check(str_contains($moduleSource, 'name="save_submissions"') && str_contains($moduleSource, 'forms-delete-dialog'), 'Existing editor save and delete controls preserved in source');
check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", (string)file_get_contents($root . '/public_html/incl/init.php'), $version) === 1 && version_compare($version[1], '2.4.4.80', '>='), 'Internal version advanced to 2.4.4.80');
echo "Forms Full-width Workspace V2.4.4z20c: $passes PASS / $fails FAIL\n";
exit($fails === 0 ? 0 : 1);
