<?php
/**
 * V2.4.4z21 — real cross-module repository/SQL workflows, run ONLY against
 * the separately provisioned synthetic integration database.
 *
 * No browser login, cron, mail, filesystem uploads, or production data.
 * Every write is wrapped in one InnoDB transaction that is ALWAYS rolled back.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(2); }
require __DIR__ . '/fixture-guard.php'; // effective DB+account+marker verified BEFORE writes
require_once ROOTPATH . '/modules/forms/FormsRepository.php';
require_once ROOTPATH . '/modules/gallery/GalleryRepository.php';

$passes = 0;
$check = static function (bool $condition, string $label) use (&$passes): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    ++$passes;
    echo 'PASS: workflow ' . $label . "\n";
};

$db = null;
$inside = false;
$initial = [];
$tables = [
    'pages', 'page_blocks', 'page_schedules', 'navigation_items', 'redirects',
    'module_forms', 'module_form_rows', 'module_form_fields', 'module_form_submissions',
    'module_galleries', 'module_gallery_items', 'media', 'media_reference_index', 'user_dashboard_cards',
];
try {
    $db = new Db();
    foreach ($tables as $table) {
        // All names are fixed literals from this file, never user inputs.
        $initial[$table] = (int)$db->fetchValue('SELECT COUNT(*) FROM `' . $table . '`');
    }
    $initialState = $db->fetchArray('SELECT dirty, rebuilt_at FROM media_reference_state WHERE id=1');
    $check(count($initialState) === 1, 'media reference state row present');
    $check((int)$db->fetchValue('SELECT COUNT(*) FROM users') === 0, 'fixture contains no user credentials');
    $migrationStatus = (new MigrationRunner($db))->status();
    $check(count($migrationStatus) === 7 && count(array_filter($migrationStatus,
        static fn(array $m): bool => $m['state'] !== 'applied')) === 0,
        'seven current fixture migrations have valid checksums');
    $check((int)$db->fetchValue("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME LIKE 'media_ref_%'") === 9, 'reference triggers installed');

    $db->beginTransaction();
    $inside = true;
    // Synthetic user rows exist ONLY inside this rolled-back transaction.
    // Password value is explicitly invalid for login (no usable credentials).
    $users = [];
    foreach (['first', 'second'] as $label) {
        $db->query('INSERT INTO users (role_id, firstname, lastname, email, password) VALUES (1, ?, ?, ?, ?)',
            ['QA', $label, 'qa1-' . $label . '@invalid.example', '*not-a-login-hash*']);
        $users[] = $db->getInsertId();
    }
    $cards = new DashboardCards($db);
    $options = DashboardCards::available('admin', []);
    $defaults = DashboardCards::sanitize(null, $options);
    $check($cards->load($users[0], $options) === $defaults, 'new dashboard user receives defaults');
    $first = ['media', 'published', 'drafts', 'scheduled'];
    $second = ['drafts', 'scheduled', 'media', 'pages_total'];
    $cards->save($users[0], DashboardCards::validate($first, $options));
    $cards->save($users[1], DashboardCards::validate($second, $options));
    $check($cards->load($users[0], $options) === $first, 'dashboard cards persist per first user');
    $check($cards->load($users[1], $options) === $second, 'dashboard cards persist per second user');
    $db->query('DELETE FROM users WHERE id = ?', [$users[0]]);
    $check((int)$db->fetchValue('SELECT COUNT(*) FROM user_dashboard_cards WHERE user_id = ?', [$users[0]]) === 0,
        'deleting a user cascades dashboard preferences');
    // All identifiers/paths are synthetic, and no matching file is created.
    $db->query("INSERT INTO pages (page_title, page_slug, content_mode, page_status, show_in_menu) VALUES (?, ?, 'builder', 'draft', 0)",
        ['z21 synthetic workflow page', 'z21-only-workflow']);
    $pageId = $db->getInsertId();
    $check($pageId > 0, 'Page Builder page created');

    // Forms: record + 50/50 row + two fields + submission state changes.
    $db->query("INSERT INTO module_forms (name, status, save_submissions, send_notification, send_confirmation) VALUES (?, 'active', 1, 0, 0)",
        ['z21 synthetic form']);
    $formId = $db->getInsertId();
    $db->query('INSERT INTO module_form_rows (form_id, row_order, row_layout) VALUES (?, 0, ?)', [$formId, '50-50']);
    $db->query("INSERT INTO module_form_fields (form_id, field_key, label, field_type, is_required, row_layout, row_order, column_index, sort_order) VALUES (?, ?, ?, ?, 1, '50-50', 0, 0, 0)",
        [$formId, 'name', 'Navn', 'text']);
    $db->query("INSERT INTO module_form_fields (form_id, field_key, label, field_type, is_required, row_layout, row_order, column_index, sort_order) VALUES (?, ?, ?, ?, 0, '50-50', 0, 1, 1)",
        [$formId, 'message', 'Besked', 'textarea']);
    $forms = new FormsRepository($db);
    $check((int)$forms->findForm($formId, true)['id'] === $formId, 'Forms lookup returns active form');
    $check(count($forms->fieldsForForm($formId)) === 2, 'Forms editor reloads both fields');
    $rows = $forms->rowsForForm($formId);
    $check(count($rows) === 1 && $rows[0]['layout'] === '50-50', 'Forms editor reloads saved row layout');
    $check(in_array($formId, array_column($forms->activeForms(), 'id'), false), 'Page Builder can select active form');
    $db->query("UPDATE module_forms SET status='inactive' WHERE id=?", [$formId]);
    $check($forms->findForm($formId, true) === null, 'inactive Form blocked from active lookup');
    $db->query("UPDATE module_forms SET status='active' WHERE id=?", [$formId]);
    $check($forms->findForm($formId, true) !== null, 'reactivated Form selectable again');

    // Gallery: Media library metadata + ordered images + active lookup.
    $media = new MediaLibrary();
    $ids = [];
    foreach (['first', 'second'] as $name) {
        $path = '/z21-test-no-file/' . $name . '.png';
        $db->query("INSERT INTO media (filename, original_filename, file_path, mime_type, media_kind, title) VALUES (?, ?, ?, 'image/png', 'image', ?)",
            [$name . '.png', $name . '.png', $path, 'z21 synthetic ' . $name]);
        $ids[] = $db->getInsertId();
    }
    $check($media->find($ids[0]) !== null, 'Media library retrieves synthetic asset');
    $check(count($media->getPickerImages('z21 synthetic')) === 2, 'Gallery picker finds two synthetic images');
    $db->query("INSERT INTO module_galleries (name, status) VALUES (?, 'active')", ['z21 synthetic gallery']);
    $galleryId = $db->getInsertId();
    $db->query('INSERT INTO module_gallery_items (gallery_id, media_id, sort_order) VALUES (?, ?, 0)', [$galleryId, $ids[0]]);
    $db->query('INSERT INTO module_gallery_items (gallery_id, media_id, sort_order) VALUES (?, ?, 1)', [$galleryId, $ids[1]]);
    $galleries = new GalleryRepository($db);
    $items = $galleries->itemsForGallery($galleryId);
    $check(count($items) === 2 && (int)$items[0]['media_id'] === $ids[0] && (int)$items[1]['media_id'] === $ids[1], 'Gallery preserves ordered Media references');
    $check((int)$galleries->findGallery($galleryId, true)['id'] === $galleryId, 'Page Builder can resolve active Gallery');
    $check((int)$galleries->findGallery($galleryId, true)['lightbox_enabled'] === 0,
        'Gallery defaults to no Lightbox');
    $db->query('UPDATE module_galleries SET lightbox_enabled = 1 WHERE id = ?', [$galleryId]);
    $check((int)$galleries->findGallery($galleryId, true)['lightbox_enabled'] === 1,
        'Gallery Lightbox choice round-trips through repository');

    // Page Builder component usage — match real repository component IDs.
    $content = [
        'columns' => [[ 'children' => [
            ['component' => 'form-' . $formId],
            ['component' => 'gallery:gallery-' . $galleryId],
            ['block' => 'image', 'image_id' => $ids[0]],
        ]]],
    ];
    $db->query("INSERT INTO page_blocks (page_id, block_type, block_data, sort_order, status) VALUES (?, 'grid', ?, 0, 'hidden')",
        [$pageId, json_encode($content, JSON_THROW_ON_ERROR)]);
    $formUsage = $forms->formDeleteDependencies($formId);
    $check($formUsage['pages'] === 1 && $formUsage['submissions'] === 0, 'Forms deletion detects hidden Page Builder usage');
    $galleryUsage = $galleries->pageBuilderUsage($galleryId);
    $check($galleryUsage['count'] === 1 && in_array($pageId, $galleryUsage['page_ids'], true), 'Gallery deletion detects Page Builder usage');
    $index = new MediaReferenceIndex($db);
    $check($index->pageIdsForMedia($ids[0]) === [$pageId], 'media reference triggers rebuild hidden Page Builder index');
    $mediaUsage = $media->usage((array)$media->find($ids[0]));
    $check($mediaUsage['count'] >= 2 && in_array($pageId, $mediaUsage['page_ids'], true), 'Media deletion guarded by Page Builder + Gallery references');
    // Inactive gallery media must remain protected.
    $db->query("UPDATE module_galleries SET status='inactive' WHERE id=?", [$galleryId]);
    $check($galleries->findGallery($galleryId, true) === null, 'inactive Gallery excluded from active lookup');
    $check($media->usage((array)$media->find($ids[1]))['count'] >= 1, 'inactive Gallery still protects referenced Media');

    $submissionId = $forms->storeSubmission($formId, [['key' => 'name', 'value' => 'Syntetisk']], 'https://boilerplate.test/test-only');
    $check($submissionId > 0 && $forms->countSubmissions($formId, 'new') === 1, 'Forms submission is stored and listed as new');
    $check(($forms->findSubmission($submissionId)['data'][0]['value'] ?? '') === 'Syntetisk', 'Forms detail decodes submission data');
    $check($forms->formDeleteDependencies($formId)['submissions'] === 1, 'Form deletion detects stored submissions');
    $forms->markSubmissionRead($submissionId);
    $check($forms->countSubmissions($formId, 'read') === 1, 'submission read status propagated to list');
    $check($forms->deleteSubmission($submissionId) && $forms->countSubmissions($formId) === 0, 'submission deletion updates listing');
    $check($forms->formDeleteDependencies($formId)['pages'] === 1, 'Form remains protected by Page Builder after submission removal');

    // Cross-Core: existing navigation and redirect lookup + scheduled page.
    $db->query("INSERT INTO navigation_items (menu_id, type, page_id, label, sort_order) VALUES (1, 'page', ?, ?, 990)",
        [$pageId, 'z21 synthetic page link']);
    $check((int)$db->fetchValue('SELECT COUNT(*) FROM navigation_items WHERE page_id=?', [$pageId]) === 1, 'Navigation references created page');
    $db->query("INSERT INTO redirects (source_path, target_url, redirect_type, page_id, is_active, is_automatic) VALUES (?, ?, 301, ?, 1, 0)",
        ['/z21-integration-test-only', '/z21-only-workflow', $pageId]);
    $check((int)$db->fetchValue('SELECT COUNT(*) FROM redirects WHERE page_id=? AND is_active=1', [$pageId]) === 1, 'Redirect references same page');
    $db->query('INSERT INTO page_schedules (page_id, publish_at_utc, updated_at) VALUES (?, ?, UTC_TIMESTAMP())', [$pageId, '2099-01-01 08:00:00']);
    $check((int)$db->fetchValue('SELECT COUNT(*) FROM page_schedules WHERE page_id=? AND publish_at_utc > UTC_TIMESTAMP()', [$pageId]) === 1, 'Scheduled Content detects future publish date');
    $check((int)$db->fetchValue('SELECT COUNT(*) FROM pages WHERE id=? AND page_status=?', [$pageId, 'draft']) === 1, 'scheduled page stays draft (no cron executed)');

    // Roll back ALL synthetic modifications, including reference-index rebuild.
    $db->rollback();
    $inside = false;
    foreach ($initial as $table => $count) {
        $check((int)$db->fetchValue('SELECT COUNT(*) FROM `' . $table . '`') === $count, 'rollback preserved row count in ' . $table);
    }
    $check($db->fetchArray('SELECT dirty, rebuilt_at FROM media_reference_state WHERE id=1') === $initialState, 'media index state also restored by rollback');
    $check((int)$db->fetchValue('SELECT COUNT(*) FROM pages WHERE page_slug=?', ['z21-only-workflow']) === 0, 'synthetic page is absent after rollback');
    $check((int)$db->fetchValue('SELECT COUNT(*) FROM users') === 0,
        'synthetic accounts fully rolled back');
    $check((int)$db->fetchValue('SELECT COUNT(*) FROM user_dashboard_cards') === 0,
        'dashboard preferences fully rolled back');
    echo 'PASS: cross-module integration workflows (' . $passes . " checks, no committed test records)\n";
    $db->close();
} catch (Throwable $error) {
    if ($inside && $db instanceof Db) {
        try { $db->rollback(); } catch (Throwable $rollbackError) {
            fwrite(STDERR, 'FAIL: rollback could not be confirmed: ' . $rollbackError->getMessage() . "\n");
        }
    }
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
