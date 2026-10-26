<?php
/** V2.4.4z20d – two independent Core nav items from one active module. */
declare(strict_types=1);
$root = dirname(__DIR__);
define('ROOTPATH', $root);
define('PUBLICPATH', $root . '/public_html');
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/modules/forms/FormsModule.php';
function t(string $key): string { return str_starts_with($key, 'forms:') ? substr($key, 6) : $key; }
function admin_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_icon(string $name, string $class = ''): string { return AdminIcons::render($name, $class); }
function admin_csrf_token(): string { return 'test-csrf'; }
function admin_render_pagination(int $page, int $pages, callable $url, string $label): void {}
function admin_icon_action_button(string $icon, string $label, array $attributes = [], string $tone = 'neutral'): string { return AdminIcons::actionButton($icon, $label, $attributes, $tone); }
$pass = $fail = 0;
function assert_contract(bool $ok, string $why): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "PASS: $why\n"; }
    else { $fail++; echo "FAIL: $why\n"; }
}
$instance = (new ReflectionClass(FormsModule::class))->newInstanceWithoutConstructor();
$menu = $instance->adminMenu();
assert_contract(count($menu) === 2, 'Forms declares exactly two standalone top-level items');
assert_contract(array_column($menu, 'route') === ['forms', 'forms/submissions'], 'Independent menu routes retain existing endpoints');
assert_contract(array_column($menu, 'key') === ['module:forms', 'module:forms:submissions'], 'Unique active menu keys');
assert_contract(($menu[0]['label'] ?? '') === 'Forms' && ($menu[1]['label'] ?? '') === 'forms:Indsendelser', 'Forms and localized Indsendelser labels');
assert_contract(($menu[1]['icon'] ?? '') === 'inbox' && str_contains(AdminIcons::render('inbox'), '<path'), 'Second menu icon is owned by Core');
assert_contract(count(array_unique(array_column($menu, 'capability'))) === 1 && ($menu[1]['capability'] ?? '') === 'content.manage', 'Both items enforce identical Core content.manage access');
assert_contract(!isset($menu[0]['children']) && !isset($menu[1]['children']) && !isset($menu[1]['parent']), 'Never registered as nested sub-menu');
$routes = $instance->adminRoutes();
$byRoute = array_column($routes, null, 'route');
assert_contract(count($routes) === 4, 'Original four admin routes retained without additions');
assert_contract(($byRoute['forms']['nav_key'] ?? '') === 'module:forms', 'Forms and editor select Forms nav');
assert_contract(($byRoute['forms/submissions']['nav_key'] ?? '') === 'module:forms:submissions', 'Submissions list selects independent nav');
assert_contract(($byRoute['forms/submission']['nav_key'] ?? '') === 'module:forms:submissions', 'Submission detail selects independent nav');
assert_contract(($byRoute['forms/settings']['nav_key'] ?? '') === 'module:forms', 'Forms global settings selects Forms nav');
foreach (['forms/submissions' => 'adminSubmissions', 'forms/submission' => 'adminSubmission'] as $route => $method) {
    assert_contract(($byRoute[$route]['handler'] ?? '') === $method && in_array('POST', $byRoute[$route]['methods'] ?? [], true), 'Original submission POST handlers and routes: ' . $route);
}
$manager = (new ReflectionClass(ModuleManager::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(ModuleManager::class, 'activeBooted'))->setValue($manager, true);
(new ReflectionProperty(ModuleManager::class, 'instances'))->setValue($manager, ['forms' => $instance]);
$entries = $manager->adminMenuItems();
assert_contract(count($entries) === 2, 'Core consumes two Forms menu registrations');
assert_contract(array_column($entries, 'href') === ['/admin/forms', '/admin/forms/submissions'], 'Core generates both independent hrefs');
assert_contract(array_column($entries, 'module_slug') === ['forms', 'forms'], 'Both menu items belong to same plugin');
assert_contract(array_column($entries, 'key') === ['module:forms', 'module:forms:submissions'], 'Core preserves exact active menu keys');
assert_contract(($manager->resolveAdminRoute('forms/submissions')['nav_key'] ?? null) === 'module:forms:submissions', 'Core route dispatcher selects submissions nav');
assert_contract(($manager->resolveAdminRoute('forms/submission')['nav_key'] ?? null) === 'module:forms:submissions', 'Detail dispatcher selects submissions nav');
(new ReflectionProperty(ModuleManager::class, 'instances'))->setValue($manager, []);
assert_contract($manager->adminMenuItems() === [], 'When no active Forms instance exists neither menu item is registered');
assert_contract($manager->resolveAdminRoute('forms/submissions') === null, 'Inactive Forms exposes no submission route');
// Real module content remains independent from global navigation.
$controller = (string)file_get_contents($root . '/modules/forms/admin/controllers/FormsAdminController.php');
assert_contract(substr_count($controller, "'activeNav' => 'module:forms:submissions'") === 2, 'Both submission views render independent global active state');
$workspace = (string)file_get_contents($root . '/modules/forms/admin/workspace.php');
assert_contract(str_contains($workspace, "'navigationMode' => 'none'") && !str_contains($workspace, "'navigationMode' => 'inline'"), 'No duplicate sidebar or inline Forms workspace menu');
$adapter = require $root . '/modules/forms/admin/workspace.php';
$submissionsData = [
  'submissions' => [], 'paging' => ['page'=>1,'pages'=>1,'from'=>0,'to'=>0],
  'total'=>0,'forms'=>[],'selectedFormId'=>0,'selectedStatus'=>'','search'=>'','notice'=>null,
];
ob_start(); $adapter('submissions', $submissionsData); $submissionsHtml = ob_get_clean();
assert_contract(!str_contains($submissionsHtml,'admin-section-nav') && !str_contains($submissionsHtml,'admin-section-layout__sidebar'), 'Standalone submission overview has no redundant local navigation');
assert_contract(str_contains($submissionsHtml,'admin-section-layout--full') && str_contains($submissionsHtml,'data-admin-live-search'), 'Standalone submission screen is full-width with live search');
assert_contract(str_contains($submissionsHtml,'forms-submissions-delete-dialog') && str_contains($submissionsHtml,'submission_action'), 'Existing Core delete dialog and submission action maintained');
assert_contract(str_contains($submissionsHtml,'/admin/forms/submissions'), 'Existing filtered submissions route maintained');
$formsData = ['forms'=>[],'search'=>'','page'=>1,'pages'=>1,'total'=>0,'from'=>0,'to'=>0,'notice'=>null,'deleteDependenciesById'=>[]];
ob_start(); $adapter('index',$formsData); $formsHtml=ob_get_clean();
assert_contract(!str_contains($formsHtml,'admin-section-nav') && str_contains($formsHtml,'href="/admin/forms/settings"'), 'Forms overview keeps settings accessible outside list toolbar, no local nav');
assert_contract(str_contains($formsHtml,'forms-list-delete-dialog'), 'Forms list keeps existing safe Core delete modal');
$editor = (string)file_get_contents($root . '/modules/forms/admin/views/partials/form-editor.php');
assert_contract(!str_contains($editor,'admin-editor-tabs__link') && !str_contains($editor,'href="/admin/forms/submissions'), 'Editor no longer includes Indsendelser link or fake fourth tab');
foreach (['fields', 'settings', 'emails'] as $tab) {
    assert_contract(str_contains($editor, 'data-admin-editor-tab="' . $tab . '"'), 'Editor retains original accessible tab: ' . $tab);
}
foreach (['save_submissions','send_notification','send_confirmation'] as $key) {
    assert_contract(str_contains($editor, 'name="'.$key.'"'), 'Form storage/email configuration intact: ' . $key);
}
$manifest=json_decode((string)file_get_contents($root.'/modules/forms/module.json'),true);
assert_contract(($manifest['version'] ?? '') === '1.3.12', 'Module version unchanged to avoid disabling active installed module on mismatched version');
$zipSource=(string)file_get_contents($root.'/public_html/incl/init.php');
preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", $zipSource, $mv);
assert_contract(isset($mv[1]) && version_compare($mv[1], '2.4.4.81', '>='), 'Internal CMS version retains z20d compatibility');
$scss=(string)file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
$css=(string)file_get_contents($root.'/public_html/admin/assets/css/admin.css');
assert_contract($scss===$css,'Core SCSS/CSS byte identical');
$moduleLang=json_decode((string)file_get_contents($root.'/modules/forms/lang/translations.json'),true);
foreach (['da','en','de','sv','nb'] as $locale) {
    assert_contract(trim((string)($moduleLang['Indsendelser'][$locale] ?? '')) !== '', 'Independent menu translated to '.$locale);
}
echo "Forms Independent Main Menu z20d: $pass PASS / $fail FAIL\n";
exit($fail === 0 ? 0 : 1);
