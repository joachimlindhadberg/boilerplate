<?php

declare(strict_types=1);

/** z10: Pages list consumes the already-approved Core list API without changing its behaviour. */
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
    $s = (string)($dictionary[$key]['da'] ?? $key);
    foreach ($params as $name => $value) $s = str_replace('{' . $name . '}', (string)$value, $s);
    return $s;
}
function admin_e(mixed $value): string { return AdminEscaper::text($value); }
function admin_icon(string $name, string $css = ''): string { return AdminIcons::render($name, $css); }
function admin_pages_url(array $changes = []): string {
    $params = [
        'status' => trim((string)($_GET['status'] ?? '')),
        'q' => trim((string)($_GET['q'] ?? '')),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
    ];
    foreach ($changes as $key => $value) {
        if ($value === null || $value === '') unset($params[$key]);
        else $params[$key] = $value;
    }
    if (($params['page'] ?? 1) <= 1) unset($params['page']);
    if (($params['status'] ?? '') === '') unset($params['status']);
    if (($params['q'] ?? '') === '') unset($params['q']);
    $qs = http_build_query($params);
    return '/admin/sider' . ($qs !== '' ? '?' . $qs : '');
}
function admin_page_public_url(array $row): string { return '/' . trim((string)($row['page_slug'] ?? ''), '/'); }
function admin_page_status_class(string $status): string { return $status; }
function admin_page_status_label(string $status): string { return $status; }
function admin_render_pagination(int $page, int $pages, callable $url, string $name): void {
    if ($pages > 1) echo '<a class="test-page-link" href="' . admin_e($url(2)) . '">2</a>';
}
function admin_csrf_token(): string { return 'safe-synthetic-z10-token'; }
class Pages { public static function isSystemSlug(string $slug): bool { return $slug === 'forside'; } }
class PageScheduler { public static function localLabel(?string $value): string { return $value ?? ''; } }
$view = $root . '/public_html/admin/views/pages/index.php';
$source = (string)file_get_contents($view);
$check(str_contains($source, 'AdminListUI::filterTabs('), 'Pages uses Core status filter tabs');
$check(str_contains($source, 'AdminListUI::actionGroup('), 'Pages uses Core row action groups');
$check(!str_contains($source, 'admin_icon_action_link(') && !str_contains($source, 'admin_icon_action_button('), 'Per-list action helpers removed in favour of Core list contract');
$check(str_contains($source, 'data-admin-live-list') && str_contains($source, 'data-admin-live-search'), 'Live search hooks preserved');
$check(str_contains($source, 'admin_render_pagination(') && str_contains($source, 'admin_pages_url('), 'Existing pagination and URLs remain server-owned');
$check(str_contains($source, 'name="status"') && str_contains($source, 'name="q"'), 'Status/search GET inputs preserved');
$check(str_contains($source, "'id' => 'page-list-delete-dialog'") && str_contains($source, "'csrfToken' => admin_csrf_token()") && str_contains($source, 'AdminDeleteDialog::render('), 'Single POST confirmation modal with CSRF retained');
$check(str_contains($source, "'disabled' => !\$canDelete"), 'Server-derived delete restrictions passed to Core');
$check(str_contains($source, "'tone' => 'danger'"), 'Destructive action uses Core danger style');
$check(str_contains($source, 'data-page-delete-id') && str_contains($source, 'data-page-delete-title'), 'Existing delegated modal attributes preserved');
$footer = "require __DIR__ . '/../partials/layout.php';";
$check(substr_count($source, $footer) === 1, 'Shared Core page layout remains unchanged');
$temporary = tempnam(sys_get_temp_dir(), 'pages-z10-');
try {
    file_put_contents($temporary, str_replace($footer, 'echo $bodyHtml;', $source));
    $render = static function (array $records, string $status = 'draft', string $search = 'M&V', int $page = 2) use ($temporary): string {
        $_GET = ['status' => $status, 'q' => $search, 'page' => $page];
        $pagesData = [
            'items' => $records,
            'counts' => ['all'=>12,'published'=>6,'draft'=>4,'protected'=>2],
            'search' => $search, 'status' => $status, 'page' => $page,
            'pages' => 4, 'total'=>12, 'from'=>6, 'to'=>10,
        ];
        $notice = null;
        ob_start(); include $temporary; return (string)ob_get_clean();
    };
    $normal = ['id'=>7,'page_title'=>'Om <team>','page_slug'=>'om-team','page_status'=>'draft','content_mode'=>'builder','has_children'=>0];
    $system = ['id'=>8,'page_title'=>'Forside','page_slug'=>'forside','page_status'=>'published','content_mode'=>'builder','has_children'=>0];
    $parent = ['id'=>9,'page_title'=>'Overordnet','page_slug'=>'overordnet','page_status'=>'protected','content_mode'=>'legacy','has_children'=>1];
    $html = $render([$normal, $system, $parent]);
    $check(str_contains($html, '<nav class="admin-filter-tabs"'), 'Semantic Core filter navigation rendered');
    $check(substr_count($html, 'aria-current="page"')===1 && substr_count($html, 'class="is-active"')===1, 'Exactly one active status tab');
    $check(str_contains($html, 'aria-label="' . admin_e(t('ui.filter_pages_status')) . '"'), 'Status tabs retain localized accessible name');
    $check(str_contains($html, '<strong>12</strong>') && str_contains($html, '<strong>6</strong>') && str_contains($html, '<strong>4</strong>') && str_contains($html, '<strong>2</strong>'), 'Core displays unaltered status counts');
    $check(str_contains($html, 'status=draft') && str_contains($html, 'q=M%26V'), 'Filter URLs keep search query and selected status');
    $check(str_contains($html, 'href="/admin/sider?status=draft&amp;q=M%26V&amp;page=2"'), 'Pagination keeps search and active tab');
    $check(substr_count($html, 'admin-table-actions--icons')===3, 'One compact Core action group per row');
    $check(preg_match_all('/<(?:a|button)\\b[^>]*class="admin-icon-action(?:\\s|")/', $html)===9, 'All three original actions on each row');
    $check(str_contains($html, 'href="/admin/sider?mode=view&amp;id=7"') && str_contains($html, 'href="/admin/sider?mode=edit&amp;id=7"'), 'Existing view/edit endpoints unchanged');
    $check(substr_count($html, 'data-admin-open-delete="page-list-delete-dialog"')===3, 'Delete triggers still target shared modal');
    $check(str_contains($html, 'data-page-delete-title="Om &lt;team&gt;"') && !str_contains($html, '<team>'), 'User-supplied titles are safely escaped');
    $check(substr_count($html, ' disabled')===2, 'System page and parent page cannot be deleted');
    $check(str_contains($html, 'name="csrf_token" value="safe-synthetic-z10-token"'), 'Existing CSRF helper controls POST confirmation');
    $check(strpos($html, 'id="page-list-delete-dialog"') > strpos($html, '</section>'), 'Modal is outside dynamic live-list replacement');
    $check(str_contains($html, 'class="test-page-link"'), 'Pagination still rendered for multiple pages');
    $empty = $render([]);
    $check(str_contains($empty, 'admin-empty-state') && str_contains($empty, 'data-admin-live-search'), 'Empty search retains recovery path');
    $check(!str_contains($empty, 'admin-table-actions--icons') && substr_count($empty, 'id="page-list-delete-dialog"')===1, 'Empty view has no fictitious actions and one modal');
    $unfiltered = $render([$normal], '', '', 1);
    $check(!str_contains($unfiltered, 'aria-current="page"') ? false : str_contains($unfiltered, 'href="/admin/sider" class="is-active" aria-current="page"'), 'All tab is selected on unfiltered listing');
    if (in_array('--fixture', $argv, true)) {
        $css = (string)file_get_contents($root . '/public_html/admin/assets/css/admin.css');
        $fixture = '<!doctype html><html lang="da"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.$css.'</style></head><body class="nf-admin"><div style="max-width:1360px;margin:auto;padding:24px">'.$html.'</div></body></html>';
        file_put_contents(sys_get_temp_dir().'/cms-pages-z10-fixture.html', $fixture);
    }
} finally { @unlink($temporary); }
$js = (string)file_get_contents($root . '/public_html/admin/assets/js/admin-live-search.js');
$check(str_contains($js, "querySelector('.admin-filter-tabs')") && str_contains($js, 'oldTabs.replaceChildren'), 'Existing live search refreshes the shared Core tab DOM');
$check(str_contains($js, "event.target.closest('.admin-filter-tabs a, .admin-pagination a')"), 'Live search tab/pagination handler remains active');
$check((string)file_get_contents($root . '/public_html/admin/assets/css/admin.css') === (string)file_get_contents($root . '/public_html/admin/assets/scss/admin.scss'), 'Core CSS and SCSS remain synchronized');
$check(!str_contains($source, '<style') && !str_contains($source, 'style='), 'Pages adds no inline module-level table styles');
$version = (string)file_get_contents($root . '/public_html/incl/init.php');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", $version, $releaseMatch) === 1 && version_compare($releaseMatch[1], '2.4.4.63', '>='), 'Version retains z10 compatibility (>= 2.4.4.63)');
echo "Core Pages List Alignment z10: $pass PASS / $fail FAIL\n";
exit($fail ? 1 : 0);
