<?php

declare(strict_types=1);

/**
 * Forms admin request handlers (controller).
 *
 * The CMS module registry invokes methods on FormsModule, so these handlers
 * are composed into the module via a trait. This preserves the route handler
 * names and the module's existing private repository/validation helpers.
 * Frontend form rendering and submission actions remain in FormsModule.
 */
trait FormsAdminController
{
    public function adminForms(array $runtime): void
    {
        $this->requireContext();
        $screen = strtolower(trim((string)($_GET['screen'] ?? '')));
        $repository = $this->repository();

        if ($screen === 'new' || $screen === 'edit') {
            $id = $screen === 'edit' ? max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0)) : 0;
            $existing = $id > 0 ? $repository->findForm($id) : null;
            if ($id > 0 && !$existing) {
                admin_set_notice('Formularen findes ikke længere.', 'error');
                admin_redirect('/admin/forms');
            }

            // A delete request is separate from the editor's save action.
            if ($screen === 'edit'
                && strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'
                && (string)($_POST['form_action'] ?? '') === 'delete') {
                $returnUrl = '/admin/forms?screen=edit&id=' . $id;
                if ($id < 1 || (int)($_POST['id'] ?? 0) !== $id
                    || !admin_verify_csrf($_POST['csrf_token'] ?? null)
                    || (string)($_POST['confirm_delete'] ?? '') !== 'permanent') {
                    admin_set_notice('forms:Formularen kunne ikke slettes. Genindlæs siden og prøv igen.', 'error');
                    admin_redirect($returnUrl);
                }
                try {
                    if (!$repository->deleteForm($id)) {
                        admin_set_notice('forms:Formularen findes ikke længere.', 'error');
                    } else {
                        admin_log_activity('deleted', 'form', $id, (string)($existing['name'] ?? 'Form'));
                        admin_set_notice('forms:Formularen er slettet.');
                        admin_redirect('/admin/forms');
                    }
                } catch (DomainException $e) {
                    admin_set_notice($e->getMessage(), 'error');
                } catch (Throwable $e) {
                    error_log('Forms delete failed: ' . $e->getMessage());
                    admin_set_notice('forms:Formularen kunne ikke slettes. Prøv igen.', 'error');
                }
                admin_redirect($returnUrl);
            }

            $form = $existing ?: $this->emptyAdminForm();
            $fields = $existing ? $repository->fieldsForForm($id) : [];
            $builderRows = $existing ? $repository->rowsForForm($id) : [];
            $errors = [];

