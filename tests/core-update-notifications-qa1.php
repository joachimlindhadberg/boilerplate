<?php

declare(strict_types=1);

/** Core Update Notifications QA1: local, read-only, no live GitHub or DB use. */
$root = dirname(__DIR__);
require_once $root . '/core/classes/UpdateReleaseVersion.php';
require_once $root . '/core/classes/UpdateAdminNotification.php';
$pass = 0;
$fail = 0;
$check = static function (bool $condition, string $message) use (&$pass, &$fail): void {
    if ($condition) { ++$pass; } else { ++$fail; echo "FAIL: {$message}\n"; }
};

$installed = '2.5.1-beta.9';
$session = [];
UpdateAdminNotification::rememberChoices($installed, $session, [
    'status' => 'available',
    'releases' => [
        ['kind' => 'beta', 'version' => '2.5.1-beta.10', 'url' => ''],
        ['kind' => 'stable', 'version' => '2.5.1', 'url' => ''],
    ],
], 1000);
$available = ['status' => 'available', 'latest_version' => '2.5.1'];
// PHP version_compare() correctly prefers a final stable over its prerelease.
$check(UpdateAdminNotification::overview($installed, $session, 1001) === $available, 'All-channel cached best release');
$check(($session['boilerplate_admin_update_notice_v1']['ttl'] ?? 0) === 3600, 'Successful lookup TTL');
$check(UpdateAdminNotification::check($installed, $session, static fn(): array => throw new RuntimeException('Must not refetch'), 1001, 'all')['status'] === 'available', 'Manual lookup primes shared cache');

$session = [];
UpdateAdminNotification::rememberChoices($installed, $session, [
    'status' => 'available',
    'releases' => [['kind' => 'beta', 'version' => '2.5.1-beta.10']],
], 1000);
$check(UpdateAdminNotification::overview($installed, $session, 1001) === [
    'status' => 'available', 'latest_version' => '2.5.1-beta.10',
], 'Preview-only update surfaced');
$session = [];
UpdateAdminNotification::rememberChoices($installed, $session, ['status' => 'current', 'releases' => []], 1000);
$check(UpdateAdminNotification::overview($installed, $session, 1001) === ['status' => 'current', 'latest_version' => ''], 'Current reported without extra API call');
$session = [];
UpdateAdminNotification::rememberChoices($installed, $session, ['status' => 'no_release', 'releases' => []], 1000);
$check(UpdateAdminNotification::overview($installed, $session, 1001) === ['status' => 'no_release', 'latest_version' => ''], 'No-release reported without API');
$session = [];
UpdateAdminNotification::rememberChoices($installed, $session, ['status' => 'connection_error', 'releases' => []], 1000);
$check(UpdateAdminNotification::overview($installed, $session, 1001) === ['status' => 'error', 'latest_version' => ''], 'Network failures cannot appear current');
$check(($session['boilerplate_admin_update_notice_v1']['ttl'] ?? 0) === 300, 'Failure TTL shorter than successful lookup');
$session = [];
UpdateAdminNotification::rememberChoices($installed, $session, ['status' => 'available', 'releases' => [
    ['kind' => 'stable', 'version' => '2.5.1-beta.10'],
]], 1000);
$check(UpdateAdminNotification::overview($installed, $session, 1001)['status'] === 'error', 'Invalid kind/version mismatch rejected');
$session = [];
UpdateAdminNotification::rememberChoices($installed, $session, ['status' => 'available', 'releases' => [
    ['kind' => 'beta', 'version' => 'https://example.test/evil'],
]], 1000);
$check(UpdateAdminNotification::overview($installed, $session, 1001)['status'] === 'error', 'Non-version rejected');
$session = [];
UpdateAdminNotification::rememberChoices($installed, $session, ['status' => 'available', 'releases' => [
    ['kind' => 'beta', 'version' => '2.5.1-beta.9'],
]], 1000);
$check(UpdateAdminNotification::overview($installed, $session, 1001)['status'] === 'error', 'Equal version never offered');

