<?php

declare(strict_types=1);
/** Installer Core input reuse contract. No DB or HTTP. */
$base = dirname(__DIR__) . '/public_html';
$php = file_get_contents($base . '/install.php');
$css = file_get_contents($base . '/assets/install.css');
$scss = file_get_contents($base . '/assets/scss/install.scss');
$admin = file_get_contents($base . '/admin/assets/css/admin.css');
$check = static function (bool $pass, string $name): void {
    if (!$pass) { throw new RuntimeException('FAIL: ' . $name); }
    echo 'PASS: ' . $name . "\n";
};
$check($css === $scss, 'installer CSS and SCSS synced');
$check(str_contains($php, 'href="/admin/assets/css/admin.css"'), 'installer includes Core admin CSS');
$check(strpos($php, 'href="/admin/assets/css/admin.css"') !== false && strpos($php, 'href="/assets/install.css?v=') !== false
    && strpos($php, 'href="/admin/assets/css/admin.css"') < strpos($php, 'href="/assets/install.css?v='), 'Core stylesheet loads before installer layout');
$check(substr_count($php, 'class="install__field admin-field"') === 11, '11 visible fields reuse Core admin-field');
$check(str_contains($admin, '.admin-field input {'), 'Core owns input component');
$check(str_contains($admin, 'outline: none;') && str_contains($admin, '--admin-control-focus-keyboard-border'), 'Core owns single-border focus');
$check(!str_contains($css, '.install input:not([type="hidden"])'), 'installer no longer redefines visible input rules');
$check(!str_contains($css, '.install input:not([type="hidden"]):focus-visible'), 'installer no longer adds second focus outline');
$check(str_contains($css, 'grid-template-rows: auto minmax(0, 1fr) auto') && str_contains($css, '.install__body') && str_contains($css, 'overflow-y: auto'), 'installer content scrolls independently of header/footer');
