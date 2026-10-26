<?php

declare(strict_types=1);
/** z20q: Forms list subtitle uses the installed module's five-language Core Translator. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';

$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $description) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $description . "\n";
    $ok ? ++$pass : ++$fail;
};

Translator::boot('en');
Translator::registerModule('forms', $root . '/modules/forms');
function t(string $key, array $parameters = []): string { return Translator::get($key, $parameters); }
function admin_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_icon(string $name, string $class = ''): string { return AdminIcons::render($name, $class); }
function admin_csrf_token(): string { return 'z20q-csrf-token'; }
function admin_render_pagination(int $page, int $pages, callable $url, string $label): void {
    // Test retained query contract without replacing real Core pagination.
    $GLOBALS['z20q_paging'] = [$page, $pages, $url(2), $label];
}
function admin_crud_pagination(int $total): array { return ['page'=>1,'pages'=>1,'from'=>$total ? 1 : 0,'to'=>$total,'offset'=>0]; }

$view = $root . '/modules/forms/admin/views/partials/forms-list.php';
$source = (string)file_get_contents($view);
$catalog = json_decode((string)file_get_contents($root . '/modules/forms/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
$existingCatalogKeys = array_keys($catalog);
$check(str_contains($source, "admin_e(t('forms:forms_page_builder_component'))"), 'Subtitle uses namespaced, HTML-escaped module translation');
$check(!str_contains($source, 'Page Builder-komponent: Forms'), 'Hard-coded Danish subtitle removed from the real view');
$check(str_contains($source, ' · <?= $id; ?>'), 'Original numeric form ID suffix retained');
$check(str_contains($source, 'data-admin-live-list') && str_contains($source, 'data-admin-live-search'), 'Original live list and search hooks unchanged');
$check(str_contains($source, 'AdminListUI::filterTabs(') && str_contains($source, 'AdminListUI::actionGroup('), 'Existing shared Core filter tabs and row actions retained');
$check(str_contains($source, 'AdminDeleteDialog::render(') && str_contains($source, "'confirm_delete' => 'permanent'"), 'Existing shared Core delete confirmation retained');
$check(!str_contains($source, 'data-forms-edit-form='), 'No unexpected builder or editor hook introduced');
$check(isset($catalog['forms_page_builder_component']) && count($catalog['forms_page_builder_component']) === 5, 'Single translation key has exactly five supported locales');
$check(count(array_filter($existingCatalogKeys, static fn($key) => $key === 'forms_page_builder_component')) === 1, 'Translation key is unique in the parsed catalog');

$render = static function (array $records, string $locale = 'en') use ($view): string {
    Translator::setLocale($locale);
    $forms = $records;
    $deleteDependenciesById = [34 => ['submissions'=>1,'pages'=>0], 35 => ['submissions'=>0,'pages'=>0]];
    $search = '<query>&test';
    $status = 'active';
    $filterCounts = ['' => 2, 'active' => 1, 'inactive' => 1];
    $page = 1; $pages = 3; $total = count($records); $from = $total ? 1 : 0; $to = $total;
    $notice = null;
    ob_start();
    try { require $view; return (string)ob_get_clean(); }
    catch (Throwable $error) { ob_end_clean(); throw $error; }
};

$sample = [
    ['id'=>34,'name'=>'<script>alert("x&y")</script>', 'status'=>'active', 'field_count'=>2, 'submission_count'=>1, 'new_submission_count'=>0, 'updated_at'=>'2026-10-18 10:00:00'],
    ['id'=>35,'name'=>'Anden formular', 'status'=>'inactive', 'field_count'=>0, 'submission_count'=>0, 'new_submission_count'=>0, 'updated_at'=>null],
];
$expected = [
    'da' => 'Page Builder-komponent: Forms',
    'en' => 'Page Builder component: Forms',
    'de' => 'Page-Builder-Komponente: Formulare',
    'sv' => 'Page Builder-komponent: Formulär',
    'nb' => 'Page Builder-komponent: Skjemaer',
];
foreach ($expected as $locale => $label) {
    $check(($catalog['forms_page_builder_component'][$locale] ?? null) === $label,
        "Exact localized catalog label: $locale");
    $html = $render($sample, $locale);
    $check(Translator::get('forms:forms_page_builder_component') === $label,
        "Real Core Translator resolves current locale: $locale");
    $check(str_contains($html, 'admin-table-secondary">' . admin_e($label) . ' · 34</span>')
        && str_contains($html, 'admin-table-secondary">' . admin_e($label) . ' · 35</span>'),
        "Both real table subtitles use translated label and unchanged IDs: $locale");
    $check(substr_count($html, 'admin-table-secondary') === 2
        && substr_count($html, 'role="group"') === 2
        && substr_count($html, 'admin-icon-action__icon') === 4,
        "Original row geometry and SVG actions retained: $locale");
    $check(str_contains($html, '&lt;script&gt;alert(&quot;x&amp;y&quot;)&lt;/script&gt;')
        && !str_contains($html, '<script>alert('),
        "Untrusted form titles remain safely HTML-escaped: $locale");
    $check(str_contains($html, 'href="/admin/forms?screen=edit&amp;id=34"')
        && str_contains($html, 'href="/admin/forms?screen=edit&amp;id=35"'),
        "Original GET editor destinations unchanged: $locale");
    $check(substr_count($html, 'data-forms-list-delete-id=') === 2
        && str_contains($html, 'data-forms-list-delete-id="34"')
        && str_contains($html, 'disabled')
        && str_contains($html, 'data-forms-list-delete-id="35"'),
        "Existing dependency-aware delete actions unchanged: $locale");
    $check(str_contains($html, 'name="csrf_token" value="z20q-csrf-token"')
        && str_contains($html, 'method="post" action="/admin/forms?screen=edit"')
        && str_contains($html, 'name="confirm_delete" value="permanent"'),
        "Core delete modal remains CSRF-protected POST: $locale");
    $check(str_contains($html, 'data-admin-live-search')
        && str_contains($html, 'name="q" value="&lt;query&gt;&amp;test"')
        && substr_count($html, 'class="admin-filter-tabs"') === 1,
        "Search and filter navigation remain correctly escaped: $locale");
    $pagination = $GLOBALS['z20q_paging'] ?? [];
    $check(str_contains((string)($pagination[2] ?? ''), 'status=active')
        && str_contains((string)($pagination[2] ?? ''), 'q=%3Cquery%3E%26test')
        && str_contains((string)($pagination[2] ?? ''), 'page=2'),
        "Pagination keeps search and status parameters: $locale");
    $empty = $render([], $locale);
    $check(str_contains($empty, 'admin-empty-state')
        && !str_contains($empty, 'admin-table-secondary')
        && !str_contains($empty, 'role="group"'),
        "Empty state is unchanged and has no fictional translated row: $locale");
}

$check(file_get_contents($root . '/public_html/admin/assets/scss/admin.scss') === file_get_contents($root . '/public_html/admin/assets/css/admin.css'), 'Core SCSS and CSS remain byte-identical');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", (string)file_get_contents($root . '/public_html/incl/init.php'), $version) === 1 && version_compare($version[1], '2.4.4.105', '>='), 'Release retains Forms localization introduced in 2.4.4.105');

printf("Forms Localization Alignment z20q: %d PASS / %d FAIL\n", $pass, $fail);
exit($fail ? 1 : 0);
