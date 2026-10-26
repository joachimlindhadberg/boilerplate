<?php
/** Regression: V2.4.4y4b1 – transparent default, bordered hover/focus. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/public_html/admin/incl/functions.php';
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $what) use (&$pass, &$fail): void {
    $ok ? ++$pass : ++$fail;
    echo ($ok ? 'PASS ' : 'FAIL ') . $what . "\n";
};
$scss = (string)file_get_contents($root . '/public_html/admin/assets/scss/admin.scss');
$css = (string)file_get_contents($root . '/public_html/admin/assets/css/admin.css');
$check($scss === $css, 'SCSS/CSS synchronized');
$start = strpos($css, 'body.nf-admin .admin-icon-action {');
$end = strpos($css, '/* V2.4.4y4: shared SVG actions', $start ?: 0);
$block = $start !== false && $end !== false ? substr($css, $start, $end - $start) : '';
$check($block !== '', 'Core icon action rules found');
$check((bool)preg_match('/\.admin-icon-action\s*\{[^}]*border:\s*1px solid transparent;/s', $block), 'Default border visually transparent, space reserved');
$check((bool)preg_match('/\.admin-icon-action\s*\{[^}]*background:\s*transparent;/s', $block), 'Default background transparent');
$check((bool)preg_match('/\.admin-icon-action:hover,\s*body\.nf-admin \.admin-icon-action:focus-visible\s*\{[^}]*border-color:\s*var\(--admin-control-hover-border\);/s', $block), 'Neutral hover and focus regain old button border');
$check((bool)preg_match('/\.admin-icon-action--danger:hover,\s*body\.nf-admin \.admin-icon-action--danger:focus-visible\s*\{[^}]*border-color:\s*#d8aaaa;/s', $block), 'Delete hover and focus regain muted red border');
$check(str_contains($block, 'outline: 2px solid var(--admin-accent);'), 'Keyboard focus outline maintained');
$check(str_contains($block, '--admin-icon-action-size: 32px;') && str_contains($block, '--admin-icon-action-size: 36px;'), 'Tap targets unchanged');
$check(str_contains(admin_icon_action_button('edit','Rediger'), 'aria-label="Rediger"'), 'Accessible icon semantics unchanged');
$version = (string)file_get_contents($root . '/public_html/incl/init.php');
$check(preg_match("~BOILERPLATE_VERSION', '([0-9.]+)'~", $version, $matches) === 1 && version_compare($matches[1], "2.4.4.41", ">="), 'Release increment');
$check(!str_contains((string)file_get_contents($root . '/public_html/assets/css/style.css'), 'admin-icon-action'), 'Frontend remains independent');
echo "SUMMARY: {$pass} PASS / {$fail} FAIL\n";
exit($fail ? 1 : 0);
