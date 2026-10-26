<?php

declare(strict_types=1);
/** RC5 isolated tests: never connect to user database, write files or run migrations. */
$root = dirname(__DIR__);
require_once $root . '/core/classes/CapabilityRegistry.php';
require_once $root . '/core/classes/Db.php';
require_once $root . '/core/classes/DashboardCards.php';

$n = 0;
$check = static function (bool $condition, string $description) use (&$n): void {
    if (!$condition) { fwrite(STDERR, "FAIL: $description\n"); exit(1); }
    ++$n; echo "PASS: $description\n";
};
$reject = static function (mixed $payload, array $options) use ($check): void {
    try { DashboardCards::validate($payload, $options); $check(false, 'Invalid metric selection refused'); }
    catch (InvalidArgumentException) { $check(true, 'Invalid metric selection refused'); }
};
$default = DashboardCards::available('admin', []);
$check(array_keys($default) === ['published', 'drafts', 'scheduled', 'media', 'pages_total'], 'Core metrics available without modules');
$check(DashboardCards::sanitize(null, $default) === DashboardCards::DEFAULTS, 'Original four cards remain default');
$check(DashboardCards::sanitize('bad-data', $default) === DashboardCards::DEFAULTS, 'Malformed preferences fall back');
$check(DashboardCards::sanitize(['drafts','published','media','scheduled'], $default) === ['drafts','published','media','scheduled'], 'Saved card ordering retained');
$check(DashboardCards::sanitize(['drafts','bogus','pages_total','media'], $default) === ['drafts','published','pages_total','media'], 'Unavailable card slot replaced individually');
$check(DashboardCards::sanitize(['drafts','drafts','drafts','drafts'], $default) === ['drafts','published','scheduled','media'], 'Corrupt duplicates never produce duplicated cards');
$check(DashboardCards::available('user', []) === [], 'Unknown/unauthorized user role receives no metrics');
$check(array_keys(DashboardCards::available('editor', [])) === array_keys($default), 'Editor receives authorized Core metrics');
$full = DashboardCards::available('admin', ['forms','gallery']);
$check(count($full) === 8, 'All eight metric definitions available with both modules active');
$check(isset($full['forms'], $full['submissions'], $full['galleries']), 'Module statistics appear only when active');
$check(!isset(DashboardCards::available('admin', ['forms'])['galleries']), 'Inactive Gallery metric hidden');
$check(!isset(DashboardCards::available('admin', ['gallery'])['forms']), 'Inactive Forms metric hidden');
$check($full['galleries']['url'] === '/admin/gallerier', 'Gallery metric links to real Core route');
$check($full['submissions']['url'] === '/admin/forms/submissions', 'Forms submissions metric links to actual route');
$check(DashboardCards::validate(['galleries','submissions','forms','pages_total'], $full) === ['galleries','submissions','forms','pages_total'], 'Valid metric selection accepted');
foreach ([null, '', [], ['published'], ['published','drafts','scheduled'], ['published','drafts','scheduled','media','forms'],
    ['published','published','scheduled','media'], ['published','bogus','scheduled','media'], [0,'drafts','scheduled','media'],
    ['forms','drafts','scheduled','media'], ['published','drafts','scheduled',['media']],
    ['published','drafts',2=>'scheduled',4=>'media']] as $bad) {
    $reject($bad, $default);
}
$check(DashboardCards::sanitize(['galleries','submissions','forms','pages_total'], $default) === ['published','drafts','scheduled','pages_total'], 'Deactivated modules replaced with safe Core options');
$check(DashboardCards::sanitize(['galleries','submissions','forms','pages_total'], $full) === ['galleries','submissions','forms','pages_total'], 'Module metrics persist while modules are active');
$schema = (string)file_get_contents($root . '/database/install/empty-schema.sql');
$check(substr_count($schema, 'CREATE TABLE `user_dashboard_cards`') === 1, 'Fresh installs contain user preference table exactly once');
$check(str_contains($schema, 'FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE'), 'Deleting users cascades saved card preferences');
$install = (string)file_get_contents($root . '/core/classes/FirstInstall.php');
$check(str_contains($install, "'core:20261019_200_dashboard_cards'"), 'Installer explicitly reconciles RC5 migration');
$migration = (string)file_get_contents($root . '/database/updates/20261019_200_dashboard_cards.php');
$check(str_contains($migration, 'CREATE TABLE IF NOT EXISTS user_dashboard_cards'), 'RC5 migration is restart-safe DDL');
$check(!str_contains($migration, 'DROP TABLE') && !str_contains($migration, 'DELETE FROM'), 'RC5 migration does not remove data');
$controller = (string)file_get_contents($root . '/public_html/admin/index.php');
$check(str_contains($controller, "mode === 'dashboard-cards'") && str_contains($controller,'admin_verify_csrf('), 'Controller checks dashboard POST CSRF');
$check(str_contains($controller, 'DashboardCards::validate($_POST[\'cards\'] ?? null') && str_contains($controller, '$auth->id()'), 'Posted selection is validated and always saved for authenticated user');
$check(!str_contains($controller, "\$_POST['user_id']"), 'No ability to set another account preferences');
$view = (string)file_get_contents($root . '/public_html/admin/views/dashboard.php');
$check(str_contains($view, 'name="cards[]"') && str_contains($view, 'name="csrf_token"'), 'No-JavaScript fallback keeps four native selects inside POST with CSRF');
$check(str_contains($view, 'foreach ($cardSelection as $index => $key)') && str_contains($view, "t(\$card['label'])"), 'All cards render from validated selection');
$check(str_contains($view, 'admin_icon($card[\'icon\']') && str_contains($view,'admin_e($card[\'url\'])'), 'Metric icons and URLs are only from Core whitelist');
$js=(string)file_get_contents($root.'/public_html/admin/assets/js/admin.js');
$check(str_contains($js,'[data-dashboard-card-trigger]') && str_contains($js,'aria-expanded'), 'Core JS exposes per-card accessible menus');
$check(str_contains($js,"event.key === 'Escape'"), 'Card editor supports Escape dismissal');
$check(str_contains($js, 'new Set(next).size !== 4') && str_contains($view,'data-dashboard-cards-notice'), 'Swapped card choices cannot yield duplicate selection; server still validates');
$scss=(string)file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
$css=(string)file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$check($scss === $css, 'Admin SCSS and CSS byte-identical');
$check(str_contains($css, '.admin-metric__menu[hidden]'), 'Hidden per-card menus cannot leak into layout');
$translations=json_decode((string)file_get_contents($root.'/core/lang/translations.json'),true,512,JSON_THROW_ON_ERROR);
foreach (['dashboard.cards_customize','dashboard.cards_save','dashboard.cards_invalid','dashboard.cards_unavailable','dashboard.cards_forms','dashboard.cards_submissions','dashboard.cards_galleries'] as $key) {
    $check(isset($translations[$key]) && count(array_filter($translations[$key])) === 5, 'All five languages: '.$key);
}
$check(count(glob($root.'/database/updates/*.php')) === 4, 'Three historical Core migrations unchanged plus RC5');
echo "Dashboard Cards RC5: $n PASS / 0 FAIL (source and isolated logic, no DB/HTTP writes)\n";

