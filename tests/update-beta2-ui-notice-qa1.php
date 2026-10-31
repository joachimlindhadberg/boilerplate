<?php
/** Beta.2 UI regression. Renders the actual Core update view without HTTP, DB or writes. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
$root = dirname(__DIR__);
require_once $root . '/core/classes/UpdateReleaseVersion.php';
function t(string $key, array $parameters = []): string { return $key; }
function admin_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_csrf_token(): string { return 'fixture-csrf'; }
function admin_icon(string $name, string $class = ''): string { return ''; }
$view = (string)file_get_contents($root . '/public_html/admin/views/tools/update.php');
$layoutInclude = "require __DIR__ . '/../partials/layout.php';";
if (substr_count($view, $layoutInclude) !== 1) {
    fwrite(STDERR, "FAIL: Unexpected view footer\n"); exit(1);
}
// The view already captures its own HTML in $bodyHtml. Skip only the surrounding admin layout.
$view = str_replace($layoutInclude, '', $view);
$render = static function (?array $notice, ?array $lookup, ?array $prepared = null) use ($view): string {
    $report = ['version' => '2.5.1-beta.1'];
    $updateChannel = 'beta';
    $lookupResult = $lookup;
    $prepareResult = null;
    $preparedRelease = $prepared;
    $pendingRecovery = false;
    $updateNotice = $notice;
    eval('?>' . $view);
    return $bodyHtml;
};
$ok = 0; $fail = 0;
$check = static function (bool $pass, string $label) use (&$ok, &$fail): void {
    echo ($pass ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $pass ? $ok++ : $fail++;
};
$release = ['status'=>'available', 'latest_version'=>'2.5.1-beta.2',
    'release_url'=>'https://github.com/joachimlindhadberg/boilerplate/releases/tag/v2.5.1-beta.2'];
$success = $render(['type'=>'success', 'message'=>'Boilerplate CMS updated successfully.'], $release);
$check(str_contains($success, 'Boilerplate CMS updated successfully.'), 'Successful update shows translated Core notice');
$check(!str_contains($success, 'updates.update_failed'), 'Success cannot fall back to update failed notice');
$check(str_contains($success, 'admin-notice--success'), 'Success retains Core success styling');
$check(substr_count($success, 'updates.available</strong>')===1, 'Available status appears once');
$check(substr_count($success, 'updates.latest_version')===1, 'Latest version appears once');
$check(substr_count($success, '2.5.1-beta.2')>=2, 'Latest version and expected submission both match Beta 2');
$check(str_contains($success, 'updates.update_now') && str_contains($success, 'updates.check'), 'Core Check and Update now actions retained');
$check(str_contains($success, 'updates.view_release') && str_contains($success, 'rel="noopener noreferrer"'), 'Release link preserved safely');
$check(str_contains($success, 'name="expected_channel" value="beta"'), 'Beta selection bound to install form');
$unsafe = $render(['type'=>'error', 'message'=>'<script>alert(1)</script>'], null);
$check(str_contains($unsafe, '&lt;script&gt;') && !str_contains($unsafe, '<script>'), 'Notice message is HTML-escaped');
$check(str_contains($unsafe, 'admin-notice--error'), 'Error retains Core error styling');
$prepared = $render(null, null, ['status'=>'prepared_for_review_only', 'install_ready'=>false, 'version'=>'2.5.1-beta.2']);
$check(substr_count($prepared, 'updates.available</strong>')===1 && substr_count($prepared, 'updates.latest_version')===1, 'Prepared-only release has one status and version');
$check(str_contains($prepared, 'updates.update_now') && !str_contains($prepared, 'updates.view_release'), 'Prepared-only release can install without inventing a GitHub link');
$check(!str_contains($view, '<style') && !str_contains($view, 'style=') && !str_contains($view, '<script'), 'UI remains Core-owned without inline CSS or JS');
echo "RESULT: $ok PASS / $fail FAIL (rendered UI, no HTTP, MySQL or installation)\n";
exit($fail ? 1 : 0);
