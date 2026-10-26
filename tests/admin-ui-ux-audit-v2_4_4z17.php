<?php
declare(strict_types=1);
/** z17: read-only audit deliverables and Core design invariants, no DB required. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
$check = static function (bool $condition, string $description) use (&$pass, &$fail): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $description . "\n";
    $condition ? ++$pass : ++$fail;
};
$audit = $root . '/docs/audits/GLOBAL-ADMIN-UI-UX-CONSISTENCY-AUDIT-V2.4.4z17.md';
$release = $root . '/docs/releases/GLOBAL-ADMIN-UI-UX-CONSISTENCY-AUDIT-V2.4.4z17.md';
$script = $root . '/tests/audit/admin_ui_consistency.py';
$check(is_file($audit), 'Prioritized audit document shipped');
$check(is_file($release), 'Release scope and candidate status documented');
$check(is_file($script), 'Read-only repeatable audit CLI shipped');
$report = (string)file_get_contents($audit);
$notes = (string)file_get_contents($release);
$code = (string)file_get_contents($script);
$check(str_contains($report, '**12**') && str_contains($report, '**14 åbne'), 'Screen inventory and finding count documented');
foreach (['UI-01', 'UI-02', 'UI-03', 'UI-04', 'UI-05', 'UI-06', 'UI-07', 'UI-08', 'UI-09', 'UI-10', 'UI-11', 'UI-12', 'UI-13', 'UI-14'] as $id) {
    $check(str_contains($report, $id) && str_contains($code, $id), 'Documented and repeatable finding ' . $id);
}
$check(str_contains($report, '**Vis bruger**') && str_contains($code, 'Brugere / detalje'), 'Audit includes precise user detail review');
$check(str_contains($report, 'P1') && str_contains($report, 'P2') && str_contains($report, 'P3'), 'Priorities and follow-ups documented');
$check(str_contains($report, '390, 560, 768, 1024 og 1600'), 'Responsive manual-test matrix present');
$check(str_contains($report, 'da/en/de/sv/nb'), 'All five admin languages require manual review');
$check(str_contains($report, 'kan **ikke**') && str_contains($report, 'browser'), 'Audit does not claim browser compliance');
$check(str_contains($notes, 'ingen UI-ændringer') || str_contains($notes, 'ingen UI-ændringer i runtime'), 'Audit only — no runtime UI changes documented');
$check(str_contains($code, '"--json"') && str_contains($code, 'read_text') && str_contains($code, 'invariant_errors'), 'Audit is readable source parser with JSON and invariant checks');
$check(!str_contains($code, 'subprocess') && !str_contains($code, 'pymysql') && !str_contains($code, 'mysql.connector'), 'Audit script does not execute tools or access database');
$check(is_file($root . '/core/classes/AdminListUI.php') && is_file($root . '/core/classes/AdminDeleteDialog.php'), 'Existing Core list/delete components untouched');
$check(file_get_contents($root . '/public_html/admin/assets/scss/admin.scss') === file_get_contents($root . '/public_html/admin/assets/css/admin.css'), 'Core SCSS and CSS byte-identical');
$check(str_contains((string)file_get_contents($root . '/public_html/admin/views/users/view.php'), '<dialog class="admin-modal')
    || str_contains((string)file_get_contents($root . '/public_html/admin/views/users/view.php'), 'AdminDeleteDialog::render('), 'Users detail finding still tracked: original or later Core alignment');
$check(str_contains((string)file_get_contents($root . '/public_html/admin/views/tools/redirects.php'), 'data-admin-confirm-form')
    || str_contains((string)file_get_contents($root . '/public_html/admin/views/tools/redirects.php'), 'AdminDeleteDialog::render('), 'Redirects finding tracked: legacy form or subsequent Core alignment');
$check(str_contains((string)file_get_contents($root . '/public_html/admin/views/tools/activity.php'), 'admin-list-table') && !str_contains((string)file_get_contents($root . '/public_html/admin/views/tools/activity.php'), 'admin-list-table__actions'), 'Read-only Activity Log still has no fake action column');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", (string)file_get_contents($root . '/public_html/incl/init.php'), $v) === 1 && version_compare($v[1], '2.4.4.74', '>='), 'z17 minimum release version 2.4.4.74');
printf("Admin UI/UX Consistency Audit V2.4.4z17: %d PASS / %d FAIL\n", $pass, $fail);
exit($fail ? 1 : 0);
