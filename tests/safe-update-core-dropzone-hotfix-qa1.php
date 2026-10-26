<?php
/** UI contract regression. Read-only static test; no DB, uploads or rendering. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
$root = dirname(__DIR__);
$view = (string)file_get_contents($root . '/public_html/admin/views/tools/partials/update-package-review.php');
$js = (string)file_get_contents($root . '/public_html/admin/assets/js/admin.js');
$core = (string)file_get_contents($root . '/public_html/admin/views/tools/partials/import.php');
$review = (string)file_get_contents($root . '/core/classes/UpdatePackageReview.php');
$translations = json_decode((string)file_get_contents($root . '/core/lang/translations.json'), true);
$pass = 0; $fail = 0;
$assert = static function (bool $ok, string $name) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $name . "\n";
    $ok ? $pass++ : $fail++;
};
foreach (['admin-upload-dropzone', 'data-admin-upload-dropzone', 'data-admin-upload-input',
    'data-admin-upload-selection', 'data-admin-upload-name', 'data-admin-upload-size',
    'data-upload-max-bytes="20971520"', 'data-upload-extensions=".zip"',
    'admin-upload-dropzone__preview', 'admin-upload-dropzone__meta',
    "admin_icon('upload', 'admin-upload-dropzone__icon')"] as $token) {
    $assert(str_contains($view, $token), 'Core dropzone contract: ' . $token);
}
$assert(str_contains($core, 'data-admin-upload-dropzone') && str_contains($js, "querySelectorAll('[data-admin-upload-dropzone]')"), 'Existing Core upload JS reused');
$assert(!preg_match('/<input[^>]*type="file"[^>]*class="admin-input"/', $view), 'No ad-hoc file input');
$assert(str_contains($view, 'admin_csrf_token()') && str_contains($view, 'name="update_package"'), 'CSRF and upload field intact');
$assert(str_contains($view, "admin_icon('search', 'admin-button__icon')"), 'Submit button retains Core icon');
$assert(is_array($translations), 'Translations file valid JSON');
foreach (['drop_title', 'drop_or', 'drop_browse', 'drop_change', 'drop_format', 'drop_type_error', 'drop_size_error'] as $suffix) {
    $key = 'update_package.' . $suffix;
    $assert(isset($translations[$key]['en'], $translations[$key]['da'], $translations[$key]['de'], $translations[$key]['sv'], $translations[$key]['nb']), 'Translated Core upload text: ' . $key);
}
$assert(str_contains($review, "'public_html/.user.ini'") && !str_contains($review, 'extractTo('), 'Narrow PHP hosting config exception; no extraction');
$assert(hash_file('sha256', $root . '/public_html/admin/assets/scss/admin.scss') === hash_file('sha256', $root . '/public_html/admin/assets/css/admin.css'), 'No admin CSS/SCSS divergence');
echo "Safe Update Core Dropzone Hotfix: {$pass} PASS / {$fail} FAIL\n";
exit($fail ? 1 : 0);
