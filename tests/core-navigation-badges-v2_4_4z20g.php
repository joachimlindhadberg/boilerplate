<?php
/** V2.4.4z20g – authorized, request-scoped Core navigation badges. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
define('ROOTPATH', $root);
define('PUBLICPATH', $root . '/public_html');
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/modules/forms/FormsModule.php';
require_once $root . '/public_html/admin/incl/functions.php';
function t(string $key): string { return str_starts_with($key, 'forms:') ? substr($key, 6) : $key; }
$pass = $fail = 0;
function ck(bool $ok, string $msg): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "PASS: $msg\n"; }
    else { $fail++; echo "FAIL: $msg\n"; }
}
class BadgeDbMock extends Db {
    public int $unread = 7;
    public int $calls = 0;
    public string $sql = '';
    public array $params = [];
    public function __construct() {}
    public function fetchValue(string $sql, array $params = []) {
        $this->calls++;
        $this->sql = $sql;
        $this->params = $params;
        return $this->unread;
    }
}
class BadgeManagerMock extends ModuleManager {
    public array $modules = [];
    public array $menus = [];
    public array $counts = [];
    public int $moduleCalls = 0;
    public int $badgeCalls = 0;
    public function __construct() {}
    public function allModules(): array { $this->moduleCalls++; return $this->modules; }
    public function adminMenuItems(): array { return $this->menus; }
    public function adminNavigationBadgeCounts(array $allowedKeys): array {
        $this->badgeCalls++;
        return array_intersect_key($this->counts, array_fill_keys($allowedKeys, true));
    }
}

$mock = new BadgeManagerMock();
$mock->menus = [
    ['key'=>'module:forms:submissions','capability'=>'content.manage'],
    ['key'=>'module:secret','capability'=>'users.manage'],
    ['key'=>'module:unknown','capability'=>'secret.manage'],
];
$mock->counts = ['module:forms:submissions'=>7,'module:secret'=>9,'module:unknown'=>8,'modules'=>100];
$mock->modules = [
    ['valid'=>true,'compatible'=>true,'available'=>true,'update_available'=>true],
    ['valid'=>true,'compatible'=>true,'available'=>true,'update_available'=>true],
    ['valid'=>false,'compatible'=>true,'available'=>true,'update_available'=>true],
    ['valid'=>true,'compatible'=>false,'available'=>true,'update_available'=>true],
    ['valid'=>true,'compatible'=>true,'available'=>true,'update_available'=>false],
    ['valid'=>true,'compatible'=>true,'available'=>false,'update_available'=>true],
];
ck(AdminNavigationBadges::collect($mock,'admin') === ['modules'=>2,'module:forms:submissions'=>7,'module:secret'=>9], 'Admin gets only locally verified updates and authorized module badges');
ck(AdminNavigationBadges::collect($mock,'editor') === ['module:forms:submissions'=>7], 'Editor sees unread submissions but not Modules or Users-restricted badges');
ck($mock->moduleCalls === 1, 'Non-admin role never queries plugin update counts');
ck(AdminNavigationBadges::collect($mock,'') === [], 'Unknown role gets no badge data');
$mock->counts=['module:forms:submissions'=>0];
ck(AdminNavigationBadges::collect($mock,'editor') === [], 'Zero count omitted instead of showing decorative badge');
$mock->counts=['module:forms:submissions'=>-3];
ck(AdminNavigationBadges::collect($mock,'editor') === [], 'Negative counts omitted');
$mock->counts=['module:forms:submissions'=>'999'];
ck(AdminNavigationBadges::collect($mock,'editor') === [], 'String counts rejected rather than coercing untrusted providers');

$render = AdminNavigationBadges::render(3,'ulæste indsendelser');
ck(str_contains($render,'>3</span>') && str_contains($render, 'aria-label="3 ulæste indsendelser"'), 'Small count renders with accessible label');
ck(AdminNavigationBadges::render(0,'anything') === '', 'Zero count renders no markup');
ck(str_contains(AdminNavigationBadges::render(100,'updates'), '>99+</span>'), 'Large count displays 99+');
ck(!str_contains(AdminNavigationBadges::render(7,'<script>'), '<script>'), 'Accessibility label escapes potentially malicious text');
ck(!str_contains(AdminNavigationBadges::render(-100,'no'), '<span'), 'Negative badge never displayed');

$db = new BadgeDbMock();
$form = (new ReflectionClass(FormsModule::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(FormsModule::class,'repository'))->setValue($form,new FormsRepository($db));
ck($form instanceof ModuleAdminNavigationBadgeProviderInterface, 'Forms implements the optional Core badge provider');
ck($form->adminNavigationBadges([]) === [] && $db->calls===0, 'Unauthorized Forms badge does not query submissions');
ck($form->adminNavigationBadges(['module:forms']) === [] && $db->calls===0, 'Forms editor menu does not trigger submission count');
ck($form->adminNavigationBadges(['module:forms:submissions']) === ['module:forms:submissions'=>7], 'Active Forms supplies actual unread count');
ck($db->calls === 1 && str_contains($db->sql,'module_form_submissions') && str_contains($db->sql,'s.status = ?') && $db->params === ['new'], 'Only one aggregate COUNT query for unread records, with parameterized status');
$db->unread=0;
ck($form->adminNavigationBadges(['module:forms:submissions']) === [], 'Read-all state removes submission badge');
$db->unread=105;
ck($form->adminNavigationBadges(['module:forms:submissions']) === ['module:forms:submissions'=>105], 'Counter retains full value before UI clamps display');

$manager = (new ReflectionClass(ModuleManager::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(ModuleManager::class,'activeBooted'))->setValue($manager,true);
(new ReflectionProperty(ModuleManager::class,'instances'))->setValue($manager,['forms'=>$form]);
ck($manager->adminNavigationBadgeCounts([]) === [], 'Empty capability set cannot run any provider');
ck($manager->adminNavigationBadgeCounts(['module:other']) === [], 'Module cannot forge an unowned menu key');
ck($manager->adminNavigationBadgeCounts(['module:forms:submissions']) === ['module:forms:submissions'=>105], 'Core accepts only matching active module-owned menu keys');
(new ReflectionProperty(ModuleManager::class,'instances'))->setValue($manager,[]);
ck($manager->adminNavigationBadgeCounts(['module:forms:submissions']) === [], 'Inactive Forms cannot contribute a badge');

$files=[
    'public_html/admin/views/partials/layout.php',
    'public_html/admin/views/delete-confirm.php',
];
foreach ($files as $file) {
    $source=(string)file_get_contents($root.'/'.$file);
    ck(str_contains($source, 'data-admin-nav-key') && str_contains($source, 'admin_nav_badge_markup'), 'Shared Core nav uses accessible badge renderer: '.$file);
}
$navFile=(string)file_get_contents($root.'/public_html/admin/incl/functions.php');
ck(str_contains($navFile, "'badge_label' => (string)(\$moduleItem['badge_label']") && str_contains($navFile,'!empty($item[\'enabled\'])'), 'Core menu maps badges to enabled items only');
$index=(string)file_get_contents($root.'/public_html/admin/index.php');
ck(str_contains($index, "\$mode === 'nav-badges'") && str_contains($index,"\$auth->mustChangePassword()") && str_contains($index,'admin_nav_badges()'), 'AJAX endpoint is behind authentication, role and password-change gates');
ck(str_contains($index,"'GET'") && str_contains($index,"'Content-Type: application/json; charset=utf-8'"), 'Badge snapshot is read-only JSON');
$js=(string)file_get_contents($root.'/public_html/admin/assets/js/admin.js');
ck(str_contains($js,'window.setInterval(refreshNavBadges, 90000)') && str_contains($js,"document.visibilityState === 'hidden'"), 'Navigation updates every 90 seconds only when visible');
ck(str_contains($js,'badge.textContent =') && !str_contains($js,'badge.innerHTML'), 'DOM update uses safe textContent and no HTML injection');
$css=(string)file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$scss=(string)file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
ck($css===$scss && str_contains($css,'.admin-nav__badge {'), 'Only Core owns badge CSS; SCSS/CSS byte-identical');
$translations=(array)json_decode((string)file_get_contents($root.'/core/lang/translations.json'),true);
$formsTranslations=(array)json_decode((string)file_get_contents($root.'/modules/forms/lang/translations.json'),true);
foreach (['da','en','de','sv','nb'] as $locale) {
    ck(!empty($translations['ui.nav_module_updates'][$locale]) && !empty($translations['ui.nav_pending_items'][$locale]) && !empty($formsTranslations['Ulæste indsendelser'][$locale]), 'Badge descriptions translated: '.$locale);
}
$manifest=(array)json_decode((string)file_get_contents($root.'/modules/forms/module.json'),true);
ck(($manifest['version']??null)==='1.3.12', 'Forms plugin version unchanged; no accidental module-update prompt');
$meta=(string)file_get_contents($root.'/public_html/incl/init.php');
ck(preg_match("/BOILERPLATE_VERSION', '([0-9.]+)'/", $meta, $matches) === 1 && version_compare($matches[1], '2.4.4.84', '>='), 'Release retains z20g or newer version');
echo "Core Navigation Badges z20g: $pass PASS / $fail FAIL\n";
exit($fail===0?0:1);
