<?php

declare(strict_types=1);
/**
 * V2.4.4z7: read-only contract/source alignment check.
 * Never connects to a DB and never writes CMS data.
 */
$root = dirname(__DIR__);
$pass = 0;
$fail = 0;
$check = static function (bool $good, string $name) use (&$pass, &$fail): void {
    $good ? $pass++ : $fail++;
    echo ($good ? '[PASS] ' : '[FAIL] ') . $name . "\n";
};
$get = static fn(string $relative): string => (string)file_get_contents($root . '/' . $relative);
$contract = $get('docs/design/CORE-UI-STANDARD-V1.0.md');
$policy = $get('docs/design/DATABASE-AND-RELEASE-POLICY-V1.0.md');
$release = $get('docs/releases/CORE-UI-STANDARD-V1.0-V2.4.4z7.md');
$version = $get('public_html/incl/init.php');
preg_match("/define\('BOILERPLATE_VERSION', '([0-9.]+)'\)/", $version, $versionMatch);
$check(isset($versionMatch[1]) && version_compare($versionMatch[1], '2.4.4.57', '>='), 'Version marker retains Core UI standard >= 2.4.4.57');
$check(str_contains($contract, '# Core UI Standard V1.0'), 'Authoritative UI document exists');
$check(str_contains($policy, '# Database & Release Policy V1.0'), 'Authoritative DB/release policy exists');
$check(str_contains($release, 'Lokal testkandidat'), 'Release is explicitly a local candidate');
foreach (['ADMIN-MODULE-STYLE-CONTRACT.md', 'CORE-ADMIN-COMPONENTS-CONTRACT.md',
          'ADMIN-LISTS-TABLES-CONTRACT.md','CMS-UI-UX-DESIGN-CONTRACT.md',
          'ADMIN-MODULE-WORKSPACES-CONTRACT.md','CMS-DESIGN-SYSTEM-PHASES.md'] as $legacy) {
    $check(str_contains($get('docs/design/' . $legacy), 'CORE-UI-STANDARD-V1.0.md'), 'Supersession note: ' . $legacy);
}
foreach (['DATABASE-MIGRATIONS-MODULE-CONTRACT.md','QA-PRODUCTION-CONTRACT.md',
          'PLUGIN-MODULE-CONTRACT.md','ADMIN-CONTROLLER-VIEW-CONTRACT.md',
          'SCHEDULED-CONTENT-MODULE-CONTRACT.md'] as $legacy) {
    $check(str_contains($get('docs/design/' . $legacy), 'DATABASE-AND-RELEASE-POLICY-V1.0.md'), 'Packaging/migration note: ' . $legacy);
}
$lists = $get('docs/design/ADMIN-LISTS-TABLES-CONTRACT.md');
$design = $get('docs/design/CMS-UI-UX-DESIGN-CONTRACT.md');
$check(!str_contains($lists, 'Show a text-only **Edit** button'), 'Outdated text-only Edit rule removed');
$check(!str_contains($lists, 'Create/New controls are text-only'), 'Outdated text-only Create rule removed');
$check(!str_contains($design, 'skal kun have tekst'), 'Outdated Danish Ny/Opret rule removed');
$check(str_contains($lists, 'admin_icon_action_link()') && str_contains($lists, 'admin_icon_action_button()'), 'Core SVG action helpers are specified');
$check(str_contains($contract, '`Row`') && str_contains($contract, '`N kolonner`'), 'Future minimal Row header specified, not claimed implemented');
$check(str_contains($contract, 'IKKE implementeret'), 'Future row UI explicitly not yet implemented');
$check(str_contains($policy, 'schema_migrations') && str_contains($policy, 'ikke automatisk'), 'Manual SQL status limitation documented');
$check(str_contains($policy, '20261016_000_pages_author_audit.php'), 'Existing PHP migration explicitly protected');
$check(str_contains($policy, 'Udviklings-/lokal-test-ZIP') && str_contains($policy, 'Produktions-/deployment-ZIP'), 'Distinct ZIP policies documented');
$check(str_contains($policy, 'uploads') && str_contains($policy, '`.env`'), 'Private data and uploads excluded');
$check(is_file($root . '/database/updates/20261016_000_pages_author_audit.php'), 'Existing Pages PHP migration preserved');
$runner = $get('core/classes/MigrationRunner.php');
$check(str_contains($runner, "'/database/updates'") && str_contains($runner, "'/migrations'"), 'Existing Core/Module PHP runner is still present');
$check(str_contains($runner, "/*.php'"), 'Runner discovers PHP, not manual SQL');
$check(is_file($root . '/core/classes/AdminIcons.php'), 'Core SVG icon source exists');
$check(is_file($root . '/core/classes/AdminToggle.php'), 'Core toggle source exists');
$icons = $get('core/classes/AdminIcons.php');
$check(str_contains($icons, 'function actionLink') && str_contains($icons, 'function actionButton'), 'Core accessible icon actions exist');
$check(str_contains($icons, "'aria-label'"), 'Icon action accessible labels enforced');
$scss = $get('public_html/admin/assets/scss/admin.scss');
$css = $get('public_html/admin/assets/css/admin.css');
$check($scss === $css, 'Core SCSS and compiled CSS remain byte-identical');
$check(str_contains($scss, '--admin-radius: 3px') && str_contains($scss, '--admin-sidebar-bg: #ffffff'), 'Actual Core radius and sidebar are documented accurately');
$pages = $get('public_html/admin/views/pages/index.php');
$check(str_contains($pages, "admin_icon('plus', 'admin-button__icon')"), 'Pages current text + plus behavior preserved');
// z10: Pages migrated from per-list helper calls to Core AdminListUI, which delegates to the same SVG icon actions.
$pagesActionContract = (str_contains($pages, 'admin_icon_action_link(') && str_contains($pages, 'admin_icon_action_button('))
    || (str_contains($pages, 'AdminListUI::actionGroup(')
        && str_contains($get('core/classes/AdminListUI.php'), 'AdminIcons::actionLink(')
        && str_contains($get('core/classes/AdminListUI.php'), 'AdminIcons::actionButton('));
$check($pagesActionContract, 'Pages retains Core SVG table actions (directly or via AdminListUI)');
$check(str_contains($contract, 'da, en, de, sv, nb'), 'Five admin languages documented');
$check(str_contains($contract, 'Frontend følger fortsat') && str_contains($contract, 'FRONTEND-CONTRACT.md'), 'Frontend theme ownership reinforced');
$check(str_contains($release, 'Ingen funktionelle ændringer'), 'Scope of doc-only release explicit');
$check(str_contains($release, 'ingen SQL-fil eller databaseimport'), 'No SQL requirement for this release');
$check(str_contains($policy, 'tests/') && str_contains($policy, 'fixtures'), 'Test/fixture rules defined');
$check(str_contains($contract, 'CSRF') && str_contains($contract, 'capability'), 'Server-side security contract retained');
echo "Core UI Standard V1.0: {$pass} PASS / {$fail} FAIL\n";
exit($fail ? 1 : 0);
