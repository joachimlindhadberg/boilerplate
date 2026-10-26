<?php
/** z20n: Media type filters consume Core list tabs and keep the existing server-owned search/grid workflow. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/public_html/admin/incl/functions.php';
$passed = 0; $failed = 0;
$check = static function (bool $ok, string $description) use (&$passed, &$failed): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $description . "\n";
    $ok ? ++$passed : ++$failed;
};
$dictionary = json_decode((string)file_get_contents($root . '/core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
$GLOBALS['media_z20n_locale'] = 'da';
function t(string $key, array $params = []): string {
    global $dictionary;
    $s = (string)($dictionary[$key][$GLOBALS['media_z20n_locale']] ?? $key);
    foreach ($params as $name => $value) $s = str_replace('{' . $name . '}', (string)$value, $s);
    return $s;
}
/** In-memory MediaLibrary fake with the same externally observable count/get contract. */
class MediaLibrary {
    public static array $records = [];
    public static array $counts = [];
    public static array $gets = [];
    public static function allowedKinds(): array { return ['image', 'video']; }
    public static function maxUploadBytes(): int { return 104857600; }
    private function filtered(string $search, string $kind): array {
        return array_values(array_filter(self::$records, static fn(array $row): bool =>
            ($kind === '' || $row['media_kind'] === $kind)
            && ($search === '' || str_contains(strtolower($row['title']), strtolower($search)))));
    }
    public function countFiltered(string $search = '', string $kind = ''): int {
        self::$counts[] = [$search, $kind];
        return count($this->filtered($search, $kind));
    }
    public function getFiltered(string $search = '', string $kind = '', int $limit = 24, int $offset = 0): array {
        self::$gets[] = [$search, $kind, $limit, $offset];
        return array_slice($this->filtered($search, $kind), $offset, $limit);
    }
}
for ($i = 1; $i <= 30; ++$i) {
    MediaLibrary::$records[] = [
        'id' => $i, 'title' => 'media& original ' . $i,
        'original_filename' => 'image-' . $i . '.png',
        'media_kind' => 'image', 'file_size' => 2048,
        'public_url' => '/uploads/image-' . $i . '.png',
        'width' => 100, 'height' => 150,
    ];
}
for ($i = 31; $i <= 33; ++$i) {
    MediaLibrary::$records[] = [
        'id' => $i, 'title' => 'media& video ' . $i,
        'original_filename' => 'video-' . $i . '.mp4',
        'media_kind' => 'video', 'file_size' => 4096,
        'public_url' => '/uploads/video-' . $i . '.mp4',
    ];
}
MediaLibrary::$records[] = [
    'id' => 99, 'title' => '<script>alert(1)</script>', 'original_filename' => 'strange.png',
    'media_kind' => 'image', 'file_size' => 32,
    'public_url' => '/uploads/strange.png',
];
$view = $root . '/public_html/admin/views/media/index.php';
$source = (string)file_get_contents($view);
$fnSource = (string)file_get_contents($root . '/public_html/admin/incl/functions.php');
$js = (string)file_get_contents($root . '/public_html/admin/assets/js/admin-live-search.js');
$check(substr_count($source, 'AdminListUI::filterTabs(') === 1, 'Media delegates one filter navigation to Core');
$check(!str_contains($source, '<div class="admin-filter-tabs"') && !str_contains($source, '<nav class="admin-filter-tabs"'), 'Old handwritten Media tabs removed');
$check(str_contains($source, "'value' => 'image'") && str_contains($source, "'value' => 'video'") && str_contains($source, "'value' => ''"), 'Original All/Image/Video type values preserved');
$check(str_contains($source, "t('ui.filter_media_type')"), 'Core tabs use original accessible translated media filter label');
$check(str_contains($fnSource, "'image' => \$media->countFiltered(\$search, 'image')") && str_contains($fnSource, "'video' => \$media->countFiltered(\$search, 'video')"), 'Counts are server-calculated from the same filtered search');
$check(str_contains($source, 'data-admin-live-list') && str_contains($source, 'data-admin-live-search'), 'Core live-list/search hooks preserved');
$check(str_contains($source, 'admin-media-grid') && str_contains($source, 'data-media-item'), 'Existing media grid and detail triggers preserved');
$check(str_contains($source, 'data-media-upload-open') && str_contains($source, 'admin_icon(\'upload\''), 'Core upload icon and dialog opener preserved');
$check(str_contains($source, "require __DIR__ . '/partials/detail-dialog.php'") && str_contains($source, "require __DIR__ . '/partials/delete-dialog.php'"), 'Original detail and delete dialogs retained');
$check(str_contains($js, "querySelector('.admin-filter-tabs')") && str_contains($js, 'oldTabs.replaceChildren'), 'Live search updates Core filter tabs with server output');
$check(str_contains($js, "event.target.closest('.admin-filter-tabs a, .admin-pagination a')"), 'Live filter and pagination navigation remains intercepted by Core');
$check(file_get_contents($root . '/public_html/admin/assets/scss/admin.scss') === file_get_contents($root . '/public_html/admin/assets/css/admin.css'), 'Core SCSS/CSS remain byte-identical');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9.]+)'/", (string)file_get_contents($root . '/public_html/incl/init.php'), $m) === 1 && version_compare($m[1], '2.4.4.102', '>='), 'Release version 2.4.4.102 or newer');
$check(is_file($root.'/docs/releases/MEDIA-FILTER-TABS-CORE-ALIGNMENT-V2.4.4z20n.md'), 'Versioned release notes shipped');
// Render only the real page body; the unmodified dialog partials and admin shell need a live CMS session.
$footer = "require __DIR__ . '/../partials/layout.php';";
$check(substr_count($source, $footer) === 1, 'Shared Core layout is unchanged');
$body = str_replace($footer, 'echo $bodyHtml;', $source);
foreach (["require __DIR__ . '/partials/upload-dialog.php';", "require __DIR__ . '/partials/detail-dialog.php';", "require __DIR__ . '/partials/delete-dialog.php';"] as $partial) {
    $check(substr_count($source, $partial) === 1, 'Original dialog included exactly once: ' . basename($partial));
    $body = str_replace($partial, '', $body);
}
$tmp = tempnam(sys_get_temp_dir(), 'media-z20n-');
try {
    file_put_contents($tmp, $body);
    $render = static function (array $request, string $locale = 'da') use ($tmp): array {
        $_GET = $request;
        $GLOBALS['media_z20n_locale'] = $locale;
        MediaLibrary::$counts = []; MediaLibrary::$gets = [];
        $mediaData = admin_media_data(); $notice = null;
        ob_start();
        try { require $tmp; $html = (string)ob_get_clean(); }
        catch (Throwable $e) { ob_end_clean(); throw $e; }
        return [$html, $mediaData, MediaLibrary::$counts, MediaLibrary::$gets];
    };
    foreach (['da', 'en', 'de', 'sv', 'nb'] as $locale) {
        foreach (['Alle', 'Billeder', 'Videoer', 'ui.filter_media_type'] as $key) {
            $check(isset($dictionary[$key][$locale]) && trim((string)$dictionary[$key][$locale]) !== '', "Translation $key for $locale");
        }
        [$html,$data,$countCalls,$listCalls] = $render(['q' => 'media&', 'type' => 'image', 'page' => '2'], $locale);
        $check($data['counts'] === ['all' => 33, 'image' => 30, 'video' => 3], "Correct searched counts for all three media tabs: $locale");
        $check($data['total'] === 30 && $data['page'] === 2 && $data['pages'] === 2 && $data['from'] === 25 && $data['to'] === 30, "Original 24-item pagination and active type retained: $locale");
        $check($countCalls === [['media&', ''], ['media&', 'image'], ['media&', 'video']], "Exactly three scoped COUNT requests, no separate total query: $locale");
        $check($listCalls === [['media&', 'image', 24, 24]], "Only one filtered media grid query, correct offset: $locale");
        $check(substr_count($html, '<nav class="admin-filter-tabs"') === 1 && substr_count($html, 'aria-current="page"') === 1, "Exactly one semantic Core filter group and active tab: $locale");
        $check(str_contains($html, 'aria-label="'.admin_e(t('ui.filter_media_type')).'"'), "Accessible tab label translated: $locale");
        $check(str_contains($html, '<strong>33</strong>') && str_contains($html, '<strong>30</strong>') && str_contains($html, '<strong>3</strong>'), "Core displays accurate distinct counts: $locale");
        $check(str_contains($html, 'href="/admin/medier?q=media%26&amp;type=image" class="is-active" aria-current="page"'), "Selected image tab retains escaped search query and resets page: $locale");
        $check(str_contains($html, 'href="/admin/medier?q=media%26"'), "All tab clears type without losing search or keeping page: $locale");
        $check(str_contains($html, 'href="/admin/medier?q=media%26&amp;type=video"'), "Video tab preserves query but resets page: $locale");
        $check(str_contains($html, 'class="admin-pagination"') && str_contains($html, 'href="/admin/medier?q=media%26&amp;type=image"'), "Pagination previous retains search and selected media kind on page 2: $locale");
        $check(str_contains($html, 'name="type" value="image"') && str_contains($html, 'name="q" value="media&amp;"'), "Live/non-JS search GET retains selected filter and safely escaped text: $locale");
        $check(substr_count($html, 'data-media-item') === 6, "Six filtered media cards render on page two: $locale");
    }
    [$unfiltered,$allData] = $render(['type'=>'', 'page'=>'1']);
    $check($allData['counts'] === ['all'=>34,'image'=>31,'video'=>3] && $allData['total'] === 34, 'Unfiltered tabs accurately count full real dataset, not only current page');
    $check(str_contains($unfiltered, 'href="/admin/medier" class="is-active" aria-current="page"'), 'All tab selected when no media kind active');
    [$video,$videoData] = $render(['type'=>'video', 'q'=>'media&', 'page'=>'8']);
    $check($videoData['page'] === 1 && $videoData['total'] === 3 && $videoData['counts']['all'] === 33, 'Out-of-range page clamped on kind switch, counts remain global to current search');
    $check(substr_count($video, 'data-media-item') === 3, 'Video grid displays filtered items without image-related changes');
    [$invalid,$invalidData] = $render(['type'=>'script!', 'q'=>'media&', 'page'=>'1']);
    $check($invalidData['kind'] === '' && substr_count($invalid, 'aria-current="page"') === 1 && $invalidData['total'] === 33, 'Unknown type remains normalized to All, with no extra untrusted tab');
    [$empty,$emptyData] = $render(['type'=>'video','q'=>'notpresent']);
    $check($emptyData['counts'] === ['all'=>0,'image'=>0,'video'=>0] && str_contains($empty, 'admin-empty-state') && str_contains($empty, '<nav class="admin-filter-tabs"'), 'Empty results preserve Core tabs and search recovery');
    [$hostile,$hostileData] = $render(['q'=>'<script>alert(1)</script>','type'=>'image']);
    $check($hostileData['total'] === 1 && str_contains($hostile, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($hostile, '<script>alert(1)</script>'), 'Malicious search text and media names are not executable markup');
    if (in_array('--fixture', $argv, true)) {
        [$fixture] = $render(['q'=>'media&', 'type'=>'image', 'page'=>'1']);
        $css = (string)file_get_contents($root.'/public_html/admin/assets/css/admin.css');
        $doc = '<!doctype html><html lang="da"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.$css.'</style></head><body class="nf-admin"><main style="max-width:1450px;padding:24px;margin:auto">'.$fixture.'</main></body></html>';
        file_put_contents(sys_get_temp_dir().'/cms-media-z20n-fixture.html',$doc);
        echo "Media browser fixture: " . sys_get_temp_dir() . "/cms-media-z20n-fixture.html\n";
    }
} finally { @unlink($tmp); }
printf("Media Filter Tabs Core Alignment z20n: %d PASS / %d FAIL\n",$passed,$failed);
exit($failed ? 1 : 0);
