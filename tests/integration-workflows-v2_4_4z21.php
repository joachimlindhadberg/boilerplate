<?php
declare(strict_types=1);
/** Read-only regression for isolated synthetic workflow runner + boundaries. */
$root = dirname(__DIR__);
$count = 0;
$check = static function (bool $ok, string $name) use (&$count): void {
    if (!$ok) { fwrite(STDERR, 'FAIL: z21 ' . $name . "\n"); exit(1); }
    ++$count;
    echo 'PASS: z21 ' . $name . "\n";
};
$runnerFile = $root . '/tests/integration/run.sh';
$workflowFile = $root . '/tests/integration/workflows.php';
$runner = (string)file_get_contents($runnerFile);
$workflow = (string)file_get_contents($workflowFile);
$fixture = (string)file_get_contents($root . '/tests/integration/fixture-guard.php');
$schema = (string)file_get_contents($root . '/tests/integration/fixtures/base-schema.sql');
$check(is_file($workflowFile) && is_file($runnerFile), 'test harness files available');
$check(str_contains($workflow, "require __DIR__ . '/fixture-guard.php'"), 'fixture guard is required');
$check(strpos($workflow, '/fixture-guard.php') < strpos($workflow, '$db->beginTransaction()'), 'guard before first transaction');
$check(str_contains($fixture, "'DB_NAME' => 'boilerplate_integration_only'") && str_contains($fixture, "'DB_USER' => 'cms_test_only'"), 'dedicated DB/user enforced');
$check(str_contains($fixture, 'SELECT CURRENT_USER()') && str_contains($fixture, 'cms_integration_marker'), 'real effective DB credentials and marker verified');
$check(str_contains($runner, 'verify-fixture)') && str_contains($runner, '"$PHP_BIN" tests/integration/workflows.php'), 'fixture runner invokes integration workflow');
$fixtureBranch = substr($runner, strpos($runner, 'fixture_db() {'), strpos($runner, "\ncase \"\${1:-}\" in") - strpos($runner, 'fixture_db() {'));
$check(strpos($fixtureBranch, 'fixture-guard.php') < strpos($fixtureBranch, 'migrate.php --apply'), 'runner guards before migrations');
$check(strpos($fixtureBranch, 'db-smoke.php') < strpos($fixtureBranch, 'workflows.php'), 'schema smoke before workflow test');
$codeBranch = substr($runner, strpos($runner, 'code_tests() {'), strpos($runner, 'normal_db() {') - strpos($runner, 'code_tests() {'));
$check(!str_contains($codeBranch, 'workflows.php'), 'regular verify code tests never write fixtures');
$check(!str_contains(substr($runner, strpos($runner, 'normal_db() {'), strpos($runner, 'fixture_db() {') - strpos($runner, 'normal_db() {')), 'workflows.php'), 'normal database checks never invoke write workflow');
$check(str_contains($workflow, '$db->beginTransaction();') && str_contains($workflow, '$db->rollback();'), 'workflow encloses writes in explicit transaction');
$check(str_contains($workflow, 'if ($inside && $db instanceof Db)') && str_contains($workflow, '$db->rollback();'), 'error path attempts rollback');
$check(!str_contains($workflow, '$db->commit(') && !preg_match('/\b(?:CREATE|DROP|TRUNCATE|ALTER)\s+TABLE\b/i', $workflow), 'workflow has no commit or schema-changing DDL');
$check(!str_contains($workflow, 'mail(') && !str_contains($workflow, 'uploadMany(') && !str_contains($workflow, '->delete('), 'no real email, uploads or filesystem deletion');
$check(str_contains($workflow, "'pages', 'page_blocks', 'page_schedules'") && str_contains($workflow, "'media_reference_index'"), 'cross-component rollback row counts tracked');
$check(str_contains($workflow, "'synthetic page is absent after rollback'"), 'post-rollback absence check');
$check(str_contains($workflow, 'FormsRepository($db)') && str_contains($workflow, 'formDeleteDependencies('), 'real Forms repository + Page Builder dependencies');
$check(str_contains($workflow, 'storeSubmission(') && str_contains($workflow, 'markSubmissionRead(') && str_contains($workflow, 'deleteSubmission('), 'Forms submission workflow covered');
$check(str_contains($workflow, 'GalleryRepository($db)') && str_contains($workflow, 'pageBuilderUsage('), 'Gallery/Page Builder relationship covered');
$check(str_contains($workflow, 'MediaReferenceIndex($db)') && str_contains($workflow, '->usage('), 'Media index and deletion guard covered');
$check(str_contains($workflow, 'navigation_items') && str_contains($workflow, 'redirects') && str_contains($workflow, 'page_schedules'), 'navigation, redirect and scheduling joins covered');
$check(str_contains($workflow, 'status=\'inactive\'') && str_contains($workflow, 'inactive Gallery still protects referenced Media'), 'disabled module media references remain guarded');
foreach (['redirects', 'page_schedules', 'page_blocks', 'module_gallery_items', 'module_form_submissions'] as $table) {
    $check(str_contains($schema, 'CREATE TABLE `' . $table . '`'), 'synthetic schema includes ' . $table);
}
$init = (string)file_get_contents($root . '/public_html/incl/init.php');
$check(preg_match("/define\('BOILERPLATE_VERSION', '([0-9.]+)'\)/", $init, $versionMatch) === 1 && version_compare($versionMatch[1], '2.4.4.107', '>='), 'version >= 2.4.4.107');
$check((string)file_get_contents($root . '/public_html/admin/assets/scss/admin.scss') === (string)file_get_contents($root . '/public_html/admin/assets/css/admin.css'), 'Core SCSS/CSS remain byte-identical');
$check(is_file($root . '/docs/releases/INTEGRATION-WORKFLOW-TESTING-V2.4.4z21.md'), 'release notes shipped');
// Negative guard test: process cannot enter synthetic workflow with an unknown DB.
// This is safe even if the developer's shell happens to have fixture credentials.
$php = PHP_BINARY;
$cmd = 'APP_ENV=development DB_NAME=never_a_test DB_USER=never_a_test DB_PASS=invalid DB_HOST=127.0.0.1 '
    . escapeshellarg($php) . ' ' . escapeshellarg($workflowFile) . ' 2>&1';
$output = [];
$exit = 0;
exec($cmd, $output, $exit);
$check($exit === 2 && str_contains(implode("\n", $output), 'dedicated fixture environment'), 'wrong database cannot execute workflow');
echo "Integration workflow contract z21: {$count} PASS / 0 FAIL\n";
