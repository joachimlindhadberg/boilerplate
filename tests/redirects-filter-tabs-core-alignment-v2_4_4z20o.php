<?php
/** z20o: Core Redirects tabs, server-authoritative scoped counts, and unchanged actions. No DB writes. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
// Mock only the model's public read contract, not the real list controller or Core UI.
class Redirects {
    public static array $records = [];
    public static array $counts = [];
    public static array $gets = [];
    private function filtered(string $search, string $status): array {
        return array_values(array_filter(self::$records, static function (array $record) use ($search, $status): bool {
            $matches = match ($status) {
                'active' => (bool)$record['is_active'],
                'inactive' => !(bool)$record['is_active'],
                'automatic' => (bool)$record['is_automatic'],
                default => true,
            };
            if (!$matches || $search === '') return $matches;
            foreach (['source_path', 'target_url', 'page_title'] as $field) {
                if (str_contains(strtolower((string)($record[$field] ?? '')), strtolower($search))) return true;
            }
            return false;
        }));
    }
    public function countFiltered(string $search = '', string $status = ''): int {
        self::$counts[] = [$search, $status];
        return count($this->filtered($search, $status));
    }
    public function getFiltered(string $search = '', string $status = '', int $limit = 20, int $offset = 0): array {
        self::$gets[] = [$search, $status, $limit, $offset];
        return array_slice($this->filtered($search, $status), $offset, $limit);
    }
}
require_once $root . '/public_html/admin/incl/functions.php';
// Initialize the synthetic CLI session before emitting test output.
admin_ensure_session();
$_SESSION['admin_csrf'] = 'synthetic-z20o-csrf';
$dictionary = json_decode((string)file_get_contents($root . '/core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
$GLOBALS['redirects_z20o_locale'] = 'da';
function t(string $key, array $params = []): string {
    global $dictionary;
    $s = (string)($dictionary[$key][$GLOBALS['redirects_z20o_locale']] ?? $key);
    foreach ($params as $keyPart => $value) $s = str_replace('{' . $keyPart . '}', (string)$value, $s);
    return $s;
}
$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $name) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . "\n";
    $ok ? ++$pass : ++$fail;
};
$mk = static fn (int $id, int $active, int $automatic, string $source, string $target, string $pageTitle = ''): array => [
    'id' => $id, 'source_path' => $source, 'target_url' => $target,
    'page_title' => $pageTitle, 'redirect_type' => 301, 'is_active' => $active,
    'is_automatic' => $automatic, 'hit_count' => 0, 'last_hit_at' => null,
];
// 27 matched results: 12 active manual, 10 inactive manual, 5 automatic (3 active / 2 inactive).
for ($i = 1; $i <= 27; $i++) {
    $automatic = $i >= 23 ? 1 : 0;
    $active = $i <= 12 || ($i >= 23 && $i <= 25) ? 1 : 0;
    Redirects::$records[] = $mk($i, $active, $automatic, '/promo&-' . $i, '/dest-' . $i, 'Campaign ' . $i);
}
Redirects::$records[] = $mk(28, 1, 0, '/other-page', '/other-dest');
Redirects::$records[] = $mk(29, 0, 1, '/<script>alert(1)</script>', '/hostile-dest');
$src = (string)file_get_contents($root . '/public_html/admin/views/tools/redirects.php');
$functions = (string)file_get_contents($root . '/public_html/admin/incl/functions.php');
$model = (string)file_get_contents($root . '/core/classes/Redirects.php');
$js = (string)file_get_contents($root . '/public_html/admin/assets/js/admin-live-search.js');
$toolsController = (string)file_get_contents($root . '/public_html/admin/tools.php');
$check(substr_count($src, 'AdminListUI::filterTabs(') === 1, 'Redirects delegates exactly one filter navigation to Core');
$check(!str_contains($src, '<div class="admin-filter-tabs"') && !str_contains($src, '<nav class="admin-filter-tabs"'), 'Old handwritten Redirects tabs removed');
$check(str_contains($src, "'value' => 'active'") && str_contains($src, "'value' => 'inactive'") && str_contains($src, "'value' => 'automatic'") && str_contains($src, "'value' => ''"), 'All four original status filters retained');
$check(str_contains($src, "t('ui.filter_redirects')"), 'Existing translated Redirects filter navigation label retained');
$check(str_contains($functions, "'active' => \$redirects->countFiltered(\$search, 'active')") && str_contains($functions, "'inactive' => \$redirects->countFiltered(\$search, 'inactive')") && str_contains($functions, "'automatic' => \$redirects->countFiltered(\$search, 'automatic')"), 'Status totals come from existing filtered model COUNT, not current page rows');
$check(str_contains($functions, '$total = $counts[$status !== \'\' ? $status : \'all\'];'), 'Chosen status count reused for real list pagination');
$check(str_contains($model, "'r.is_active = 1'") && str_contains($model, "'r.is_active = 0'") && str_contains($model, "'r.is_automatic = 1'"), 'Original SQL status filters remain distinct');
$check(str_contains($src, 'data-admin-live-list') && str_contains($src, 'data-admin-live-search'), 'Live-list and search DOM hooks remain');
$check(str_contains($js, "event.target.closest('.admin-filter-tabs a, .admin-pagination a')") && str_contains($js, 'oldTabs.replaceChildren'), 'Core JS still handles live tab navigation and count updates');
$check(str_contains($toolsController, "define('ADMIN_REQUIRED_CAPABILITY', 'tools.manage')"), 'Existing Redirects capability stays in place');
$check(substr_count($src, 'AdminDeleteDialog::render(') === 1 && str_contains($src, "'csrfToken' => admin_csrf_token()"), 'Existing one POST/CSRF Core delete dialog remains');
$check(str_contains($src, 'data-redirect-delete-id') && str_contains($src, 'data-redirect-delete-source'), 'Row-level delete intent survives live-search updates');
$check(str_contains($src, 'name="source_path"') && str_contains($src, 'name="target_url"') && str_contains($src, 'name="is_active"'), 'Create/edit fields untouched');
$check(file_get_contents($root.'/public_html/admin/assets/scss/admin.scss') === file_get_contents($root.'/public_html/admin/assets/css/admin.css'), 'Admin SCSS/CSS byte-identical');
$check(version_compare((string)preg_replace('/.*BOILERPLATE_VERSION\x27, \x27([0-9.]+)\x27.*/s', '$1', (string)file_get_contents($root.'/public_html/incl/init.php')), '2.4.4.103', '>='), 'CMS version at least 2.4.4.103');
$check(is_file($root.'/docs/releases/REDIRECTS-FILTER-TABS-CORE-ALIGNMENT-V2.4.4z20o.md'), 'z20o release notes shipped');

