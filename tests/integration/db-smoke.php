<?php
declare(strict_types=1);
/** Dedicated synthetic DB integration smoke; writes only inside rollback. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(2); }
// Run identical guard in this process to prevent bypass through direct invocation.
require __DIR__ . '/fixture-guard.php';
// The guard defines ROOTPATH and autoloads Db, but closes its handle.
$passes = 0;
$assert = static function (bool $ok, string $label) use (&$passes): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    echo "PASS: {$label}\n";
    ++$passes;
};
try {
    $db = new Db();
    foreach (['users', 'roles', 'settings', 'pages', 'page_blocks', 'media', 'module_forms', 'module_form_rows', 'module_form_fields', 'module_form_submissions', 'module_galleries', 'module_gallery_items', 'user_dashboard_cards', 'schema_migrations'] as $table) {
        $exists = (int)$db->fetchValue('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', [$table]);
        $assert($exists === 1, 'table ' . $table);
    }
    $assert((int)$db->fetchValue('SELECT COUNT(*) FROM users') === 0, 'no real or synthetic user credentials');
    $assert((int)$db->fetchValue('SELECT COUNT(*) FROM pages WHERE page_title=?', ['Integrationstest for Boilerplate CMS']) === 1, 'synthetic page fixture');
    // Derive expected records from the exact shipped migration registry, not a
    // historical hard-coded count (RC4b Gallery + RC5 Dashboard added two).
    $migrationStatus = (new MigrationRunner($db))->status();
    $assert(count($migrationStatus) === 7, 'seven expected current PHP migrations discovered');
    $assert(count(array_filter($migrationStatus, static fn(array $m): bool => $m['state'] !== 'applied')) === 0,
        'all discovered fixture migrations applied with matching checksums');
    $assert((int)$db->fetchValue('SELECT COUNT(*) FROM schema_migrations') === count($migrationStatus),
        'migration registry has exactly all current records');
    $lightboxColumn = (int)$db->fetchValue("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='module_galleries' AND COLUMN_NAME='lightbox_enabled'");
    $assert($lightboxColumn === 1, 'Gallery Lightbox schema present');
    $dashboardFk = (int)$db->fetchValue("SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_dashboard_cards' AND REFERENCED_TABLE_NAME='users'");
    $assert($dashboardFk === 1, 'Dashboard Cards preferences linked to owning user');
    $assert((int)$db->fetchValue("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name LIKE 'media_ref_%'") === 9, 'media-reference triggers');
    $db->beginTransaction();
    try {
        $db->query('INSERT INTO module_forms (name, status) VALUES (?, ?)', ['Fixture Test Form', 'active']);
        $formId = $db->getInsertId();
        $assert($formId > 0, 'transaction created synthetic form');
        $db->query('INSERT INTO module_form_rows (form_id, row_order, row_layout) VALUES (?, ?, ?)', [$formId, 0, '50-50']);
        $db->query('INSERT INTO module_form_fields (form_id, field_key, label, field_type, row_layout, row_order, column_index, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$formId, 'first', 'First', 'text', '50-50', 0, 0, 0]);
        $db->query('INSERT INTO module_form_fields (form_id, field_key, label, field_type, row_layout, row_order, column_index, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$formId, 'last', 'Last', 'text', '50-50', 0, 1, 1]);
        $assert((int)$db->fetchValue('SELECT COUNT(*) FROM module_form_fields WHERE form_id=?', [$formId]) === 2, 'Forms 50/50 columns within transaction');
    } finally {
        $db->rollback();
    }
    $assert((int)$db->fetchValue('SELECT COUNT(*) FROM module_forms WHERE name=?', ['Fixture Test Form']) === 0, 'transaction rolled back');
    echo "PASS: fixture DB smoke ({$passes} checks)\n";
    $db->close();
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
