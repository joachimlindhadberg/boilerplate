<?php

declare(strict_types=1);

/**
 * Gallery admin request controller. Composed into the GalleryModule entrypoint
 * so that the module registry keeps its established handler method and route.
 * GalleryRepository, validation helpers and frontend rendering stay unchanged.
 */
trait GalleryAdminController
{
    public function adminGalleries(array $runtime): void
    {
        $this->requireContext();
        $repository = $this->repository();
        $screen = strtolower(trim((string)($_GET['screen'] ?? '')));

        if ($screen === 'new' || $screen === 'edit') {
            $id = $screen === 'edit' ? max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0)) : 0;
            $existing = $id > 0 ? $repository->findGallery($id) : null;
            if ($id > 0 && !$existing) {
                admin_set_notice('Galleriet findes ikke længere.', 'error');
                admin_redirect('/admin/gallerier');
            }

            $gallery = $existing ?: ['name' => '', 'description' => '', 'status' => 'active', 'lightbox_enabled' => 1];
            $items = $existing ? $repository->itemsForGallery($id) : [];
            foreach ($items as &$item) {
                $item['public_url'] = $this->media()->publicUrl((string)($item['file_path'] ?? ''));
            }
            unset($item);
            $errors = [];

            if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
                if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
                    $errors['form'] = 'Sessionen er udløbet. Genindlæs siden og prøv igen.';
                } else {
                    $action = strtolower(trim((string)($_POST['gallery_action'] ?? 'save')));
                    if ($action === 'delete' && $id > 0) {
                        try {
                            $result = $repository->deleteGallery($id);
                            if (!empty($result['ok'])) admin_log_activity('deleted', 'gallery', $id, (string)($existing['name'] ?? ''));
                            if (empty($result['ok']) && (int)($result['usage_count'] ?? 0) > 0) {
                                $count = (int)$result['usage_count'];
                                admin_set_notice($count === 1 ? 'notice.gallery_in_use_one' : 'notice.gallery_in_use_many', 'error', ['count' => $count]);
                            } else {
                                admin_set_notice((string)$result['message'], !empty($result['ok']) ? 'success' : 'error');
                            }
                            admin_redirect(!empty($result['ok']) ? '/admin/gallerier' : '/admin/gallerier?screen=edit&id=' . $id);
                        } catch (Throwable $e) {
                            error_log('Gallery delete failed: ' . $e->getMessage());
                            admin_set_notice('Galleriet kunne ikke slettes. Prøv igen.', 'error');
                            admin_redirect('/admin/gallerier?screen=edit&id=' . $id);
                        }
                    }

                    [$gallery, $mediaIds, $items, $errors] = $this->normalizeAdminSubmission($_POST);
                    // Hidden legacy field: preserve old gallery-level state without surfacing it in UI.
                    $gallery['lightbox_enabled'] = (int)($existing['lightbox_enabled'] ?? 1);
                    if (!$errors) {
                        try {
                            $id = $repository->saveGallery($id, $gallery, $mediaIds);
                            admin_log_activity($screen === 'edit' ? 'updated' : 'created', 'gallery', $id, (string)($gallery['name'] ?? 'Gallery'));
                            admin_set_notice($screen === 'edit' ? 'Galleriet er gemt.' : 'Galleriet er oprettet.');
                            admin_redirect('/admin/gallerier?screen=edit&id=' . $id);
                        } catch (Throwable $e) {
                            error_log('Gallery save failed: ' . $e->getMessage());
                            $errors['form'] = 'Galleriet kunne ikke gemmes. Prøv igen.';
                        }
                    }
                }
            }

            $this->context->renderAdmin('admin/views/' . ($screen === 'edit' ? 'edit' : 'new') . '.php', [
                'user' => $runtime['user'] ?? null,
                'activeNav' => 'module:gallery',
                'documentTitle' => $screen === 'edit' ? 'Rediger galleri' : 'Nyt galleri',
                'galleryData' => $gallery,
                'items' => $items,
                'errors' => $errors,
                'galleryId' => $id,
                'isEdit' => $screen === 'edit',
                'usage' => $id > 0 ? $repository->pageBuilderUsage($id) : ['count' => 0, 'page_ids' => []],
                'notice' => admin_pull_notice(),
            ]);
            return;
        }

        if ($screen !== '') admin_redirect('/admin/gallerier');
        $search = trim((string)($_GET['q'] ?? ''));
        $search = $this->limitText($search, 150);
        $total = $repository->countFilteredGalleries($search);
        $paging = admin_crud_pagination($total);

        $this->context->renderAdmin('admin/views/index.php', [
            'user' => $runtime['user'] ?? null,
            'activeNav' => 'module:gallery',
            'documentTitle' => 'Gallerier',
            'galleries' => $repository->filteredGalleries($search, $paging['perPage'], $paging['offset']),
            'search' => $search,
            'total' => $total,
            'paging' => $paging,
            'notice' => admin_pull_notice(),
        ]);
    }
}
