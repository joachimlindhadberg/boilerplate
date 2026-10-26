<?php

declare(strict_types=1);
/** Source-only checks for the isolated 4A.6.3a installer UI revision. */
$base = dirname(__DIR__) . '/public_html';
$php = file_get_contents($base . '/install.php');
$css = file_get_contents($base . '/assets/install.css');
$scss = file_get_contents($base . '/assets/scss/install.scss');
$assert = static function (bool $ok, string $name): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $name);
    echo 'PASS: ' . $name . "\n";
};
$assert($css === $scss, 'SCSS/CSS synchronized');
$assert(str_contains($css, 'height: min(680px, calc(100dvh - 32px))'), 'wizard has viewport-capped modal height');
$assert(preg_match('/\.install__body\s*\{[^}]*overflow-y:\s*auto/s', $css) === 1, 'only modal body scrolls');
$assert(!str_contains($css, '.install input:not([type="hidden"])') && str_contains($css, '.install input[type="hidden"]'), 'Core owns visible input styling; installer retains hidden input rule');
$assert(substr_count($php, 'class="install__field admin-field"') === 11, '11 installer fields reuse Core class');
$assert(substr_count($php, 'name="csrf"') === 2 && substr_count($php, 'type="hidden" name="action" value=') === 1 && str_contains($php, 'name="action" value="finish"'), 'both installer forms keep CSRF and submission actions');
$assert(str_contains($php, 'FirstInstall::install(') && !str_contains($php, 'ensureInstallationKey('), 'installation retained without key activation');
$assert(str_contains($php, '<footer class="install__footer"') && str_contains($php, 'form="install-db-form"') && str_contains($php, 'form="install-site-form"'), 'single fixed footer owns actions for both forms');