$files = [
    'public_html/admin/index.php' => ["UpdateAdminNotification::overview", "\$auth->can('tools.manage')", "Cache-Control: private, no-store"],
    'public_html/admin/tools.php' => ['UpdateAdminNotification::rememberChoices', 'admin_verify_csrf', 'install_official_update', 'prepareSelected'],
    'public_html/admin/views/dashboard.php' => ["t('updates.available_beta_description')", 'data-dashboard-update-version'],
    'public_html/admin/views/partials/layout.php' => ["'updates.preview_label'", 'data-admin-nav-key'],
    'public_html/admin/views/tools/partials/tool-card.php' => ['data-core-update-card-status', 'admin-health-indicator'],
    'public_html/admin/views/tools/update.php' => ['data-core-update-page', 'data-core-update-autoload', 'data-official-update-form', 'data-core-update-summary'],
    'docs/design/CORE-UI-STANDARD-V1.0.md' => ['## Core update availability (QA1)', 'Check for updates'],
    'public_html/admin/assets/js/admin.js' => ['coreUpdateAvailable', 'data-core-update-card-text', 'data-core-update-page', 'release_selection', "fetch(endpoint,"],
];
foreach ($files as $path => $needles) {
    $text = @file_get_contents($root . '/' . $path);
    $check(is_string($text), "Readable {$path}");
    if (!is_string($text)) continue;
    foreach ($needles as $needle) $check(str_contains($text, $needle), "{$path} includes {$needle}");
}
$js = file_get_contents($root . '/public_html/admin/assets/js/admin.js');
$check(substr_count($js, "const endpoint = '/admin/index.php?mode=update-notice';") === 1, 'One Core update endpoint per view');
$check(!str_contains($js, 'innerHTML = data.latest_version'), 'No untrusted HTML injection');
$check(str_contains($js, 'installForm.hidden = !canOffer;'), 'Recovery blocks automatic install option');
$check(str_contains($js, 'options.replaceChildren(item)'), 'Dynamic update option uses safe DOM APIs');
$check(str_contains($js, 'data.latest_version.includes'), 'Prerelease kind inferred from version');
$check(str_contains($js, 'adminTr('), 'Localized UI labels used');
$check(str_contains($js, "data?.status === 'current'"), 'Distinguishes current from connection failure');
$check(str_contains($js, "data?.status === 'no_release'"), 'No-release safe state');
$check(str_contains($js, 'toolsNav.setAttribute(\'aria-label\''), 'Sidebar notification has accessible label');

$css = file_get_contents($root . '/public_html/admin/assets/css/admin.css');
$scss = file_get_contents($root . '/public_html/admin/assets/scss/admin.scss');
$check($css === $scss, 'Core CSS/SCSS byte-identical');
foreach (['.admin-nav a[data-core-update-available="true"]::after', '.admin-tools-card__status[hidden]', '[data-core-update-summary][hidden]', 'form[data-official-update-form][hidden]'] as $needle) {
    $check(str_contains($css, $needle), "Core CSS contains {$needle}");
}
$catalog = json_decode((string)file_get_contents($root . '/core/lang/translations.json'), true);
foreach (['updates.available_beta_description', 'updates.available', 'updates.up_to_date', 'updates.check_failed', 'updates.choices_title', 'updates.choices_description', 'updates.preview_label', 'updates.preview_help', 'updates.stable_label', 'updates.stable_help', 'updates.view_release'] as $key) {
    foreach (['en', 'da', 'de', 'sv', 'nb'] as $locale) {
        $check(is_string($catalog[$key][$locale] ?? null) && trim($catalog[$key][$locale]) !== '', "Translation {$key}/{$locale}");
    }
}
echo "Core Update Notifications QA1: {$pass} PASS / {$fail} FAIL (read-only, no GitHub/DB)\n";
exit($fail === 0 ? 0 : 1);
