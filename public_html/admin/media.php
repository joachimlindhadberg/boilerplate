<?php

declare(strict_types=1);

const ADMIN_REQUIRED_CAPABILITY = 'media.manage';
require_once __DIR__ . '/incl/module-bootstrap.php';

$mediaLibrary = new MediaLibrary();
$mode = strtolower(trim((string)($_GET['mode'] ?? '')));

if ($mode === 'picker') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        exit;
    }

    $search = trim((string)($_GET['q'] ?? ''));
    $items = array_map(static function (array $item): array {
        return [
            'id' => (int)$item['id'],
            'url' => (string)($item['public_url'] ?? $item['file_path']),
            'filename' => (string)$item['original_filename'],
            'title' => (string)($item['title'] ?? ''),
            'alt' => (string)($item['alt_text'] ?? ''),
            'width' => $item['width'] !== null ? (int)$item['width'] : null,
            'height' => $item['height'] !== null ? (int)$item['height'] : null,
            'size' => (int)$item['file_size'],
        ];
    }, $mediaLibrary->getPickerImages($search));

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (admin_request_exceeds_post_max_size()) {
        $postMax = admin_ini_size_bytes(ini_get('post_max_size'));
        $limitLabel = $postMax > 0 ? admin_format_bytes($postMax) : 'serverens grænse';
        admin_set_notice('notice.media_request_too_large', 'error', ['size' => $limitLabel]);
        admin_redirect('/admin/medier');
    }

    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        admin_set_notice('Sessionen er udløbet. Genindlæs siden og prøv igen.', 'error');
        admin_redirect('/admin/medier');
    }

    if ($mode === 'upload') {
        try {
            $uploaded = $mediaLibrary->uploadMany((array)($_FILES['media_files'] ?? []), $auth->id());
            $count = count($uploaded);
            foreach ($uploaded as $asset) {
                if (is_array($asset)) admin_log_activity('uploaded', 'media', (int)($asset['id'] ?? 0), (string)($asset['original_filename'] ?? $asset['filename'] ?? 'Media'));
            }
            admin_set_notice($count === 1 ? 'notice.media_upload_one' : 'notice.media_upload_many', 'success', ['count' => $count]);
        } catch (Throwable $e) {
            error_log('Media upload: ' . $e->getMessage());
            $uploadMessage = $e instanceof RuntimeException ? $e->getMessage() : 'Uploaden mislykkedes. Prøv igen.';
            // Localize the two filename-bearing validation errors without
            // changing the data-layer exceptions or exposing raw file names in HTML.
            if (preg_match('/^Filen (.+) er større end 100 MB\.$/u', $uploadMessage, $match)) {
                admin_set_notice('notice.media_file_too_large', 'error', ['filename' => $match[1]]);
            } elseif (preg_match('/^Filtypen for (.+) understøttes ikke\.$/u', $uploadMessage, $match)) {
                admin_set_notice('notice.media_file_type_unsupported', 'error', ['filename' => $match[1]]);
            } else {
                admin_set_notice($uploadMessage, 'error');
            }
        }
        admin_redirect('/admin/medier');
    }

    if ($mode === 'update') {
        $id = max(0, (int)($_POST['id'] ?? 0));
        try {
            if (!$mediaLibrary->updateMetadata($id, $_POST)) {
                admin_set_notice('Mediet findes ikke længere.', 'error');
            } else {
                admin_log_activity('updated', 'media', $id, (string)($_POST['title'] ?? 'Media #' . $id));
                admin_set_notice('Medieoplysningerne er gemt.');
            }
        } catch (Throwable $e) {
            error_log('Media metadata update: ' . $e->getMessage());
            admin_set_notice('Medieoplysningerne kunne ikke gemmes.', 'error');
        }
        admin_redirect('/admin/medier');
    }

    if ($mode === 'delete') {
        $id = max(0, (int)($_POST['id'] ?? 0));
        try {
            $record = $mediaLibrary->find($id);
            $result = $mediaLibrary->delete($id);
            if (!empty($result['ok'])) admin_log_activity('deleted', 'media', $id, (string)($record['original_filename'] ?? 'Media #' . $id));
            if (empty($result['ok']) && (int)($result['usage']['count'] ?? 0) > 0) {
                $count = (int)$result['usage']['count'];
                admin_set_notice($count === 1 ? 'notice.media_in_use_one' : 'notice.media_in_use_many', 'error', ['count' => $count]);
            } else {
                admin_set_notice((string)($result['message'] ?? (!empty($result['ok']) ? 'Mediet er slettet.' : 'Mediet kunne ikke slettes.')), !empty($result['ok']) ? 'success' : 'error');
            }
        } catch (Throwable $e) {
            error_log('Media delete: ' . $e->getMessage());
            admin_set_notice('Mediet kunne ikke slettes. Prøv igen.', 'error');
        }
        admin_redirect('/admin/medier');
    }

    admin_redirect('/admin/medier');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    admin_redirect('/admin/medier');
}

admin_render('media/index', [
    'user' => $auth->user(),
    'activeNav' => 'media',
    'mediaData' => admin_media_data(),
    'notice' => admin_pull_notice(),
]);
