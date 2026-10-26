<?php
/** V2.4.4y: public HTML contract and legacy-grid compatibility (no DB needed). */
require_once dirname(__DIR__) . '/core/classes/PageBlocks.php';
require_once dirname(__DIR__) . '/public_html/incl/helpers/page_builder_renderer.php';

$passes = 0;
function test_y(bool $ok, string $title): void
{
    global $passes;
    if (!$ok) { fwrite(STDERR, "FAIL: {$title}\n"); exit(1); }
    $passes++;
    echo "PASS: {$title}\n";
}
function render_y(string $type, array $data): string
{
    ob_start();
    render_page_builder_block(['block_type'=>$type, 'data'=>$data, 'status'=>'active']);
    return ob_get_clean();
}
function normalized_y(string $type, array $data): array
{
    $out = PageBlocks::normalizeSubmitted([['type'=>$type,'status'=>'active','data'=>$data]]);
    test_y($out['errors'] === [], "{$type} data normalizes successfully");
    return $out['blocks'][0]['data'];
}

$single = normalized_y('text', ['section_id'=>'section-123456', 'title'=>'Hej <test>', 'body'=>"First\n\nSecond", 'layout'=>'stacked', 'theme'=>'dark']);
$html = render_y('text',$single);
test_y(str_starts_with($html, '<section id="section-123456" class="block-section"'), '100% Row uses persistent ID and class');
test_y(str_contains($html, '<div class="container"><div class="site-grid site-grid--1"><div class="site-grid--item">'), '100% exact wrapper hierarchy');
test_y(substr_count($html, '<section') === 1 && substr_count($html, 'site-grid--item') === 1, '100% Row has exactly one section and one item');
test_y(str_contains($html, 'data-theme="dark"'), 'Stored theme is exposed to frontend Theme');
test_y(str_contains($html, 'Hej &lt;test&gt;') && !str_contains($html, '<test>'), 'Editable content is escaped');
test_y(str_contains($html, '<p>First') && str_contains($html, '<p>Second'), 'Paragraph formatting retained');
test_y(render_y('text', $single) === $html, 'ID and content remain stable across renders');

$two = normalized_y('grid', [
    'section_id'=>'section-987654', 'layout_version'=>'v2', 'column_count'=>2,
    'preset'=>'equal', 'gap'=>'large', 'columns'=>[
        ['blocks'=>[['type'=>'text', 'status'=>'active', 'data'=>['title'=>'Left']]]],
        ['blocks'=>[['type'=>'image', 'status'=>'active', 'data'=>['image_path'=>'/uploads/photo.jpg', 'image_alt'=>'Photo']]]],
    ],
]);
$html = render_y('grid',$two);
test_y(str_contains($html, '<section id="section-987654" class="block-section"'), '50/50 Row has stable anchor ID');
test_y(str_contains($html, '<div class="container"><div class="site-grid site-grid--2"'), '50/50 has exact structural wrapper');
test_y(substr_count($html, '<div class="site-grid--item">') === 2, '50/50 exactly two column wrappers');
test_y(substr_count($html, '<section') === 1, '50/50 has precisely one outer section');
test_y(str_contains($html, 'Left') && str_contains($html, '/uploads/photo.jpg'), 'Both independent column blocks render');
test_y(str_contains($html, 'data-gap="large"'), 'Theme owns configured grid gap');

$empty = normalized_y('grid', ['layout_version'=>'v2','column_count'=>2,'preset'=>'equal','columns'=>[]]);
$html = render_y('grid',$empty);
test_y(substr_count($html,'<div class="site-grid--item">') === 2, 'Empty 50/50 persists two wrappers');
test_y(preg_match('~^<section id="section-[0-9]{6}" class="block-section"~', $html) === 1, 'Newly saved empty 50/50 receives stable ID');

$legacy = normalized_y('grid', ['section_id'=>'section-445566', 'column_count'=>3, 'preset'=>'equal','columns'=>[['blocks'=>[]],['blocks'=>[]],['blocks'=>[]]]]);
$html = render_y('grid',$legacy);
test_y(str_contains($html, '<section id="section-445566" class="section theme-light" data-container-width="standard">'), 'Legacy grid keeps old section and stable anchor');
test_y(str_contains($html,'grid--cols-3') && substr_count($html,'grid__column')===3, 'Legacy 3 columns retained');
test_y(!str_contains($html,'site-grid--item'), 'Legacy grid not silently converted to V2');

$unequal=normalized_y('grid',['section_id'=>'section-999999','column_count'=>2,'preset'=>'33-67','columns'=>[]]);
test_y(str_contains(render_y('grid',$unequal),'grid--split-33-67'), 'Legacy unequal columns preserved');

// Content types that formerly rendered their own section are now a single 100%-Row.
foreach (['hero','intro','text_image','cta','component'] as $type) {
    $input=['section_id'=>'section-234567', 'title'=>'Example','body'=>'Text','theme'=>'soft'];
    if ($type==='component') $input['component']='invalid';
    $data=$type === 'component' ? $input : normalized_y($type,$input);
    $output=render_y($type,$data);
    test_y(substr_count($output, '<section')===1 && substr_count($output, 'site-grid--item')===1, "{$type} avoids nested sections and preserves wrapper");
}

$cta=render_y('cta', ['section_id'=>'section-123456', 'button_label'=>'Click', 'button_url'=>'javascript:alert(1)', 'title'=>'CTA']);
test_y(!str_contains($cta,'javascript:') && !str_contains($cta,'href='), 'Disallowed imported link schemes are not output');
$missing=render_y('text',['title'=>'Unsaved legacy']);
test_y(!str_contains($missing, ' id="section-'), 'Renderer never mints an unstable temporary section ID');
$invalid=render_y('text',['section_id'=>'section-<script>', 'title'=>'Unsafe']);
test_y(!str_contains($invalid,'id="section-'), 'Invalid section IDs cannot enter HTML');

$themeScss=file_get_contents(dirname(__DIR__).'/public_html/assets/scss/layout/_layout.site-grid.scss');
$themeCss=file_get_contents(dirname(__DIR__).'/public_html/assets/css/style.css');
test_y(str_contains($themeCss,$themeScss), 'Frontend Theme SCSS and compiled CSS are synchronized');
test_y(!str_contains(file_get_contents(dirname(__DIR__).'/public_html/incl/helpers/page_builder_v2_renderer.php'), 'style='), 'No inline CSS emitted by V2 renderer');

echo "Page Builder V2.4.4y frontend: {$passes} PASS / 0 FAIL\n";