            if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
                if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
                    $errors['form'] = 'Sessionen er udløbet. Genindlæs siden og prøv igen.';
                } else {
                    [$form, $fields, $errors, $builderRows] = $this->normalizeAdminSubmission($_POST);
                    if (!$errors) {
                        try {
                            $id = $repository->saveForm($id, $form, $fields, $builderRows);
                            admin_log_activity($screen === 'edit' ? 'updated' : 'created', 'form', $id, (string)($form['name'] ?? $form['title'] ?? 'Form'));
                            admin_set_notice($screen === 'edit' ? 'Formularen er gemt.' : 'Formularen er oprettet.');
                            admin_redirect('/admin/forms?screen=edit&id=' . $id);
                        } catch (Throwable $e) {
                            error_log('Forms save failed: ' . $e->getMessage());
                            $errors['form'] = 'Formularen kunne ikke gemmes. Prøv igen.';
                        }
                    }
                }
            }

            $this->context->renderAdmin('admin/views/' . ($screen === 'edit' ? 'edit' : 'new') . '.php', [
                'user' => $runtime['user'] ?? null,
                'activeNav' => 'module:forms',
                'documentTitle' => $screen === 'edit' ? 'Rediger formular' : 'Ny formular',
                'formData' => $form,
                'fields' => $fields,
                'builderRows' => $builderRows,
                'errors' => $errors,
                'formId' => $id,
                'deleteDependencies' => $screen === 'edit' ? $repository->formDeleteDependencies($id) : ['submissions' => 0, 'pages' => 0],
                'isEdit' => $screen === 'edit',
                'fieldTypes' => self::FIELD_TYPES,
                'formTemplates' => FormsTemplateCatalog::all(array_keys(self::FIELD_TYPES)),
                'publishedPages' => $this->publishedPages(),
                'notificationEmail' => $this->notificationEmail(),
                'notice' => admin_pull_notice(),
            ]);
            return;
        }

        if ($screen !== '') {
            admin_redirect('/admin/forms');
        }

        $query = $_GET['q'] ?? '';
        $search = trim(is_string($query) ? $query : '');
        $search = function_exists('mb_substr')
            ? mb_substr($search, 0, 200, 'UTF-8')
            : substr($search, 0, 200);
        // Status is a server-side GET filter, shared by tabs, search and pagination.
        $status = in_array((string)($_GET['status'] ?? ''), ['active', 'inactive'], true)
            ? (string)$_GET['status'] : '';
        $allForms = $repository->allForms();
        if ($search !== '') {
            $allForms = array_values(array_filter($allForms, static function (array $form) use ($search): bool {
                $name = (string)($form['name'] ?? '');
                return function_exists('mb_stripos')
                    ? mb_stripos($name, $search, 0, 'UTF-8') !== false
                    : stripos($name, $search) !== false;
            }));
        }
        // Counts are based on the full search result, never the visible page.
        $filterCounts = [
            '' => count($allForms),
            'active' => count(array_filter($allForms, static fn(array $form): bool => ($form['status'] ?? 'active') === 'active')),
            'inactive' => count(array_filter($allForms, static fn(array $form): bool => ($form['status'] ?? 'active') !== 'active')),
        ];
        if ($status !== '') {
            $allForms = array_values(array_filter($allForms, static fn(array $form): bool =>
                (($form['status'] ?? 'active') === 'active') === ($status === 'active')
            ));
        }
        $total = count($allForms);
        $pages = max(1, (int)ceil($total / 10));
        $page = max(1, min((int)($_GET['page'] ?? 1), $pages));
        $offset = ($page - 1) * 10;
        $visibleForms = array_slice($allForms, $offset, 10);
        $deleteDependenciesById = [];
        // Use the same authoritative checks as the form editor. Only the ten
        // visible records are inspected; the repository re-checks on POST.
        foreach ($visibleForms as $visibleForm) {
            $visibleId = (int)($visibleForm['id'] ?? 0);
            if ($visibleId > 0) {
                $deleteDependenciesById[$visibleId] = $repository->formDeleteDependencies($visibleId);
            }
        }

        $this->context->renderAdmin('admin/views/index.php', [
            'user' => $runtime['user'] ?? null,
            'activeNav' => 'module:forms',
            'documentTitle' => 'Forms',
            'forms' => $visibleForms,
            'deleteDependenciesById' => $deleteDependenciesById,
            'status' => $status,
            'filterCounts' => $filterCounts,
            'search' => $search,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($total, $offset + 10),
            'notice' => admin_pull_notice(),
        ]);
    }

    public function adminSettings(array $runtime): void
    {
        $this->requireContext();
        $errors = [];
        $form = $this->formSettings();

        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
                $errors['form'] = 'Sessionen er udløbet. Genindlæs siden og prøv igen.';
            } else {
                $form = [
                    'default_save_submissions' => isset($_POST['default_save_submissions']) ? 1 : 0,
                    'default_send_notification' => isset($_POST['default_send_notification']) ? 1 : 0,
                    'default_success_message' => $this->limitText(trim((string)($_POST['default_success_message'] ?? '')), 1000),
                    'rate_limit_count' => max(1, min(30, (int)($_POST['rate_limit_count'] ?? 6))),
                    'rate_limit_window_minutes' => max(1, min(60, (int)($_POST['rate_limit_window_minutes'] ?? 10))),
                ];
                if ($form['default_success_message'] === '') {
                    $errors['default_success_message'] = 'Standard kvitteringstekst skal udfyldes.';
                }

                if (!$errors) {
                    $settings = $this->settings();
                    $settings->set('forms_default_save_submissions', $form['default_save_submissions']);
                    $settings->set('forms_default_send_notification', $form['default_send_notification']);
                    $settings->set('forms_default_success_message', $form['default_success_message']);
                    $settings->set('forms_rate_limit_count', $form['rate_limit_count']);
                    $settings->set('forms_rate_limit_window_minutes', $form['rate_limit_window_minutes']);
                    admin_log_activity('updated', 'form_settings', null, 'Forms settings');
                    admin_set_notice('Forms-indstillinger er gemt.');
                    admin_redirect('/admin/forms/settings');
                }
            }
        }

        $this->context->renderAdmin('admin/views/settings.php', [
            'user' => $runtime['user'] ?? null,
            'activeNav' => 'module:forms',
            'documentTitle' => 'forms:Forms · Indstillinger',
            'formData' => $form,
            'errors' => $errors,
            'notice' => admin_pull_notice(),
        ]);
    }

    public function adminSubmissions(array $runtime): void
    {
        $this->requireContext();
        $repository = $this->repository();

        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
                admin_set_notice('Sessionen er udløbet. Genindlæs siden og prøv igen.', 'error');
            } else {
                $id = max(0, (int)($_POST['id'] ?? 0));
                $action = strtolower(trim((string)($_POST['submission_action'] ?? '')));
                $submission = $repository->findSubmission($id);
                if (!$submission) {
                    admin_set_notice('Indsendelsen findes ikke længere.', 'error');
                } elseif ($action === 'delete') {
                    $repository->deleteSubmission($id);
                    admin_log_activity('deleted', 'submission', $id, 'Submission #' . $id);
                    admin_set_notice('Indsendelsen er slettet.');
                } elseif ($action === 'mark_read') {
                    $repository->setSubmissionStatus($id, 'read');
                    admin_set_notice('Indsendelsen er markeret som læst.');
                } elseif ($action === 'mark_unread') {
                    $repository->setSubmissionStatus($id, 'new');
                    admin_set_notice('Indsendelsen er markeret som ulæst.');
                }
            }
            admin_redirect($this->safeAdminReturnUrl((string)($_POST['return_url'] ?? '/admin/forms/submissions')));
        }

        $formId = max(0, (int)($_GET['form_id'] ?? 0));
        if ($formId > 0 && !$repository->findForm($formId)) $formId = 0;
        $status = in_array((string)($_GET['status'] ?? ''), ['new', 'read'], true) ? (string)$_GET['status'] : '';
        $search = $this->limitText(trim((string)($_GET['q'] ?? '')), 150);
        // Keep direct form_id deep links even though the redundant select is gone.
        // Compute counts using the same repository filters as the actual table.
        $allCount = $repository->countSubmissions($formId, '', $search);
        $newCount = $repository->countSubmissions($formId, 'new', $search);
        $filterCounts = [
            '' => $allCount,
            'new' => $newCount,
            'read' => max(0, $allCount - $newCount),
        ];
        $total = $filterCounts[$status];
        $paging = admin_crud_pagination($total);

        $this->context->renderAdmin('admin/views/submissions.php', [
            'user' => $runtime['user'] ?? null,
            'activeNav' => 'module:forms:submissions',
            'documentTitle' => 'forms:Indsendelser',
            'submissions' => $repository->submissions($formId, $status, $search, $paging['perPage'], $paging['offset']),
            'paging' => $paging,
            'total' => $total,
            'forms' => $repository->allForms(),
            'selectedFormId' => $formId,
            'selectedStatus' => $status,
            'filterCounts' => $filterCounts,
            'search' => $search,
            'notice' => admin_pull_notice(),
        ]);
    }

    public function adminSubmission(array $runtime): void
    {
        $this->requireContext();
        $repository = $this->repository();
        $id = max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
        $submission = $repository->findSubmission($id);
        if (!$submission) {
            admin_set_notice('Indsendelsen findes ikke længere.', 'error');
            admin_redirect('/admin/forms/submissions');
        }

        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
                admin_set_notice('Sessionen er udløbet. Genindlæs siden og prøv igen.', 'error');
                admin_redirect('/admin/forms/submission?id=' . $id);
            }
            $action = strtolower(trim((string)($_POST['submission_action'] ?? '')));
            if ($action === 'delete') {
                $formId = (int)$submission['form_id'];
                $repository->deleteSubmission($id);
                admin_log_activity('deleted', 'submission', $id, 'Submission #' . $id);
                admin_set_notice('Indsendelsen er slettet.');
                admin_redirect('/admin/forms/submissions?form_id=' . $formId);
            }
            if ($action === 'mark_unread') {
                $repository->setSubmissionStatus($id, 'new');
                admin_set_notice('Indsendelsen er markeret som ulæst.');
                admin_redirect('/admin/forms/submissions?form_id=' . (int)$submission['form_id']);
            }
            if ($action === 'mark_read') {
                $repository->setSubmissionStatus($id, 'read');
            }
        }

        $repository->markSubmissionRead($id);
        $submission['status'] = 'read';

        $this->context->renderAdmin('admin/views/submission.php', [
            'user' => $runtime['user'] ?? null,
            'activeNav' => 'module:forms:submissions',
            'documentTitle' => 'Indsendelse',
            'submission' => $submission,
            'notice' => admin_pull_notice(),
        ]);
    }
}
