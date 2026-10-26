<?php
/** V2.4.4z20i-e: upload CTA matches Pages' Core text-button style with the correct semantic upload icon. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
require_once $root . '/core/classes/AdminEscaper.php';
require_once $root . '/core/classes/AdminIcons.php';
$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
    $ok ? ++$pass : ++$fail;
};
$source = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$index = $source('public_html/admin/views/media/index.php');
$dialog = $source('public_html/admin/views/media/partials/upload-dialog.php');
$pages = $source('public_html/admin/views/pages/index.php');
$js = $source('public_html/admin/assets/js/admin.js');
$controller = $source('public_html/admin/media.php');
$translations = json_decode($source('core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
$locale = 'da';
function admin_e(mixed $value): string { return AdminEscaper::text($value); }
function admin_icon(string $name, string $class = ''): string { return AdminIcons::render($name, $class); }
function admin_csrf_token(): string { return 'synthetic-media-token'; }
function t(string $key, array $args = []): string {
    global $translations, $locale;
    $text = (string)($translations[$key][$locale] ?? $key);
    foreach ($args as $placeholder => $value) {
        $text = str_replace('{' . $placeholder . '}', (string)$value, $text);
    }
    return $text;
}
$check(str_contains($pages, "admin_icon('plus', 'admin-button__icon')"), 'Ny side retains the Core reference plus icon');
$check(substr_count($index, 'data-media-upload-open') === 1, 'Exactly one media upload trigger');
$check(preg_match('/<button class="admin-button" type="button" data-media-upload-open>\<\?= admin_icon\(\'upload\', \'admin-button__icon\'\); \?\>/', $index) === 1, 'Upload medier uses identical Core button class and icon class as Ny side');
$check(!str_contains($index, "data-media-upload-open><?= admin_icon('plus'"), 'Upload trigger never implies a new CMS record');
$check(str_contains($dialog, '<button class="admin-button" type="submit"><?= admin_icon(\'upload\', \'admin-button__icon\'); ?>'), 'Dialog primary Upload action shares the same Core icon and styling');
$check(!str_contains($dialog, "admin_icon('plus'"), 'Dialog upload action does not use create icon');
$check(str_contains($dialog, 'method="post" action="/admin/medier?mode=upload" enctype="multipart/form-data"'), 'Original multipart POST upload route preserved');
$check(str_contains($dialog, 'name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>"'), 'CSRF token remains in upload form');
$check(str_contains($dialog, 'name="media_files[]"') && str_contains($dialog, 'multiple required data-admin-upload-input'), 'Multi-file input and client upload hooks preserved');
$check(str_contains($dialog, 'data-admin-upload-dropzone') && str_contains($dialog, 'data-admin-upload-selection'), 'Drag-and-drop hooks and feedback preserved');
$check(str_contains($dialog, '<button class="admin-button admin-button--secondary" type="button" data-modal-close>'), 'Cancel remains independent, with its secondary tone');
$check(str_contains($js, "document.querySelector('[data-media-upload-open]')?.addEventListener('click', () => openDialog(mediaUploadDialog));"), 'Unchanged Core JS opens the upload modal');
$check(str_contains($js, "const mediaUploadDialog = document.querySelector('[data-media-upload-dialog]');"), 'Unchanged Core upload dialog target');
$check(str_contains($controller, "const ADMIN_REQUIRED_CAPABILITY = 'media.manage'"), 'Unchanged server-side media.manage capability');
$check(str_contains($controller, "if (!admin_verify_csrf(\$_POST['csrf_token'] ?? null))"), 'Unchanged server-side CSRF verification');
$check(str_contains($controller, "if (\$mode === 'upload')"), 'Original upload processing route remains');
$check($source('public_html/admin/assets/scss/admin.scss') === $source('public_html/admin/assets/css/admin.css'), 'Core SCSS and compiled CSS byte-identical');
$check(str_contains($source('public_html/admin/assets/css/admin.css'), '.admin-button__icon { display: block; width: 16px; height: 16px; flex: 0 0 16px; margin-right: 7px; }'), 'Both upload and create icons share the original CSS sizing');
$check(str_contains($source('docs/design/CORE-UI-STANDARD-V1.0.md'), "admin_icon('upload', 'admin-button__icon')"), 'Binding Core UI standard explains semantic upload icon');
$check(preg_match("/BOILERPLATE_VERSION'[,]? '([0-9.]+)'/", $source('public_html/incl/init.php'), $matches) === 1 && version_compare($matches[1], '2.4.4.91', '>='), 'Hotfix compatibility remains in subsequent releases');
$uploadSvg = AdminIcons::render('upload', 'admin-button__icon');
$plusSvg = AdminIcons::render('plus', 'admin-button__icon');
$check(str_contains($uploadSvg, 'aria-hidden="true"') && str_contains($uploadSvg, 'focusable="false"'), 'Upload SVG is decorative and keyboard-inert');
$check(str_contains($uploadSvg, 'class="admin-button__icon"') && str_contains($plusSvg, 'class="admin-button__icon"'), 'Upload and plus share the same sized CSS class');
$check($uploadSvg !== $plusSvg && str_contains($uploadSvg, 'M12 16V4'), 'Core registry outputs genuine upload arrow instead of plus');
$check(str_contains($uploadSvg, 'viewBox="0 0 24 24"') && str_contains($plusSvg, 'viewBox="0 0 24 24"'), 'Icons use same Core SVG coordinate system');
$check(preg_match('/<button\b[^>]*data-media-upload-open[^>]*>.*?<\/button>/s', $index, $match) === 1, 'Media titlebar trigger can be isolated for real HTML render');
$triggerSource = $match[0] ?? '';
foreach (['da', 'en', 'de', 'sv', 'nb'] as $code) {
    $locale = $code;
    ob_start();
    eval('?>' . $triggerSource);
    $triggerHtml = (string)ob_get_clean();
    $check(str_contains($triggerHtml, 'data-media-upload-open') && str_contains($triggerHtml, '<svg class="admin-button__icon"'), "Media titlebar button renders Core SVG in {$code}");
    $check(str_contains($triggerHtml, admin_e($translations['Upload medier'][$code])) && !str_contains($triggerHtml, '>+</'), "Media titlebar uses translated label and non-plus SVG in {$code}");
    $effectiveFileMaxLabel = '8 MB';
    $effectiveFileMax = 8388608;
    $effectiveTotalMax = 16777216;
    ob_start(); require $root . '/public_html/admin/views/media/partials/upload-dialog.php';
    $renderedDialog = (string)ob_get_clean();
    $check(substr_count($renderedDialog, 'class="admin-button__icon"') === 1, "Exactly one sized Core upload icon appears in dialog action ({$code})");
    $check(str_contains($renderedDialog, 'type="submit"><svg class="admin-button__icon"') && str_contains($renderedDialog, '>' . admin_e($translations['Upload'][$code]) . '</button>'), "Translated modal submit contains upload icon and label ({$code})");
    $check(str_contains($renderedDialog, 'name="csrf_token" value="synthetic-media-token"') && str_contains($renderedDialog, '/admin/medier?mode=upload'), "Modal renders original POST and CSRF ({$code})");
}
printf("Core Media Upload Button V2.4.4z20i-e: %d PASS / %d FAIL\n", $pass, $fail);
exit($fail ? 1 : 0);
