<?php
/** V2.4.4w: test server-side stable section IDs without a DB dependency. */
require_once dirname(__DIR__) . '/core/classes/PageBlocks.php';
function check(bool $condition, string $message): void {
    if (!$condition) {fwrite(STDERR, "FAIL: $message\n"); exit(1);}
    echo "PASS: $message\n";
}
function block(string $type, array $data = [], string $status = 'active'): array {
    return compact('type', 'data', 'status');
}
$source = [
    block('text', ['title' => 'A', 'body' => 'Hello']),
    block('grid', [
        'column_count' => 3,
        'columns' => [
            ['blocks' => [['type' => 'text', 'status' => 'active', 'data' => ['title' => 'Nested']]]],
            ['blocks' => []],
            ['blocks' => []],
        ],
    ]),
    block('cta', ['title' => 'C', 'button_label' => 'See more', 'button_url' => '/contact'], 'hidden'),
];
$first = PageBlocks::normalizeSubmitted($source);
check($first['errors'] === [], 'legacy text / grid / hidden CTA accepted');
$ids = array_column(array_column($first['blocks'], 'data'), 'section_id');
check(count($ids) === 3 && count(array_unique($ids)) === 3, 'every top-level block has a unique ID');
check(count(array_filter($ids, fn($id) => preg_match('/^section-[0-9]{6}$/D', $id))) === 3, 'exact six-digit format');
check($first['blocks'][1]['data']['column_count'] === 3, 'legacy 3-column grid unchanged');
check(($first['blocks'][1]['data']['columns'][0]['blocks'][0]['data']['title'] ?? null) === 'Nested', 'legacy nested grid child unchanged');
check($first['blocks'][2]['status'] === 'hidden', 'hidden status retained');
$second = PageBlocks::normalizeSubmitted($first['blocks']);
check($second['errors'] === [], 'round-trip validation accepted');
check(array_column(array_column($second['blocks'], 'data'), 'section_id') === $ids, 'all IDs stable on save/reopen');
$reordered = PageBlocks::normalizeSubmitted([$first['blocks'][2], $first['blocks'][0], $first['blocks'][1]]);
check(array_column(array_column($reordered['blocks'], 'data'), 'section_id') === [$ids[2], $ids[0], $ids[1]], 'IDs follow blocks when sorting');
$dupe = PageBlocks::normalizeSubmitted([$first['blocks'][0], $first['blocks'][0], $first['blocks'][1]]);
check($dupe['blocks'][0]['data']['section_id'] === $ids[0], 'original section ID is preserved');
check($dupe['blocks'][1]['data']['section_id'] !== $ids[0], 'copied duplicate ID is replaced');
check(count(array_unique(array_column(array_column($dupe['blocks'],'data'),'section_id'))) === 3, 'all IDs remain unique after copy');
$invalid = PageBlocks::normalizeSubmitted([block('text', ['section_id' => 'section-<script>']), block('text', ['section_id' => 'section-12345']), block('text', ['section_id' => 'section-123456'])]);
check($invalid['blocks'][2]['data']['section_id'] === 'section-123456', 'valid existing ID is preserved');
check(count(array_unique(array_column(array_column($invalid['blocks'],'data'),'section_id'))) === 3, 'invalid IDs replaced without collision');
$malformed = PageBlocks::normalizeSubmitted([block('text', ['section_id' => ['not', 'scalar']])]);
check($malformed['errors'] === [] && preg_match('/^section-[0-9]{6}$/D',$malformed['blocks'][0]['data']['section_id']) === 1,'malformed array never triggers a TypeError');
check(PageBlocks::normalizeSubmitted([]) === ['blocks'=>[], 'errors'=>[]], 'empty/legacy page accepted');
$renderer = file_get_contents(dirname(__DIR__) . '/public_html/incl/helpers/page_builder_renderer.php');
check(strpos($renderer, 'random_int(') === false && strpos($renderer, 'render_page_builder_block') !== false, 'Renderer preserves saved section IDs without generating random IDs');
$view = file_get_contents(dirname(__DIR__) . '/public_html/admin/incl/page-builder-functions.php');
check(strpos($view,'[data][section_id]') !== false && strpos($view,'data-builder-section-id') !== false,'editor preserves hidden ID in POST');
echo "Page Builder V2.4.4w IDs: ALL PASS\n";
