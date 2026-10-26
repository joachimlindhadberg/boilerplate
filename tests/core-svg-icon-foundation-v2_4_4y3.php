<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/public_html/admin/incl/functions.php';

$pass = 0;
$fail = 0;
$check = static function (bool $okay, string $label) use (&$pass, &$fail): void {
    if ($okay) {
        ++$pass;
        echo "PASS: {$label}\n";
    } else {
        ++$fail;
        echo "FAIL: {$label}\n";
    }
};
$rejects = static function (callable $callback): bool {
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return true;
    }
    return false;
};

// Must be byte-for-byte identical to the existing Settings menu icon body.
$settingsShape = '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>';
$settingIcon = admin_icon('settings', 'admin-nav__icon');
$check(str_contains($settingIcon, $settingsShape), 'Settings geometry unchanged from existing main navigation');
$check($settingIcon === AdminIcons::render('settings', 'admin-nav__icon'), 'Existing icon helper delegates to Core');
$check(str_contains($settingIcon, 'aria-hidden="true"') && str_contains($settingIcon, 'focusable="false"'), 'SVG icons are decorative');
$check(str_contains(admin_icon('edit'), 'M16.2 4.8'), 'Edit icon available');
$check(str_contains(admin_icon('delete'), 'M3 6h18'), 'Delete icon available');
$check(str_contains(admin_icon('missing-icon'), 'M6 2.5h8l4 4V21H6z'), 'Unknown legacy icon falls back to file');
$check(str_contains(admin_icon('file', '\" onload=\"alert(1)'), '&quot;'), 'SVG class is escaped');

$button = admin_icon_action_button('edit', 'Rediger overskrift', ['data-editor-open' => 'title', 'id' => 'edit-title']);
$check(str_starts_with($button, '<button type="button"'), 'Default icon action cannot submit a form');
$check(str_contains($button, 'aria-label="Rediger overskrift"'), 'Action button has accessible name');
$check(str_contains($button, 'title="Rediger overskrift"'), 'Action button has tooltip text');
$check(str_contains($button, 'data-editor-open="title"'), 'Safe module data attribute supported');
$check(str_contains($button, 'class="admin-icon-action__icon"'), 'Shared SVG class is present');
$check(str_contains($button, 'aria-hidden="true"'), 'Button SVG is hidden from accessibility tree');
$check(str_contains(admin_icon_action_button('delete', 'Slet', ['type' => 'submit', 'form' => 'delete-form'], 'danger'), 'type="submit"'), 'Explicit submit button allowed');
$check(str_contains(admin_icon_action_button('delete', 'Slet', [], 'danger'), 'admin-icon-action--danger'), 'Danger styling is opt-in');
$check(str_contains(admin_icon_action_button('settings', 'Indstillinger', ['disabled' => true]), ' disabled'), 'Disabled action supported');
$check(!str_contains(admin_icon_action_button('settings', 'Indstillinger', ['disabled' => false]), ' disabled'), 'False disabled omitted');
$check(str_contains(admin_icon_action_button('settings', 'Indstillinger', ['title' => 'Vis indstillinger']), 'title="Vis indstillinger"'), 'Tooltip may be overridden');
$check(str_contains(admin_icon_action_link('edit', 'Rediger', '/admin/sider?mode=edit&id=7'), 'href="/admin/sider?mode=edit&amp;id=7"'), 'Admin link escapes query parameters');
$check(str_contains(admin_icon_action_link('edit', 'Rediger', '/admin/sider?mode=edit&id=7', ['class' => 'my-extra']), 'admin-icon-action my-extra'), 'Explicit safe additional class supported');

foreach ([
    'empty label' => static fn() => admin_icon_action_button('edit', ''),
    'unknown action icon' => static fn() => admin_icon_action_button('other', 'Rediger'),
    'invalid button type' => static fn() => admin_icon_action_button('edit', 'Rediger', ['type' => 'javascript:bad']),
    'event handler attribute' => static fn() => admin_icon_action_button('edit', 'Rediger', ['onclick' => 'alert(1)']),
    'inline style attribute' => static fn() => admin_icon_action_button('edit', 'Rediger', ['style' => 'display:none']),
    'ARIA label override' => static fn() => admin_icon_action_button('edit', 'Rediger', ['aria-label' => 'bad']),
    'ARIA label reference override' => static fn() => admin_icon_action_button('edit', 'Rediger', ['aria-labelledby' => 'elsewhere']),
    'invalid class' => static fn() => admin_icon_action_button('edit', 'Rediger', ['class' => 'x" onclick="bad']),
    'malformed title' => static fn() => admin_icon_action_button('edit', 'Rediger', ['title' => ['x']]),
    'invalid tone' => static fn() => admin_icon_action_button('edit', 'Rediger', [], 'blue'),
    'javascript link' => static fn() => admin_icon_action_link('edit', 'Rediger', 'javascript:alert(1)'),
    'protocol-relative link' => static fn() => admin_icon_action_link('edit', 'Rediger', '//attacker.tld'),
    'backslash link' => static fn() => admin_icon_action_link('edit', 'Rediger', '/admin\\evil'),
    'event handler link' => static fn() => admin_icon_action_link('edit', 'Rediger', '/admin', ['onfocus' => 'bad']),
    'form override link' => static fn() => admin_icon_action_link('edit', 'Rediger', '/admin', ['form' => 'bad']),
] as $description => $callback) {
    $check($rejects($callback), 'Unsafe action rejected: ' . $description);
}
$malicious = admin_icon_action_button('edit', 'Edit " <script>alert(1)</script>', ['data-info' => '" onclick="oops']);
$check(!str_contains($malicious, '<script>') && str_contains($malicious, '&lt;script&gt;'), 'Label HTML properly escaped');
$check(str_contains($malicious, '&quot; onclick=&quot;oops'), 'Attribute value properly escaped');

$scss = file_get_contents($root . '/public_html/admin/assets/scss/admin.scss');
$css = file_get_contents($root . '/public_html/admin/assets/css/admin.css');
$check($scss === $css, 'Core SCSS and CSS remain identical');
foreach (['.admin-icon-action', '.admin-icon-action__icon', '.admin-icon-action--danger', ':focus-visible', 'aria-disabled'] as $needle) {
    $check(str_contains($css, $needle), 'Common admin CSS includes ' . $needle);
}
$bootstrap = file_get_contents($root . '/public_html/incl/init.php');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9.]+)'/", $bootstrap, $version) === 1 && version_compare($version[1], '2.4.4.37', '>='), 'CMS version retains SVG foundation');
$check(is_file($root . '/public_html/assets/css/style.css'), 'Frontend CSS remains present');
$check(!str_contains((string)file_get_contents($root . '/public_html/assets/css/style.css'), 'admin-icon-action'), 'Core admin icon CSS does not leak into frontend');

printf("\nSummary: %d PASS / %d FAIL\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
