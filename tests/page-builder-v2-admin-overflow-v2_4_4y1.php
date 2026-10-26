<?php
/** V2.4.4y1: Page Builder hidden grid controls cannot widen admin edit page. */
declare(strict_types=1);

$root = dirname(__DIR__);
$scss = file_get_contents($root . '/public_html/admin/assets/scss/admin.scss');
$css = file_get_contents($root . '/public_html/admin/assets/css/admin.css');
$markup = file_get_contents($root . '/public_html/admin/incl/page-builder-functions.php');
$version = file_get_contents($root . '/public_html/incl/init.php');

function ensure_y1(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, 'FAIL: ' . $message . "\n");
        exit(1);
    }
    echo 'PASS: ' . $message . "\n";
}

ensure_y1($css === $scss, 'Core admin SCSS and compiled CSS are synchronized');
ensure_y1(str_contains($markup, 'class="sr-only" data-grid-child-type'), 'Page Builder preserves accessible hidden grid field-type select');
ensure_y1(str_contains($scss, 'body.nf-admin .admin-grid-column__add select.sr-only'), 'Narrow rule targets only hidden selects inside the shared admin grid control');
ensure_y1(preg_match('/body\.nf-admin\s+\.admin-grid-column__add\s+select\.sr-only\s*\{[^}]*\bwidth:\s*1px;[^}]*\bheight:\s*1px;[^}]*\bpadding:\s*0;[^}]*\bborder:\s*0;/', $scss) === 1,
    'Hidden select has its one-pixel geometry restored with no padding/border');
ensure_y1(str_contains($scss, '.admin-grid-column__add select {') && str_contains($scss, 'width: 100%;'), 'Visible select component styling remains intact');
ensure_y1(!str_contains($scss, 'html { overflow-x: hidden;') && !str_contains($scss, 'body.nf-admin { overflow-x: hidden;'), 'Fix does not globally hide horizontal overflows');
ensure_y1(preg_match("~BOILERPLATE_VERSION', '([0-9.]+)'~", $version, $match) === 1 && version_compare($match[1], '2.4.4.35', '>='), 'Hotfix remains included in current version');
echo "V2.4.4y1 Page Builder admin overflow: 7 PASS / 0 FAIL\n";
