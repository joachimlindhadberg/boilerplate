<?php
/** z20p: exercise the *actual* read-only scheduled-content controller and view through synthetic registry results. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/public_html/admin/incl/functions.php';

// The real registry is replaced only at its documented read contract. No database or web server.
class ScheduledContent {
    public static array $records = ['upcoming' => [], 'overdue' => []];
    public static array $counts = [];
    public static array $calls = [];
    public static array $failOn = [];
    public function count(string $scope = 'upcoming'): int {
        self::$calls[] = ['count', $scope];
        if (in_array($scope, self::$failOn, true)) throw new RuntimeException('Synthetic provider failure');
        return self::$counts[$scope] ?? count(self::$records[$scope] ?? []);
    }
    public function items(string $scope = 'upcoming', int $limit = 100): array {
        self::$calls[] = ['items', $scope, $limit];
        if (in_array($scope, self::$failOn, true)) throw new RuntimeException('Synthetic provider failure');
        return array_slice(self::$records[$scope] ?? [], 0, $limit);
    }
    public static function typeLabel(string $type): string {
        return ucfirst(str_replace(['-', '_'], ' ', $type));
    }
}
class PageScheduler {
    public static function localLabel(string $utc): string { return $utc . ' UTC'; }
}
$dictionary = json_decode((string)file_get_contents($root . '/core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
$GLOBALS['z20p_locale'] = 'da';
function t(string $key, array $params = []): string {
    global $dictionary;
    $value = (string)($dictionary[$key][$GLOBALS['z20p_locale']] ?? $key);
    foreach ($params as $name => $text) $value = str_replace('{' . $name . '}', (string)$text, $value);
    return $value;
}
$GLOBALS['z20p_html'] = '';
$GLOBALS['z20p_data'] = [];
function z20p_capture_render(string $screen, array $params): void {
    global $z20p_view;
    if ($screen !== 'scheduled-content') throw new RuntimeException('Wrong view');
    $GLOBALS['z20p_data'] = $params;
    extract($params, EXTR_SKIP);
    ob_start();
    try { require $z20p_view; $GLOBALS['z20p_html'] = (string)ob_get_clean(); }
    catch (Throwable $e) { ob_end_clean(); throw $e; }
}
$pass = 0; $fail = 0;
$check = static function (bool $result, string $name) use (&$pass, &$fail): void {
    echo ($result ? 'PASS' : 'FAIL') . ': ' . $name . "\n";
    $result ? ++$pass : ++$fail;
};
$makeItem = static function (int $id, string $scope): array {
    $post = $id % 2 === 0;
    return [
        'type' => $post ? 'post' : 'page',
        'title' => $scope . ' item ' . $id,
        'action_type' => $id % 4 < 2 ? 'publish' : 'unpublish',
        'due_at_utc' => '2026-11-01 12:00:00',
        'edit_url' => $post ? '/admin/blog?mode=edit&id=' . $id : '/admin/sider?mode=edit&id=' . $id,
    ];
};
for ($i = 1; $i <= 27; $i++) ScheduledContent::$records['upcoming'][] = $makeItem($i, 'upcoming');
for ($i = 1; $i <= 7; $i++) ScheduledContent::$records['overdue'][] = $makeItem($i, 'overdue');
$controller = (string)file_get_contents($root . '/public_html/admin/scheduled-content.php');
$view = (string)file_get_contents($root . '/public_html/admin/views/scheduled-content.php');
$oldLayout = "require __DIR__ . '/partials/layout.php';";
$check(substr_count($view, $oldLayout) === 1, 'Actual scheduled view retains original shared Core layout');
$check(str_contains($controller, "define('ADMIN_REQUIRED_CAPABILITY', 'content.manage')"), 'Existing server-side content.manage protection retained');
$check(str_contains($controller, "'GET'" ) && str_contains($controller, '405'), 'Scheduled overview remains GET-only');
$check(str_contains($controller, '$registry->items($scope, 2000)'), 'Only selected scope fetches original 2000-row bound');
$check(substr_count($view, 'AdminListUI::filterTabs(') === 1, 'Filter markup delegates once to Core');
$check(substr_count($view, 'AdminListUI::actionGroup(') === 1, 'Row actions delegate once to Core');
$check(!str_contains($view, '<nav class="admin-filter-tabs"') && !str_contains($view, '<a class="admin-button admin-button--secondary admin-button--compact"'), 'Old manual tabs and text Edit links removed');
$check(str_contains($view, 'method="get" action="/admin/scheduled-content"') && str_contains($view, 'type="submit" class="admin-button admin-button--compact admin-button--secondary"'), 'Consciously retained explicit GET filter submit, works without JS');
$check(str_contains($view, 'name="scope"') && str_contains($view, 'name="type"') && str_contains($view, 'name="action"'), 'Existing GET filter fields retained');
$check(str_contains($view, "'icon' => 'edit'") && str_contains($view, "(string)\$item['edit_url']"), 'Original provider-owned edit URL goes to safe Core action component');
$check(str_contains($controller, "\$scopeCounts[\$scope] = \$count") && str_contains($controller, "\$scopeCounts[\$otherScope] = \$registry->count(\$otherScope)"), 'Both tab counts use registry server-side counts rather than page-sized items');
$check(str_contains($controller, "if (\$type !== '')") && str_contains($controller, "if (\$action !== '')"), 'Existing combined dropdown filters retained');
$check(str_contains($controller, "'truncated' => \$truncated") && str_contains($view, 'schedule.limit_notice'), 'Original 2000-result warning preserved');
$check((string)file_get_contents($root . '/public_html/admin/assets/css/admin.css') === (string)file_get_contents($root . '/public_html/admin/assets/scss/admin.scss'), 'Core CSS/SCSS byte-identical');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", (string)file_get_contents($root . '/public_html/incl/init.php'), $match)===1 && version_compare($match[1], '2.4.4.104', '>='), 'Compatible with scheduled-content release 2.4.4.104 or later');
$check(is_file($root . '/docs/releases/SCHEDULED-CONTENT-CORE-UI-ALIGNMENT-V2.4.4z20p.md'), 'Candidate release document exists');

// Swap *only* the outer layout and the authenticated request bootstrap. Both controller
// and scheduled list view stay the production sources, including all filters and URLs.
$tmpController = tempnam(sys_get_temp_dir(), 'z20p-controller-');
$tmpView = tempnam(sys_get_temp_dir(), 'z20p-view-');
$check($tmpController !== false && $tmpView !== false, 'Isolated read-only render fixtures created');
if ($tmpController === false || $tmpView === false) exit(1);
try {
    $bootstrap = "require_once __DIR__ . '/incl/module-bootstrap.php';";
    $check(substr_count($controller, $bootstrap) === 1, 'Production admin authorization/bootstrap is still present');
    $controllerFixture = str_replace($bootstrap, '/* Synthetic CLI: outer auth bootstrap supplied by test */', $controller);
    $controllerFixture = str_replace("define('ADMIN_REQUIRED_CAPABILITY', 'content.manage');", '/* Synthetic CLI: capability verified above */', $controllerFixture);
    $controllerFixture = str_replace("error_log('Scheduled content admin: ' . \$e->getMessage());", '/* Expected test provider error */', $controllerFixture);
    file_put_contents($tmpController, str_replace("admin_render('scheduled-content', [", "z20p_capture_render('scheduled-content', [", $controllerFixture));
    file_put_contents($tmpView, str_replace($oldLayout, 'echo $bodyHtml;', $view));
    $GLOBALS['z20p_view'] = $tmpView;
    $render = static function (array $query, string $locale = 'da') use ($tmpController): array {
        $GLOBALS['z20p_locale'] = $locale;
        $_GET = $query;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ScheduledContent::$calls = [];
        $auth = new class { public function user(): array { return ['id' => 1, 'firstname' => 'Test']; } };
        require $tmpController;
        return [$GLOBALS['z20p_html'], $GLOBALS['z20p_data'], ScheduledContent::$calls];
    };
    foreach (['da', 'en', 'de', 'sv', 'nb'] as $locale) {
        foreach (['schedule.actions_for', 'schedule.scope', 'schedule.upcoming', 'schedule.overdue', 'schedule.filter', 'Rediger'] as $key) {
            $check(is_string($dictionary[$key][$locale] ?? null) && trim((string)$dictionary[$key][$locale]) !== '', "$key translated for $locale");
        }
        [$html, $data, $calls] = $render(['scope'=>'upcoming','page'=>'2'], $locale);
        $check($calls === [['count','upcoming'],['count','overdue'],['items','upcoming',2000]], "Two count-only scopes and one selected-scope items fetch ($locale)");
        $check($data['scopeCounts'] === ['upcoming'=>27,'overdue'=>7] && $data['total']===27 && $data['paging']['page']===2 && count($data['items'])===10, "Correct scope counters and 10-row pagination ($locale)");
        $check(substr_count($html, '<nav class="admin-filter-tabs"')===1 && substr_count($html, 'aria-current="page"')===1, "One semantic Core tab navigation and active choice ($locale)");
        $check(str_contains($html, '<strong>27</strong>') && str_contains($html, '<strong>7</strong>'), "True global tab counts rather than 10 visible actions ($locale)");
        $check(str_contains($html, 'href="/admin/scheduled-content?scope=upcoming" class="is-active" aria-current="page"') && str_contains($html, 'href="/admin/scheduled-content?scope=overdue"'), "Tabs use preserved endpoint and reset page ($locale)");
        $check(substr_count($html, 'class="admin-table-actions admin-table-actions--icons"')===10 && substr_count($html, 'admin-icon-action')>=10, "10 genuine Core icon action groups ($locale)");
        $check(str_contains($html, '/admin/blog?mode=edit&amp;id=') && str_contains($html, '/admin/sider?mode=edit&amp;id='), "Module and Page edit URLs remain exact ($locale)");
        $check(str_contains($html, 'role="group" aria-label="') && str_contains($html, admin_e(t('schedule.actions_for', ['title'=>'upcoming item 11']))), "Row-specific group label translated ($locale)");
        $check(str_contains($html, 'class="admin-pagination"') && str_contains($html, '/admin/scheduled-content?scope=upcoming&amp;page='), "Pager retains correct scope ($locale)");
        $check(str_contains($html, 'name="scope" value="upcoming"') && str_contains($html, 'name="type"') && str_contains($html, 'name="action"'), "GET form fully functional without JS ($locale)");
    }
    [$filtered, $filters, $calls] = $render(['scope'=>'overdue','type'=>'post','action'=>'unpublish','page'=>'4']);
    $check($filters['scopeCounts'] === ['upcoming'=>27,'overdue'=>7] && $filters['scope'] === 'overdue' && $filters['type'] === 'post' && $filters['action'] === 'unpublish', 'Cross-scope totals do not pretend to be dropdown-subset counts');
    $check($calls === [['count','overdue'],['count','upcoming'],['items','overdue',2000]], 'Only overdue actions fetched, even with combined filters');
    $check($filters['paging']['page'] === 1 && $filters['total']===2 && count($filters['items'])===2, 'Combined type/action filters applied before 10-row pagination');
    $check(str_contains($filtered, 'href="/admin/scheduled-content?scope=upcoming&amp;type=post&amp;action=unpublish"') && str_contains($filtered, 'href="/admin/scheduled-content?scope=overdue&amp;type=post&amp;action=unpublish"'), 'Scope links preserve both dropdown selections, omit page');
    $check(str_contains($filtered, 'name="scope" value="overdue"') && str_contains($filtered, '<option value="post" selected') && str_contains($filtered, '<option value="unpublish" selected'), 'GET form retains full selected state');
    [$empty, $emptyParams] = $render(['scope'=>'overdue','type'=>'doesnotexist','action'=>'publish']);
    $check($emptyParams['total']===0 && str_contains($empty, 'admin-empty-state') && str_contains($empty, '<nav class="admin-filter-tabs"'), 'Filtered empty state retains Core tabs and the recovery form');
    [$invalid, $normalized] = $render(['scope'=>'bogus','type'=>'../../bad','action'=>'drop','page'=>'-20']);
    $check($normalized['scope']==='upcoming' && $normalized['type']==='' && $normalized['action']==='' && $normalized['paging']['page']===1, 'Invalid scope/type/action/page normalized by original controller');
    ScheduledContent::$records['upcoming'][0]['title'] = '<img src=x onerror=alert(1)>';
    [$hostile] = $render(['scope'=>'upcoming']);
    $check(!str_contains($hostile, '<img src=x onerror') && str_contains($hostile, '&lt;img src=x onerror=alert(1)&gt;'), 'Untrusted title escaped in table and Core action labels');
    ScheduledContent::$records['upcoming'][0] = $makeItem(1, 'upcoming');
    ScheduledContent::$failOn = ['overdue'];
    [$broken, $brokenData] = $render(['scope'=>'upcoming']);
    $check($brokenData['loadError'] === true && str_contains($broken, 'role="alert"') && !str_contains($broken, '<table'), 'Provider counting error shows unavailable, never a false empty or partial table');
    ScheduledContent::$failOn = [];
    ScheduledContent::$counts['upcoming'] = 2001;
    ScheduledContent::$records['upcoming'] = [];
    for ($i = 1; $i <= 2000; $i++) ScheduledContent::$records['upcoming'][] = $makeItem($i, 'upcoming');
    [$bounded, $boundedData, $boundedCalls] = $render(['scope'=>'upcoming']);
    $check($boundedData['truncated']===true && $boundedData['total']===2000 && $boundedData['scopeCounts']['upcoming']===2001, 'Truncation warns while Core count remains the registry full action count');
    $check(in_array(['items','upcoming',2000], $boundedCalls, true) && str_contains($bounded, 'admin-notice--error'), 'Original 2000-item cap and warning remain visible');
    // Audit must distinguish accepted intentional submit from a removed signature.
    $script = $root . '/tests/audit/admin_ui_consistency.py';
    $auditJson = shell_exec('python3 '.escapeshellarg($script).' --json');
    $audit = is_string($auditJson) ? json_decode($auditJson,true) : null;
    $states = [];
    foreach (($audit['findings'] ?? []) as $finding) $states[$finding['id']] = $finding['state'];
    $check(($states['UI-06'] ?? '') === 'not_detected' && ($states['UI-09'] ?? '') === 'not_detected', 'Scheduled row and tabs legacy signatures no longer detected');
    // z20p's historical test may run on a later approved UI release; Forms' UI-12 is
    // closed in z20q while the scheduled no-JS filter exception remains accepted.
    $formsLocalized = str_contains((string)file_get_contents($root . '/modules/forms/admin/views/partials/forms-list.php'), "t('forms:forms_page_builder_component')");
    $check(($states['UI-10'] ?? '') === 'accepted'
        && ($states['UI-12'] ?? '') === ($formsLocalized ? 'not_detected' : 'open')
        && ($audit['open_count'] ?? -1) === ($formsLocalized ? 0 : 1),
        'Intentional no-JS filter acceptance remains distinct from Forms localization status');
    if (in_array('--fixture', $argv, true)) {
        [$sample] = $render(['scope'=>'upcoming']);
        $css = (string)file_get_contents($root . '/public_html/admin/assets/css/admin.css');
        $fixture = '<!doctype html><html lang="da"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>' . $css . '</style></head><body class="nf-admin"><main class="admin-main" style="max-width:1450px;margin:auto">' . $sample . '</main></body></html>';
        $dest = sys_get_temp_dir() . '/cms-scheduled-z20p-fixture.html';
        file_put_contents($dest, $fixture);
        echo "Scheduled Content browser fixture: $dest\n";
    }
} finally { @unlink($tmpController); @unlink($tmpView); }
printf("Scheduled Content Core UI Alignment z20p: %d PASS / %d FAIL\n", $pass, $fail);
exit($fail ? 1 : 0);
