<?php
/** Regression: V2.4.4y4b global icon action appearance. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/public_html/admin/incl/functions.php';
$passes = 0;
$fails = 0;
$check = static function (bool $yes, string $what) use (&$passes, &$fails): void {
    $yes ? ++$passes : ++$fails;
    echo ($yes ? 'PASS ' : 'FAIL ') . $what . "\n";
};
$scss = file_get_contents($root . '/public_html/admin/assets/scss/admin.scss');
$css = file_get_contents($root . '/public_html/admin/assets/css/admin.css');
$check($css === $scss, 'Global Core admin SCSS and compiled CSS are synced');
$start = strpos($css, 'body.nf-admin .admin-icon-action {');
$end = strpos($css, '/* V2.4.4y4: shared SVG actions', $start ?: 0);
$block = $start !== false && $end !== false ? substr($css, $start, $end - $start) : '';
$check($block !== '', 'Shared action style block exists');
$check((bool)preg_match('/\.admin-icon-action\s*\{[^}]*border:\s*1px solid transparent;/s', $block), 'Normal state has no visible border and reserves its dimensions');
$check((bool)preg_match('/\.admin-icon-action\s*\{[^}]*background:\s*transparent;/s', $block), 'Normal state is transparent');
$check(str_contains($block, '.admin-icon-action:hover,') && str_contains($block, '.admin-icon-action:focus-visible {'), 'Hover and keyboard focus share visible interaction state');
$check(str_contains($block, 'background: var(--admin-surface-soft);'), 'Hover and focus use Core neutral surface');
$check(str_contains($block, '.admin-icon-action--danger:hover,') && str_contains($block, '.admin-icon-action--danger:focus-visible {'), 'Danger actions respond to hover and focus');
$check(str_contains($block, 'color: var(--admin-danger);'), 'Danger actions use Core danger token');
$check(str_contains($block, 'outline: 2px solid var(--admin-accent);'), 'Keyboard focus retains explicit visible outline');
$check(str_contains($block, 'cursor: not-allowed;') && str_contains($block, 'pointer-events: none;'), 'Disabled actions remain inert');
$check(str_contains($block, '--admin-icon-action-size: 32px;') && str_contains($block, '--admin-icon-action-size: 36px;'), 'Desktop and mobile click targets retained');
$check(str_contains($block, 'width: 17px; height: 17px;'), 'Original icon geometry retained');
$check(str_contains(admin_icon_action_button('settings', 'Indstillinger'), 'aria-label="Indstillinger"'), 'Button labels preserved');
$check(str_contains(admin_icon_action_button('delete', 'Slet', [], 'danger'), 'admin-icon-action--danger'), 'Danger tone remains opt-in');
$check(str_contains(admin_icon_action_link('edit', 'Rediger', '/admin/sider'), 'href="/admin/sider"'), 'Icon links preserved');
$versionSource = file_get_contents($root . '/public_html/incl/init.php');
$matches = [];
$check(preg_match("~BOILERPLATE_VERSION', '([0-9.]+)'~", $versionSource, $matches) === 1 && version_compare($matches[1], '2.4.4.40', '>='), 'Release version incremented');
$check(!str_contains(file_get_contents($root . '/public_html/assets/css/style.css'), 'admin-icon-action'), 'No Core icon styling leaked into frontend CSS');
echo "SUMMARY: {$passes} PASS / {$fails} FAIL\n";
exit($fails ? 1 : 0);
