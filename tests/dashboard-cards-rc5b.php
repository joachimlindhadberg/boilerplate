<?php

declare(strict_types=1);
/** RC5b source/UI-contract regression. No bootstrap, DB, migrations or HTTP. */
$root = dirname(__DIR__);
$count = 0;
$test = static function (bool $value, string $message) use (&$count): void {
    if (!$value) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    ++$count; echo "PASS: $message\n";
};
$read = static fn(string $file): string => (string)file_get_contents($root . '/' . $file);
$view = $read('public_html/admin/views/dashboard.php');
$controller = $read('public_html/admin/index.php');
$js = $read('public_html/admin/assets/js/admin.js');
$css = $read('public_html/admin/assets/css/admin.css');
$scss = $read('public_html/admin/assets/scss/admin.scss');
$layout = $read('public_html/admin/views/partials/layout.php');

$test(!str_contains($view, 'data-dashboard-cards-toggle'), 'Remove large top-level Customize Cards action');
$test(!str_contains($view, 'data-dashboard-cards-panel'), 'Remove giant interactive editor panel');
$test(str_contains($view, 'data-dashboard-welcome-restore'), 'Show Welcome restoration action remains present');
$test(str_contains($view, '<div class="admin-page-actions">') && str_contains($view, 'data-dashboard-welcome-restore hidden'), 'Show Welcome retains original titlebar action position');
$test(str_contains($view, 'data-dashboard-welcome-close'), 'Welcome close action retained');
$test(str_contains($js, '[data-dashboard-welcome-restore]') && str_contains($js, 'dashboard-welcome.v3.user-'), 'Welcome restore retained with one-time onboarding behavior');
$test(str_contains($view, 'foreach ($cardSelection as $index => $key)'), 'Cards generated from saved selection');
$test(str_contains($view, 'data-dashboard-card data-metric='), 'Each metric card has current key and slot');
$test(str_contains($view, 'data-dashboard-card-trigger') && str_contains($view, 'aria-expanded="false"'), 'Each card gets accessible disclosure trigger');
$test(str_contains($view, 'data-dashboard-card-menu hidden') && str_contains($view, 'aria-controls="dashboard-card-menu-'), 'Each card has uniquely addressable initially hidden dropdown');
$test(str_contains($view, 'data-dashboard-card-choice=') && str_contains($view, 'aria-pressed='), 'Each option states selected status for assistive tech');
$test(str_contains($view, "admin_icon('dots', 'admin-metric__settings-icon')"), 'Small Core SVG more-icon used');
$test(str_contains($view, 'data-dashboard-cards-status') && str_contains($view, 'aria-live="polite"'), 'ARIA status announces autosave');
$test(str_contains($view, 'data-dashboard-cards-notice') && str_contains($view, 'role="alert"'), 'Failed save presents accessible feedback');
$test(str_contains($view, 'data-dashboard-cards-icons hidden') && str_contains($view, '<template data-dashboard-icon='), 'Only pre-rendered Core icon SVG templates are used');
$test(str_contains($view, 'data-save-url="/admin/index.php?mode=dashboard-cards-ajax"'), 'AJAX endpoint is direct PHP path, avoids /admin redirect');
$test(str_contains($view, '<noscript>') && str_contains($view, '<form method="post" action="/admin/index.php?mode=dashboard-cards"'), 'Without JS original CSRF POST editing remains accessible');
$test(str_contains($view, 'name="cards[]"') && str_contains($view, 'name="csrf_token"'), 'Fallback submits exactly Core validation contract');
$test(substr_count($view, 'class="admin-dashboard-cards-panel"') === 0, 'No visible dashboard customization panel');

