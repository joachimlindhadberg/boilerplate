<?php
/** QA1 Getting Started source/translation/accessibility contract; no database access. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
$view = file_get_contents($root . '/public_html/admin/views/dashboard.php');
$controller = file_get_contents($root . '/public_html/admin/index.php');
$js = file_get_contents($root . '/public_html/admin/assets/js/admin.js');
$dict = json_decode(file_get_contents($root . '/core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
$checks = [];
$checks['No old personal greeting'] = !str_contains($view, "t('dashboard.welcome_name'")
    && !str_contains($dict['dashboard.welcome_title']['en'], 'back');
$checks['New title and onboarding description'] = $dict['dashboard.welcome_title']['en'] === 'Welcome to Boilerplate CMS!'
    && str_contains($dict['dashboard.welcome_description']['en'], 'Start by editing your homepage');
$checks['Onboarding heading and close have accessible labels'] = str_contains($view, 'aria-labelledby="dashboard-welcome-heading"')
    && str_contains($view, 'data-dashboard-welcome-close aria-label=');
$checks['Homepage link requires server content capability'] = str_contains($view, 'if (!empty($canManageContent))')
    && str_contains($controller, "$" . "canManageContent = " . "$" . "auth->can('content.manage')");
$checks['Settings link requires server settings capability'] = str_contains($view, 'if (!empty($canManageSettings))')
    && str_contains($controller, "'canManageSettings' => " . "$" . "auth->can('settings.manage')");
$checks['Homepage edit link resolves real database ID'] = str_contains($controller, "(new Pages())->findBySlug('forside')")
    && str_contains($controller, "'/admin/sider?mode=edit&id=' . (int)" . '$home')
    && str_contains($view, 'admin_e(is_string($homePageEditUrl ?? null)');
$checks['Site view link remains safe in new tab'] = str_contains($view, 'target="_blank" rel="noopener"');
$checks['Browser-pref is per-account and v3'] = str_contains($js, 'dashboard-welcome.v3.user-') && str_contains($js, 'localStorage.setItem(storageKey');
$checks['Legacy dismissals are respected'] = str_contains($js, 'dashboard-welcome.v2.user-') && str_contains($js, 'localStorage.getItem(legacyKey) !== null');
$checks['No timed automatic reopening'] = !str_contains($js, 'welcomeIntervalMs') && !str_contains($js, 'lastShownAt');
$checks['Restore and close preserve keyboard focus'] = str_contains($js, 'welcomeRestore.focus({ preventScroll: true })') && str_contains($js, 'close.focus({ preventScroll: true })');
$checks['Restore stays in dashboard titlebar'] = str_contains($view, 'data-dashboard-welcome-restore hidden');
$checks['No Core stylesheet fork'] = file_get_contents($root . '/public_html/admin/assets/css/admin.css') === file_get_contents($root . '/public_html/admin/assets/scss/admin.scss');
foreach (['dashboard.welcome_kicker', 'dashboard.welcome_title', 'dashboard.welcome_description', 'dashboard.welcome_shortcuts', 'dashboard.welcome_edit_homepage', 'dashboard.welcome_site_settings', 'dashboard.welcome_view_site'] as $key) {
    $checks["Translations: $key"] = array_keys($dict[$key] ?? []) === ['en', 'da', 'de', 'sv', 'nb']
        && !in_array('', $dict[$key] ?? [], true);
}
foreach ($checks as $label => $passed) echo ($passed ? 'PASS: ' : 'FAIL: ') . $label . "\n";
$failed = count(array_filter($checks, static fn($x) => !$x));
echo 'Getting Started QA1: ' . (count($checks) - $failed) . ' PASS / ' . $failed . " FAIL\n";
exit($failed > 0 ? 1 : 0);
