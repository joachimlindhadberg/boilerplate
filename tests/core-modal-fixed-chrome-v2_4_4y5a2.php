<?php
/** Core modal scroll container regression, V2.4.4y5a2. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
$pass = 0;
$fail = 0;
$check = static function(bool $ok, string $label) use (&$pass, &$fail): void {
    $ok ? ++$pass : ++$fail;
    echo ($ok ? 'PASS: ' : 'FAIL: ').$label."\n";
};
$scss = (string)file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
$css = (string)file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$check($css === $scss, 'Core admin SCSS and compiled CSS are synchronized');
$extract = static function(string $selector) use ($css): string {
    $pattern = '~'.preg_quote($selector, '~').'\s*\{([^}]*)\}~';
    return preg_match($pattern, $css, $matches) === 1 ? $matches[1] : '';
};
$surface = $extract('.admin-modal__surface');
$header = $extract('.admin-modal__header');
$body = $extract('.admin-modal__body');
$footer = $extract('.admin-modal__footer');
$check(str_contains($surface, 'display: flex'), 'Surface creates a flex layout');
$check(str_contains($surface, 'flex-direction: column'), 'Surface is a vertical flex column');
$check(str_contains($surface, 'overflow: hidden'), 'Surface cannot scroll header or footer');
$check(str_contains($surface, 'max-height: calc(100dvh - 40px)'), 'Surface respects dynamic viewport height');
$check(str_contains($header, 'flex: 0 0 auto'), 'Header cannot shrink or scroll with body');
$check(str_contains($footer, 'flex: 0 0 auto'), 'Footer cannot shrink or scroll with body');
$check(str_contains($body, 'flex: 1 1 auto'), 'Modal content uses remaining height');
$check(str_contains($body, 'min-height: 0'), 'Content may shrink on small screens');
$check(str_contains($body, 'overflow-y: auto'), 'Content is the only vertical scrollbar');
$check(str_contains($body, 'overscroll-behavior: contain'), 'Inner scroll does not chain to page');
$check(str_contains($extract('.admin-media-picker-toolbar'), 'flex: 0 0 auto'), 'Media picker search toolbar remains outside scroll');
$check(str_contains($css, 'max-height: calc(100dvh - 18px)'), 'Mobile dynamic viewport limit retained');
$check(str_contains($css, '.admin-modal__body--flush { padding: 0; }'), 'Flush body wrapper is available for media details');
$mediaDetail = (string)file_get_contents($root.'/public_html/admin/views/media/partials/detail-dialog.php');
$bodyPosition = strpos($mediaDetail, 'class="admin-modal__body admin-modal__body--flush"');
$footerPosition = strpos($mediaDetail, 'class="admin-modal__footer admin-modal__footer--split"');
$check($bodyPosition !== false && $footerPosition !== false && $bodyPosition < $footerPosition, 'Media detail has content wrapper followed by footer');
$check(str_contains($mediaDetail, 'form="media-detail-update-form"') && str_contains($mediaDetail, 'data-admin-open-delete="media-delete-dialog"'), 'Media detail footer retains Save form and separate Core Delete trigger');
$check(str_contains($extract('.admin-media-detail__form'), 'overflow: visible'), 'Media detail uses shared body scrollbar');
$pages = (string)file_get_contents($root.'/public_html/admin/views/pages/partials/form-fields.php');
$forms = (string)file_get_contents($root.'/modules/forms/admin/views/partials/form-editor.php');
$check(str_contains($pages, 'admin-modal__header') && str_contains($pages, 'admin-modal__body') && str_contains($pages, 'admin-modal__footer'), 'Pages uses shared modal structure');
$check(str_contains($forms, 'admin-modal__header') && str_contains($forms, 'admin-modal__body') && str_contains($forms, 'admin-modal__footer'), 'Forms uses shared modal structure');
$version = (string)file_get_contents($root.'/public_html/incl/init.php');
$check((bool)preg_match("/BOILERPLATE_VERSION', '([0-9.]+)'/", $version, $versionMatch) && version_compare($versionMatch[1], '2.4.4.45', '>='), 'Release version is 2.4.4.45 or later');
printf("Summary V2.4.4y5a2: %d PASS / %d FAIL\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