$test(str_contains($controller, "\$mode === 'dashboard-cards-ajax'"), 'Separate AJAX route is explicit');
$test(strpos($controller, 'if (!$auth->check())') < strpos($controller, "\$mode === 'dashboard-cards-ajax'"), 'AJAX checks authentication before handler');
$test(strpos($controller, 'if (!$auth->canAccessAdmin())') < strpos($controller, "\$mode === 'dashboard-cards-ajax'"), 'AJAX respects Core admin capability');
$test(strpos($controller, 'if ($auth->mustChangePassword()') < strpos($controller, "\$mode === 'dashboard-cards-ajax'"), 'AJAX refuses password-change-required accounts');
$test(str_contains($controller, "header('Allow: POST')"), 'AJAX rejects other HTTP methods');
$test(str_contains($controller, "admin_verify_csrf(\$_POST['csrf_token'] ?? null)"), 'AJAX requires existing CSRF check');
$test(str_contains($controller, "DashboardCards::validate(\$_POST['cards'] ?? null, \$available)"), 'AJAX uses exact Core whitelist validation');
$test(str_contains($controller, '$service->save((int)$auth->id(), $selection)'), 'Preferences saved to logged-in account only');
$test(!str_contains($controller, "\$_POST['user_id']"), 'User ID cannot be submitted from client');
$test(str_contains($controller, "'Content-Type: application/json; charset=utf-8'"), 'AJAX returns JSON only');
$test(str_contains($controller, "'Cache-Control: private, no-store'"), 'Private AJAX responses cannot enter public cache');
foreach ([403, 405, 422, 503] as $code) $test(str_contains($controller, "\$respond($code,"), "AJAX explicitly defines failure HTTP $code");
$test(str_contains($controller, "'ok' => true, 'cards' => \$cards"), 'Successful save returns canonical card data');
$test(str_contains($controller, 'admin_dashboard_data()') && str_contains($controller, '$service->extraCounts($selection, $activeModules)'), 'Counts come from Core/server metric providers');
$test(str_contains($controller, "'url' => \$option['url']") && str_contains($controller, "'label' => t(\$option['label'])"), 'Server returns only trusted URL and localized labels');
$test(str_contains($controller, "\$mode === 'dashboard-cards'"), 'Legacy/native POST endpoint preserved for no-JS fallback');

$test(str_contains($js, "[data-dashboard-cards]"), 'Core initializes only when cards are present');
$test(str_contains($js, "[data-dashboard-card-trigger]"), 'Each trigger wired individually');
$test(str_contains($js, "event.key === 'Escape'") && str_contains($js, "!openCard.contains(event.target)"), 'Escape and outside-click close controls');
$test(str_contains($js, "if (other >= 0) next[other] = next[slot]"), 'Selecting occupied metric swaps rather than duplicates');
$test(str_contains($js, 'new Set(next).size !== 4'), 'Client rejects invalid local duplicates');
$test(str_contains($js, "method: 'POST', credentials: 'same-origin', redirect: 'error'"), 'AJAX POST uses same-origin cookies and rejects redirects');
$test(str_contains($js, "new URLSearchParams({ csrf_token: csrf.value })"), 'AJAX submits authenticated CSRF token');
$test(str_contains($js, "body.append('cards[]', key)"), 'AJAX uses original cards[] POST payload');
$test(str_contains($js, "cardsRoot.setAttribute('aria-busy', 'true')"), 'Loading state is announced');
$test(str_contains($js, 'button.disabled = true;'), 'Concurrent card changes disabled during pending save');
$test(str_contains($js, "link.href = record.url"), 'Canonical destination updated live');
$test(str_contains($js, 'label.textContent = record.label') && str_contains($js, 'count.textContent = record.count'), 'Never inject JSON text as HTML');
$test(str_contains($js, 'template.content.cloneNode(true)'), 'Icons copied from Core-owned DOM templates');
$test(!str_contains(substr($js, strpos($js, '// RC5b:'), strpos($js, '// Getting Started is shown automatically') - strpos($js, '// RC5b:')), '.innerHTML'), 'AJAX block does not parse untrusted HTML');
$test(str_contains($js, "notice.hidden = false"), 'Network/save failures preserve existing cards and show feedback');
$test(str_contains($js, 'closeMenu(card, true)'), 'Save returns focus to trigger');
$test(!str_contains($js, '[data-dashboard-cards-toggle]'), 'Obsolete editor JS removed');
$test($css === $scss, 'Core Admin SCSS and compiled CSS remain synchronized');
$test(str_contains($css, '.admin-metric__menu[hidden]') && str_contains($css, '.admin-metric__settings:focus-visible'), 'Hidden menus and keyboard focus styled by Core');
$test(str_contains($css, '.admin-metric:nth-of-type(odd) .admin-metric__menu'), 'Mobile menu anchored to available horizontal space');
$test(str_contains($css, '.admin-metric__settings-icon'), 'Small Core icon uses shared styles');
$test(str_contains($layout, "'dashboard.cards_saving'") && str_contains($layout, "'dashboard.cards_save_failed'"), 'Live messages included in existing translation channel');
$tr = json_decode($read('core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
foreach (['dashboard.cards_change','dashboard.cards_pick','dashboard.cards_saving','dashboard.cards_save_failed'] as $key) {
    $test(isset($tr[$key]) && count(array_filter($tr[$key])) === 5, "All five translations provided: $key");
}
$test(is_file($root . '/database/updates/20261019_200_dashboard_cards.php'), 'RC5 migration reused unchanged');
$test(is_file($root . '/docs/releases/V2.5.0-RC5b-DASHBOARD-CARDS-UX-AJAX.md'), 'Scoped release documentation shipped');
echo "Dashboard Cards UX/AJAX RC5b: $count PASS / 0 FAIL (source-only)\n";
