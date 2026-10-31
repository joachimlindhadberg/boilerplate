<?php
/** Current UI regression: renders real update view, no network/DB/installer. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
$root = dirname(__DIR__);
require_once $root . '/core/classes/UpdateReleaseVersion.php';
function t(string $key, array $parameters = []): string { return $key; }
function admin_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_csrf_token(): string { return 'fixture-csrf'; }
function admin_icon(string $name, string $class = ''): string { return ''; }
$view = (string)file_get_contents($root . '/public_html/admin/views/tools/update.php');
$include = "require __DIR__ . '/../partials/layout.php';";
if (substr_count($view, $include) !== 1) exit(1);
$view = str_replace($include, '', $view);
$render = static function (?array $notice, ?array $choices, bool $recovery = false) use ($view): string {
    $report = ['version' => '2.5.1-beta.2'];
    $updateNotice = $notice;
    $updateChoices = $choices;
    $pendingRecovery = $recovery;
    eval('?>' . $view);
    return $bodyHtml;
};
$pass = 0; $fail = 0;
$check = static function (bool $yes, string $label) use (&$pass, &$fail): void {
    echo ($yes ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $yes ? $pass++ : $fail++;
};
$both = ['status'=>'available','releases'=>[
    ['kind'=>'stable','version'=>'2.5.2','url'=>'https://github.com/joachimlindhadberg/boilerplate/releases/tag/v2.5.2'],
    ['kind'=>'beta','version'=>'2.5.3-beta.1','url'=>'https://github.com/joachimlindhadberg/boilerplate/releases/tag/v2.5.3-beta.1'],
]];
$html = $render(['type'=>'success','message'=>'Boilerplate CMS has been updated successfully.'], $both);
$check(str_contains($html, 'Boilerplate CMS has been updated successfully.'), 'Translated success notice shown');
$check(!str_contains($html, 'updates.update_failed'), 'Success never shows failed message');
$check(str_contains($html, 'admin-notice--success'), 'Core success styling retained');
$check(substr_count($html, 'updates.choices_title') === 2, 'One available updates heading and accessible group');
$check(substr_count($html, 'name="release_selection"') === 2, 'Two radio choices displayed');
$check(str_contains($html, 'value="stable:2.5.2" checked'), 'Stable preselected');
$check(str_contains($html, 'value="beta:2.5.3-beta.1"'), 'Newer Beta offered');
$check(substr_count($html, 'updates.update_selected') === 1, 'One primary update action');
$check(str_contains($html, 'updates.check'), 'Check button stays visible');
$check(substr_count($html, 'updates.view_release') === 2 && substr_count($html, 'rel="noopener noreferrer"') === 2, 'Separate safe release links');
$check(!str_contains($html, 'name="channel"') && !str_contains($html, 'updates.save_channel'), 'Persistent channel UI removed');
$check(!str_contains($html, 'updates.latest_version'), 'No duplicated latest-version status');
$invalid = $render(['type'=>'error','message'=>'<script>alert(1)</script>'], null);
$check(str_contains($invalid, '&lt;script&gt;') && !str_contains($invalid, '<script>'), 'Notices escaped');
$check(str_contains($invalid, 'admin-notice--error'), 'Core error style retained');
$check(!str_contains($invalid, 'name="release_selection"'), 'No choice before search');
$recover = $render(null, $both, true);
$check(str_contains($recover, 'restore_interrupted_update') && !str_contains($recover, 'name="release_selection"'), 'Recovery suppresses installation');
$single = $render(null, ['status'=>'available', 'releases'=>[ $both['releases'][1] ]]);
$check(substr_count($single, 'name="release_selection"')===1 && str_contains($single, 'value="beta:2.5.3-beta.1" checked'), 'Single beta preselected');
$check(!str_contains($view, '<style') && !str_contains($view, 'style=') && !str_contains($view, '<script'), 'All styling Core-owned');
echo "RESULT: $pass PASS / $fail FAIL (rendered UI; no network, DB, install)
";
exit($fail ? 1 : 0);