// Render the real Redirects view's HTML while omitting only the existing outer shell.
$layout = "require __DIR__ . '/../partials/layout.php';";
$check(substr_count($src, $layout) === 1, 'Shared outer admin layout kept unchanged');
$body = str_replace($layout, 'echo $bodyHtml;', $src);
$tmp = tempnam(sys_get_temp_dir(), 'redirects-z20o-');
$check($tmp !== false, 'Isolated CLI rendering fixture created');
if ($tmp === false) exit(1);
try {
    file_put_contents($tmp, $body);
    $render = static function (array $request, string $locale = 'da') use ($tmp): array {
        $_GET = $request;
        $GLOBALS['redirects_z20o_locale'] = $locale;
        Redirects::$counts = []; Redirects::$gets = [];
        $redirectsData = admin_redirects_data();
        $form = []; $notice = null; $errors = []; $editRecord = null;
        ob_start();
        try { require $tmp; $html = (string)ob_get_clean(); }
        catch (Throwable $e) { ob_end_clean(); throw $e; }
        return [$html, $redirectsData, Redirects::$counts, Redirects::$gets];
    };
    $expectedCounts = ['all' => 27, 'active' => 15, 'inactive' => 12, 'automatic' => 5];
    foreach (['da', 'en', 'de', 'sv', 'nb'] as $locale) {
        foreach (['Alle', 'ui.filter_redirects', 'redirects.filter_active', 'redirects.filter_inactive', 'redirects.filter_automatic'] as $key) {
            $check(is_string($dictionary[$key][$locale] ?? null) && trim((string)$dictionary[$key][$locale]) !== '', "$key is localized in $locale");
        }
        [$html, $data, $countCalls, $listCalls] = $render(['q' => 'promo&', 'status' => 'active', 'page' => '2'], $locale);
        $check($data['counts'] === $expectedCounts, "Search-scoped counts are accurate in $locale");
        $check($data['total'] === 15 && $data['page'] === 2 && $data['pages'] === 2 && $data['from'] === 11 && $data['to'] === 15, "Existing 10-row pagination retains the selected status in $locale");
        $check($countCalls === [['promo&', ''], ['promo&', 'active'], ['promo&', 'inactive'], ['promo&', 'automatic']], "Exactly four distinct read-only COUNT calls with the active search in $locale");
        $check($listCalls === [['promo&', 'active', 10, 10]], "Exactly one paged Redirects row query in $locale");
        $check(substr_count($html, '<nav class="admin-filter-tabs"') === 1 && substr_count($html, 'aria-current="page"') === 1, "One semantic Core tab group with a single selected tab in $locale");
        $check(str_contains($html, 'aria-label="'.admin_e(t('ui.filter_redirects')).'"'), "Core nav accessible label translated in $locale");
        $check(str_contains($html, '<strong>27</strong>') && str_contains($html, '<strong>15</strong>') && str_contains($html, '<strong>12</strong>') && str_contains($html, '<strong>5</strong>'), "Core exposes correctly counted subsets in $locale");
        $check(str_contains($html, 'href="/admin/vaerktoejer/redirects?q=promo%26&amp;status=active" class="is-active" aria-current="page"'), "Selected tab keeps search and resets page in $locale");
        $check(str_contains($html, 'href="/admin/vaerktoejer/redirects?q=promo%26"'), "All tab clears status, preserves search and resets page in $locale");
        $check(str_contains($html, 'href="/admin/vaerktoejer/redirects?q=promo%26&amp;status=inactive"') && str_contains($html, 'href="/admin/vaerktoejer/redirects?q=promo%26&amp;status=automatic"'), "Other tabs use exact existing URLs in $locale");
        $check(str_contains($html, 'name="status" value="active"') && str_contains($html, 'name="q" value="promo&amp;"'), "GET search retains status and escapes query in $locale");
        $check(str_contains($html, 'class="admin-pagination"') && str_contains($html, '/admin/vaerktoejer/redirects?q=promo%26&amp;status=active'), "Pagination retains search/status in $locale");
        $check(substr_count($html, 'data-redirect-delete-id="') === 5 && substr_count($html, 'id="redirect-list-delete-dialog"') === 1, "Five rows and exactly one unchanged delete modal in $locale");
    }
    [$all, $allData] = $render([]);
    $check($allData['counts'] === ['all' => 29, 'active' => 16, 'inactive' => 13, 'automatic' => 6] && $allData['total'] === 29, 'Unfiltered counts include all redirects, including inactive and automatic overlap');
    $check(str_contains($all, 'href="/admin/vaerktoejer/redirects" class="is-active" aria-current="page"'), 'All tab is active with original clean URL');
    [$auto, $autoData] = $render(['q'=>'promo&', 'status'=>'automatic', 'page'=>'77']);
    $check($autoData['page'] === 1 && $autoData['total'] === 5 && count($autoData['items']) === 5 && $autoData['counts']['all'] === 27, 'Automatic subset count and out-of-range page clamp correct');
    $check(str_contains($auto, 'href="/admin/vaerktoejer/redirects?q=promo%26&amp;status=automatic" class="is-active"'), 'Automatic tab selection matches previous status semantics');
    [$unknown, $unknownData] = $render(['q' => 'promo&', 'status'=>'untrusted', 'page'=>'1']);
    $check($unknownData['status'] === '' && $unknownData['total'] === 27 && substr_count($unknown, 'aria-current="page"') === 1, 'Invalid status normalizes to All without creating a fake tab');
    [$empty, $emptyData] = $render(['q'=>'nonesuch', 'status'=>'inactive']);
    $check($emptyData['counts'] === ['all'=>0,'active'=>0,'inactive'=>0,'automatic'=>0] && str_contains($empty, 'admin-empty-state') && str_contains($empty, '<nav class="admin-filter-tabs"'), 'Empty results preserve accessible tabs and search recovery');
    [$hostile, $hostileData] = $render(['q'=>'<script>alert(1)</script>', 'status'=>'automatic']);
    $check($hostileData['total'] === 1 && str_contains($hostile, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($hostile, '<script>alert(1)</script>'), 'Malicious user input in query and matching redirect cannot inject executable HTML');
    [$testEdit, $testEditData] = $render(['status'=>'active', 'edit'=>'8']);
    $check(str_contains($testEdit, 'name="action" value="save_redirect"') && str_contains($testEdit, 'name="csrf_token" value="synthetic-z20o-csrf"'), 'Original edit/create POST form remains valid');
    if (in_array('--fixture', $argv, true)) {
        [$fixture] = $render(['q'=>'promo&', 'status'=>'active', 'page'=>'1']);
        $css = (string)file_get_contents($root.'/public_html/admin/assets/css/admin.css');
        $doc = '<!doctype html><html lang="da"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.$css.'</style></head>'
             . '<body class="nf-admin"><main style="max-width:1450px;margin:auto;padding:24px">'.$fixture.'</main></body></html>';
        $output = sys_get_temp_dir().'/cms-redirects-z20o-fixture.html';
        file_put_contents($output, $doc);
        echo "Redirects browser fixture: $output\n";
    }
} finally { @unlink($tmp); }
printf("Redirects Filter Tabs Core Alignment z20o: %d PASS / %d FAIL\n", $pass, $fail);
exit($fail ? 1 : 0);