/** Separate in-memory Db stub: real service calls but zero DB network access. */
class Rc5DashboardMemoryDb extends Db
{
    public array $users = [];
    public array $queries = [];
    public function __construct() {}
    public function fetchValue(string $sql, array $params = [])
    {
        $this->queries[] = ['read', $sql, $params];
        if (str_starts_with($sql, 'SELECT cards_json')) return $this->users[$params[0]] ?? null;
        if (str_contains($sql, 'FROM pages')) return 18;
        if (str_contains($sql, 'FROM module_forms')) return 6;
        if (str_contains($sql, 'FROM module_form_submissions')) return 12;
        if (str_contains($sql, 'FROM module_galleries')) return 9;
        return null;
    }
    public function fetchArray(string $sql, array $params = []): array
    {
        $this->queries[] = ['list', $sql, $params];
        return [['module_slug'=>'forms'], ['module_slug'=>'gallery']];
    }
    public function query(string $sql, array $params = []): void
    {
        $this->queries[] = ['write', $sql, $params];
        if (!str_starts_with($sql, 'INSERT INTO user_dashboard_cards')) throw new RuntimeException('Unexpected write');
        $this->users[(int)$params[0]] = (string)$params[1];
    }
}
$memoryDb = new Rc5DashboardMemoryDb();
$service = new DashboardCards($memoryDb);
$check($service->activeModules() === ['forms','gallery'], 'Core active modules read from allowlisted SELECT');
$alice = ['galleries','forms','submissions','pages_total'];
$bob = ['published','drafts','media','scheduled'];
$service->save(101, DashboardCards::validate($alice, $full));
$service->save(202, DashboardCards::validate($bob, $full));
$check($service->load(101, $full) === $alice, 'User 101 loads their saved metric order');
$check($service->load(202, $full) === $bob, 'User 202 loads independent metric order');
$check($service->load(303, $full) === DashboardCards::DEFAULTS, 'New user gets unchanged defaults');
$check($service->load(101, $default) === ['published','drafts','scheduled','pages_total'], 'Inactive modules do not leak stale metrics for existing user');
$check($service->extraCounts($alice, ['forms','gallery']) === ['galleries'=>9,'forms'=>6,'submissions'=>12,'pages_total'=>18], 'Bounded provider queries return actual counts');
$check($service->extraCounts($alice, []) === ['pages_total'=>18], 'Inactive module tables never queried');
$check(count(array_filter($memoryDb->queries, static fn($q) => $q[0] === 'write')) === 2, 'Only two explicit saves write preferences');
$check(!array_filter($memoryDb->queries, static fn($q) => str_contains($q[1], 'DELETE FROM') || str_contains($q[1], 'DROP TABLE')), 'Read and save never delete or mutate unrelated rows');
echo 'RC5 in-memory per-user storage: 8 PASS / 0 FAIL (no actual DB connection)'."\n";
