<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/classes/ModuleAdminNavigationBadgeProviderInterface.php';
require_once __DIR__ . '/FormsRepository.php';
require_once __DIR__ . '/FormsTemplateCatalog.php';
require_once __DIR__ . '/admin/controllers/FormsAdminController.php';

final class FormsModule implements ModuleInterface, ModuleComponentProviderInterface, ModuleFrontendActionInterface, ModuleAdminNavigationBadgeProviderInterface
{
    use FormsAdminController;

    private ?ModuleContext $context = null;
    private ?FormsRepository $repository = null;
    private ?Settings $settings = null;

    private const FIELD_TYPES = [
        'text' => 'Tekst',
        'email' => 'E-mail',
        'tel' => 'Telefon',
        'textarea' => 'Tekstområde',
        'select' => 'Dropdown',
        'radio' => 'Radio-knapper',
        'checkbox' => 'Checkbox',
    ];

    public function boot(ModuleContext $context): void
    {
        $this->context = $context;
        $this->repository = new FormsRepository();
        $this->settings = new Settings();
        $this->ensureSession();
    }

    public function adminMenu(): array
    {
        // Core inserts each registration as a standalone item in the GLOBAL
        // admin menu. Both entries are available only while Forms is active.
        return [
            [
                'label' => 'Forms',
                'route' => 'forms',
                'icon' => 'mail',
                'capability' => 'content.manage',
                'key' => 'module:forms',
            ],
            [
                'label' => 'forms:Indsendelser',
                'route' => 'forms/submissions',
                'icon' => 'inbox',
                'capability' => 'content.manage',
                'key' => 'module:forms:submissions',
                'badge_label' => 'forms:Ulæste indsendelser',
            ],
        ];
    }

    /** Count all unread saved submissions only when the menu is authorized. */
    public function adminNavigationBadges(array $allowedKeys): array
    {
        if (!in_array('module:forms:submissions', $allowedKeys, true)) {
            return [];
        }
        $count = $this->repository()->countSubmissions(0, 'new');
        return $count > 0 ? ['module:forms:submissions' => $count] : [];
    }

    public function adminRoutes(): array
    {
        return [
            [
                'route' => 'forms',
                'methods' => ['GET', 'POST'],
                'capability' => 'content.manage',
                'handler' => 'adminForms',
                'nav_key' => 'module:forms',
            ],
            [
                'route' => 'forms/submissions',
                'methods' => ['GET', 'POST'],
                'capability' => 'content.manage',
                'handler' => 'adminSubmissions',
                'nav_key' => 'module:forms:submissions',
            ],
            [
                'route' => 'forms/submission',
                'methods' => ['GET', 'POST'],
                'capability' => 'content.manage',
                'handler' => 'adminSubmission',
                'nav_key' => 'module:forms:submissions',
            ],
            [
                'route' => 'forms/settings',
                'methods' => ['GET', 'POST'],
                'capability' => 'content.manage',
                'handler' => 'adminSettings',
                'nav_key' => 'module:forms',
            ],
        ];
    }

    public function componentOptions(): array
    {
        $options = [];
        foreach ($this->repository()->activeForms() as $form) {
            $id = (int)($form['id'] ?? 0);
            if ($id > 0) {
                $options['form-' . $id] = 'Formular · ' . (string)($form['name'] ?? ('#' . $id));
            }
        }
        return $options;
    }

    public function renderComponent(string $component, array $modifiers = []): bool
    {
        if (!preg_match('/^form-([1-9][0-9]*)$/', $component, $match)) {
            return false;
        }

        $formId = (int)$match[1];
        $form = $this->repository()->findForm($formId, true);
        if (!$form) {
            return true;
        }

        $fields = $this->repository()->fieldsForForm($formId);
        if ($fields) $this->renderFrontendForm($form, $fields);
        return true;
    }


    public function frontendActions(): array
    {
        return [[
            'action' => 'submit',
            'methods' => ['POST'],
            'handler' => 'submitFrontendForm',
        ]];
    }

    public function submitFrontendForm(array $runtime): void
    {
        $formId = max(0, (int)($_POST['form_id'] ?? 0));
        $form = $this->repository()->findForm($formId, true);
        $returnUrl = $this->safeReturnUrl((string)($_POST['return_url'] ?? '/'));

        if (function_exists('maintenance_mode_enabled') && maintenance_mode_enabled()) {
            $this->setFrontendFlash($formId, ['errors' => ['_form' => 'Formularen er midlertidigt lukket på grund af vedligeholdelse.']]);
            $this->redirectFrontend($returnUrl, $formId);
        }
        if (!$form) $this->redirectFrontend($returnUrl);

        $this->ensureSession();
        $errors = [];
        $values = is_array($_POST['form_fields'] ?? null) ? $_POST['form_fields'] : [];

        if (trim((string)($_POST['website'] ?? '')) !== '') {
            $this->completeFrontendSuccess($form, $returnUrl);
        }

        $submittedToken = is_string($_POST['form_token'] ?? null) ? $_POST['form_token'] : '';
        if (!$this->consumeFormToken($formId, $submittedToken)) {
            $errors['_form'] = 'Formularen er udløbet. Genindlæs siden og prøv igen.';
        }

        $fields = $this->repository()->fieldsForForm($formId);
        // Empty-row-only drafts must never accept empty frontend submissions.
        if (!$fields) $this->redirectFrontend($returnUrl);
        [$cleanValues, $snapshot, $fieldErrors] = $this->validateFrontendFields($fields, $values);
        $errors = array_merge($errors, $fieldErrors);

        if ($errors) {
            $this->setFrontendFlash($formId, ['errors' => $errors, 'values' => $cleanValues]);
            $this->redirectFrontend($returnUrl, $formId);
        }

        // Per-IP rate limiting persists across browser sessions. The configured
        // threshold applies per form; the higher site-wide ceiling limits abuse
        // spread across many forms. Both counters are atomic in MySQL.
        try {
            $rateSettings = $this->formSettings();
            $limit = (int)$rateSettings['rate_limit_count'];
            $window = (int)$rateSettings['rate_limit_window_minutes'] * 60;
            $ip = RateLimiter::clientIp();
            $limiter = new RateLimiter();
            $perForm = $limiter->hit('forms-form-' . $formId, $ip, $limit, $window);
            $siteWide = $limiter->hit('forms-site', $ip, max(30, $limit * 5), $window);
            if (!$perForm['allowed'] || !$siteWide['allowed']) {
                $this->setFrontendFlash($formId, [
                    'errors' => ['_form' => 'Der er sendt for mange formularer på kort tid. Vent et øjeblik og prøv igen.'],
                    'values' => $cleanValues,
                ]);
                $this->redirectFrontend($returnUrl, $formId);
            }
        } catch (Throwable $e) {
            // Never accept a submission when shared protection is unavailable.
            // The redirect exception is avoided: redirectFrontend terminates PHP.
            error_log('Forms rate limiter unavailable: ' . $e->getMessage());
            $this->setFrontendFlash($formId, [
                'errors' => ['_form' => 'Formularen er midlertidigt utilgængelig. Prøv igen senere.'],
                'values' => $cleanValues,
            ]);
            $this->redirectFrontend($returnUrl, $formId);
        }

        $sourceUrl = (string)($_POST['source_url'] ?? $returnUrl);
        if (!empty($form['save_submissions'])) {
            $this->repository()->storeSubmission($formId, $snapshot, $this->safeReturnUrl($sourceUrl));
        }
        if (!empty($form['send_notification'])) {
            $this->sendNotification($form, $snapshot);
        }
        if (!empty($form['send_confirmation'])) {
            $this->sendConfirmation($form, $snapshot);
        }

        $this->completeFrontendSuccess($form, $returnUrl);
    }

    /** @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>,2:array<string,string>} */
    private function validateFrontendFields(array $fields, array $rawValues): array
    {
        $clean = [];
        $snapshot = [];
        $errors = [];

        foreach ($fields as $field) {
            $key = (string)$field['field_key'];
            $type = (string)$field['field_type'];
            $label = (string)$field['label'];
            $required = !empty($field['is_required']);
            $raw = $rawValues[$key] ?? '';
            $value = is_array($raw) ? '' : trim((string)$raw);

            if ($type === 'checkbox') {
                $value = $raw === '1' || $raw === 1 || $raw === 'on' ? '1' : '';
            }

            $value = $this->limitText($value, $type === 'textarea' ? 5000 : 1000);

            if ($required && $value === '') {
                $errors[$key] = $type === 'checkbox' ? 'Dette felt skal accepteres.' : 'Feltet skal udfyldes.';
            } elseif ($type === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$key] = 'Indtast en gyldig e-mailadresse.';
            } elseif (in_array($type, ['select', 'radio'], true) && $value !== '') {
                $allowed = is_array($field['options'] ?? null) ? $field['options'] : [];
                if (!in_array($value, $allowed, true)) {
                    $errors[$key] = 'Vælg en gyldig mulighed.';
                    $value = '';
                }
            }

            $clean[$key] = $value;
            $snapshot[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'value' => $type === 'checkbox' ? ($value === '1' ? 'Ja' : 'Nej') : $value,
            ];
        }

        return [$clean, $snapshot, $errors];
    }

    private function renderFrontendForm(array $form, array $fields): void
    {
        $formId = (int)$form['id'];
        // Unique per request, while the first instance keeps its historical #form-N anchor.
        static $instances = [];
        $instance = ($instances[$formId] ?? 0) + 1;
        $instances[$formId] = $instance;
        $instanceId = 'form-' . $formId . ($instance === 1 ? '' : '-' . $instance);
        $flash = $this->pullFrontendFlash($formId, $instance);
        $errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
        $values = is_array($flash['values'] ?? null) ? $flash['values'] : [];
        $success = trim((string)($flash['success'] ?? ''));
        $token = $this->createFormToken($formId);
        $requestUri = $this->safeReturnUrl((string)($_SERVER['REQUEST_URI'] ?? '/'));
        $e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

        echo '<div class="form-wrap" id="' . $e($instanceId) . '">';
        if ($success !== '') echo '<div class="form__notice form__notice--success" role="status">' . nl2br($e($success)) . '</div>';
        if (!empty($errors['_form'])) echo '<div class="form__notice form__notice--error" role="alert">' . $e($errors['_form']) . '</div>';

        echo '<form class="form" method="post" action="' . $e($requestUri) . '">';
        echo '<input type="hidden" name="_module_action" value="forms:submit">';
        echo '<input type="hidden" name="form_id" value="' . $formId . '">';
        echo '<input type="hidden" name="form_instance" value="' . $instance . '">';
        echo '<input type="hidden" name="form_token" value="' . $e($token) . '">';
        echo '<input type="hidden" name="return_url" value="' . $e($requestUri) . '">';
        echo '<input type="hidden" name="source_url" value="' . $e($requestUri) . '">';
        echo '<div class="form__honeypot" aria-hidden="true"><label>Website<input tabindex="-1" autocomplete="off" name="website"></label></div>';

        $rows = [];
        foreach ($fields as $field) {
            $rowOrder = max(0, (int)($field['row_order'] ?? $field['sort_order'] ?? 0));
            $layout = (string)($field['row_layout'] ?? '100') === '50-50' ? '50-50' : '100';
            $column = $layout === '50-50' ? max(0, min(1, (int)($field['column_index'] ?? 0))) : 0;
            if (!isset($rows[$rowOrder])) {
                $rows[$rowOrder] = ['layout' => $layout, 'fields' => []];
            }
            $rows[$rowOrder]['layout'] = $layout;
            $rows[$rowOrder]['fields'][$column] = $field;
        }
        ksort($rows);

        foreach ($rows as $row) {
            $layout = $row['layout'] === '50-50' ? '50-50' : '100';
            $rowFields = is_array($row['fields'] ?? null) ? $row['fields'] : [];
            ksort($rowFields);
            echo '<div class="form__row form__row--' . $e($layout) . '">';
            foreach ($rowFields as $columnIndex => $field) {
                $columnClass = $layout === '50-50' ? ' form__column--' . ((int)$columnIndex + 1) : '';
                echo '<div class="form__column' . $columnClass . '">';
                $this->renderFrontendField($instanceId, $field, $values, $errors);
                echo '</div>';
            }
            echo '</div>';
        }

        echo '<button class="button form__submit" type="submit">' . $e((string)$form['submit_label']) . '</button>';
        echo '</form></div>';
    }


    private function renderFrontendField(string $instanceId, array $field, array $values, array $errors): void
    {
        $e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        $key = (string)$field['field_key'];
        $type = (string)$field['field_type'];
        $label = (string)$field['label'];
        $required = !empty($field['is_required']);
        $placeholder = (string)($field['placeholder'] ?? '');
        $help = (string)($field['help_text'] ?? '');
        $value = (string)($values[$key] ?? '');
        $error = (string)($errors[$key] ?? '');
        $inputId = $instanceId . '-' . preg_replace('/[^a-z0-9_-]/i', '-', $key);
        $describedBy = [];
        if ($help !== '') $describedBy[] = $inputId . '-help';
        if ($error !== '') $describedBy[] = $inputId . '-error';
        $aria = $describedBy ? ' aria-describedby="' . $e(implode(' ', $describedBy)) . '"' : '';
        $invalid = $error !== '' ? ' aria-invalid="true"' : '';
        $requiredAttr = $required ? ' required' : '';
        $placeholderAttr = $placeholder !== '' ? ' placeholder="' . $e($placeholder) . '"' : '';

        if ($type === 'radio') {
            echo '<fieldset class="form__field' . ($error !== '' ? ' form__field--error' : '') . '"><legend>' . $e($label) . ($required ? ' <span aria-hidden="true">*</span>' : '') . '</legend>';
            foreach ((array)($field['options'] ?? []) as $index => $option) {
                $optionId = $inputId . '-' . $index;
                echo '<label class="form__choice" for="' . $e($optionId) . '"><input id="' . $e($optionId) . '" type="radio" name="form_fields[' . $e($key) . ']" value="' . $e($option) . '"' . ($value === (string)$option ? ' checked' : '') . $requiredAttr . $aria . $invalid . '><span>' . $e($option) . '</span></label>';
            }
            $this->renderFieldHelpAndError($inputId, $help, $error);
            echo '</fieldset>';
            return;
        }

        if ($type === 'checkbox') {
            echo '<div class="form__field form__field--checkbox' . ($error !== '' ? ' form__field--error' : '') . '">';
            echo '<label class="form__choice" for="' . $e($inputId) . '"><input id="' . $e($inputId) . '" type="checkbox" name="form_fields[' . $e($key) . ']" value="1"' . ($value === '1' ? ' checked' : '') . $requiredAttr . $aria . $invalid . '><span>' . $e($label) . ($required ? ' <span aria-hidden="true">*</span>' : '') . '</span></label>';
            $this->renderFieldHelpAndError($inputId, $help, $error);
            echo '</div>';
            return;
        }

        echo '<div class="form__field' . ($error !== '' ? ' form__field--error' : '') . '">';
        echo '<label for="' . $e($inputId) . '">' . $e($label) . ($required ? ' <span aria-hidden="true">*</span>' : '') . '</label>';
        if ($type === 'textarea') {
            echo '<textarea id="' . $e($inputId) . '" name="form_fields[' . $e($key) . ']" rows="6"' . $placeholderAttr . $requiredAttr . $aria . $invalid . '>' . $e($value) . '</textarea>';
        } elseif ($type === 'select') {
            echo '<select id="' . $e($inputId) . '" name="form_fields[' . $e($key) . ']"' . $requiredAttr . $aria . $invalid . '>';
            echo '<option value="">Vælg…</option>';
            foreach ((array)($field['options'] ?? []) as $option) {
                echo '<option value="' . $e($option) . '"' . ($value === (string)$option ? ' selected' : '') . '>' . $e($option) . '</option>';
            }
            echo '</select>';
        } else {
            $htmlType = in_array($type, ['email', 'tel'], true) ? $type : 'text';
            $autocomplete = $type === 'email' ? ' autocomplete="email"' : ($type === 'tel' ? ' autocomplete="tel"' : '');
            echo '<input id="' . $e($inputId) . '" type="' . $e($htmlType) . '" name="form_fields[' . $e($key) . ']" value="' . $e($value) . '"' . $placeholderAttr . $autocomplete . $requiredAttr . $aria . $invalid . '>';
        }
        $this->renderFieldHelpAndError($inputId, $help, $error);
        echo '</div>';
    }

    private function renderFieldHelpAndError(string $inputId, string $help, string $error): void
    {
        $e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        if ($help !== '') echo '<p class="form__help" id="' . $e($inputId) . '-help">' . $e($help) . '</p>';
        if ($error !== '') echo '<p class="form__error" id="' . $e($inputId) . '-error">' . $e($error) . '</p>';
    }

    /** @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>,2:array<string,string>} */
    private function normalizeAdminSubmission(array $source): array
    {
        $errors = [];
        $name = $this->limitText(trim((string)($source['name'] ?? '')), 191);
        $submitLabel = $this->limitText(trim((string)($source['submit_label'] ?? 'Submit')), 80);
        $successMessage = $this->limitText(trim((string)($source['success_message'] ?? '')), 1000);
        $status = (string)($source['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $successAction = (string)($source['success_action'] ?? 'message') === 'redirect' ? 'redirect' : 'message';
        $redirectPageId = max(0, (int)($source['success_redirect_page_id'] ?? 0));

        if ($name === '') $errors['name'] = 'Navn skal udfyldes.';
        if ($submitLabel === '') $errors['submit_label'] = 'Knapteksten skal udfyldes.';
        if ($successAction === 'message' && $successMessage === '') $errors['success_message'] = 'Kvitteringsteksten skal udfyldes.';
        if ($successAction === 'redirect' && !$this->isPublishedPageId($redirectPageId)) {
            $errors['success_redirect_page_id'] = 'Vælg en publiceret side at sende brugeren videre til.';
        }

        $rawRows = is_array($source['rows'] ?? null) ? array_values($source['rows']) : [];

        // Backwards compatibility with Forms <= 1.1 admin submissions. A flat
        // field list is interpreted as one 100% row per field.
        if (!$rawRows && is_array($source['fields'] ?? null)) {
            foreach (array_values($source['fields']) as $legacyField) {
                if (!is_array($legacyField)) continue;
                $rawRows[] = ['layout' => '100', 'fields' => [0 => $legacyField]];
            }
        }

        $fields = [];
        $rowLayouts = [];
        $usedKeys = [];
        $fieldNumber = 0;
        if (count($rawRows) > 100) {
            $errors['fields'] = 'forms:En formular kan højst indeholde 100 rækker.';
            $rawRows = array_slice($rawRows, 0, 100);
        }
        foreach ($rawRows as $rowIndex => $rawRow) {
            if (!is_array($rawRow)) continue;
            $layout = (string)($rawRow['layout'] ?? '100') === '50-50' ? '50-50' : '100';
            $rowLayouts[] = ['row_order' => (int)$rowIndex, 'layout' => $layout];
            $rawRowFields = is_array($rawRow['fields'] ?? null) ? $rawRow['fields'] : [];
            $columns = $layout === '50-50' ? [0, 1] : [0];

            foreach ($columns as $columnIndex) {
                $rawField = $rawRowFields[$columnIndex] ?? null;
                if (!is_array($rawField)) continue;

                $fieldNumber++;
                $label = $this->limitText(trim((string)($rawField['label'] ?? '')), 191);
                $type = strtolower(trim((string)($rawField['field_type'] ?? 'text')));
                if (!isset(self::FIELD_TYPES[$type])) $type = 'text';
                if ($label === '') {
                    $errors['fields'] = 'Alle tilføjede felter skal have en label.';
                    continue;
                }

                $requestedKey = $this->sanitizeFieldKey((string)($rawField['field_key'] ?? ''));
                $key = $requestedKey !== ''
                    ? $this->uniqueFieldKeyFromBase($requestedKey, $usedKeys)
                    : $this->uniqueFieldKey($label, $usedKeys, $fieldNumber);

                $options = [];
                if (in_array($type, ['select', 'radio'], true)) {
                    $rawOptions = preg_split('/\R/u', (string)($rawField['options'] ?? '')) ?: [];
                    foreach ($rawOptions as $option) {
                        $option = $this->limitText(trim($option), 191);
                        if ($option !== '' && !in_array($option, $options, true)) $options[] = $option;
                    }
                    if (!$options) $errors['fields'] = 'Dropdown- og radiofelter skal have mindst én valgmulighed.';
                }

                $fields[] = [
                    'field_key' => $key,
                    'label' => $label,
                    'field_type' => $type,
                    'placeholder' => $this->limitText(trim((string)($rawField['placeholder'] ?? '')), 255),
                    'help_text' => $this->limitText(trim((string)($rawField['help_text'] ?? '')), 500),
                    'options' => $options,
                    'is_required' => isset($rawField['is_required']) ? 1 : 0,
                    'row_layout' => $layout,
                    'row_order' => (int)$rowIndex,
                    'column_index' => (int)$columnIndex,
                ];
            }
        }

        if (!$rowLayouts) $errors['fields'] = 'forms:Tilføj mindst én række til formularen.';
        if (count($fields) > 50) {
            $fields = array_slice($fields, 0, 50);
            $errors['fields'] = 'En formular kan højst indeholde 50 felter.';
        }

        $emailKeys = [];
        foreach ($fields as $field) {
            if (($field['field_type'] ?? '') === 'email') $emailKeys[] = (string)$field['field_key'];
        }

        $sendNotification = isset($source['send_notification']) ? 1 : 0;
        $notificationSubject = $this->limitText(trim((string)($source['notification_subject'] ?? '')), 191);
        $replyToFieldKey = $this->sanitizeFieldKey((string)($source['reply_to_field_key'] ?? ''));
        if (!in_array($replyToFieldKey, $emailKeys, true)) $replyToFieldKey = '';

        $sendConfirmation = isset($source['send_confirmation']) ? 1 : 0;
        $confirmationEmailFieldKey = $this->sanitizeFieldKey((string)($source['confirmation_email_field_key'] ?? ''));
        $confirmationSubject = $this->limitText(trim((string)($source['confirmation_subject'] ?? '')), 191);
        $confirmationMessage = $this->limitText(trim((string)($source['confirmation_message'] ?? '')), 3000);
        if ($sendConfirmation) {
            if (!in_array($confirmationEmailFieldKey, $emailKeys, true)) {
                $errors['confirmation_email_field_key'] = 'Vælg hvilket e-mailfelt bekræftelsen skal sendes til.';
            }
            if ($confirmationSubject === '') $errors['confirmation_subject'] = 'Emnet til bekræftelsesmailen skal udfyldes.';
            if ($confirmationMessage === '') $errors['confirmation_message'] = 'Teksten til bekræftelsesmailen skal udfyldes.';
        }

        $form = [
            'name' => $name,
            'status' => $status,
            'submit_label' => $submitLabel,
            'success_message' => $successMessage !== '' ? $successMessage : $this->formSettings()['default_success_message'],
            'success_action' => $successAction,
            'success_redirect_page_id' => $redirectPageId,
            'notification_email' => '',
            'notification_subject' => $notificationSubject,
            'reply_to_field_key' => $replyToFieldKey,
            'save_submissions' => isset($source['save_submissions']) ? 1 : 0,
            'send_notification' => $sendNotification,
            'send_confirmation' => $sendConfirmation,
            'confirmation_email_field_key' => $confirmationEmailFieldKey,
            'confirmation_subject' => $confirmationSubject,
            'confirmation_message' => $confirmationMessage,
        ];

        return [$form, $fields, $errors, $rowLayouts];
    }

    private function uniqueFieldKey(string $label, array &$used, int $fallback): string
    {
        $ascii = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label) : $label;
        $ascii = is_string($ascii) ? strtolower($ascii) : strtolower($label);
        $key = trim((string)preg_replace('/[^a-z0-9]+/', '_', $ascii), '_');
        if ($key === '') $key = 'field_' . $fallback;
        return $this->uniqueFieldKeyFromBase(substr($key, 0, 80), $used);
    }

    private function uniqueFieldKeyFromBase(string $base, array &$used): string
    {
        $base = $this->sanitizeFieldKey($base);
        if ($base === '') $base = 'field';
        $key = substr($base, 0, 80);
        $suffix = 2;
        while (isset($used[$key])) {
            $key = substr($base, 0, 74) . '_' . $suffix++;
        }
        $used[$key] = true;
        return $key;
    }

    private function sanitizeFieldKey(string $key): string
    {
        $key = strtolower(trim($key));
        return trim((string)preg_replace('/[^a-z0-9_]+/', '_', $key), '_');
    }

    private function emptyAdminForm(): array
    {
        $defaults = $this->formSettings();
        return [
            'name' => '',
            'status' => 'active',
            'submit_label' => 'Submit',
            'success_message' => $defaults['default_success_message'],
            'success_action' => 'message',
            'success_redirect_page_id' => 0,
            'notification_email' => '',
            'notification_subject' => '',
            'reply_to_field_key' => '',
            'save_submissions' => $defaults['default_save_submissions'],
            'send_notification' => $defaults['default_send_notification'],
            'send_confirmation' => 0,
            'confirmation_email_field_key' => '',
            'confirmation_subject' => 'Thank you for contacting us',
            'confirmation_message' => 'Thank you for contacting us. We received your message and will reply as soon as possible.',
        ];
    }

    /** @return array<string,mixed> */
    private function formSettings(): array
    {
        $settings = $this->settings();
        return [
            'default_save_submissions' => (int)$settings->get('forms_default_save_submissions', 1) === 1 ? 1 : 0,
            'default_send_notification' => (int)$settings->get('forms_default_send_notification', 1) === 1 ? 1 : 0,
            'default_success_message' => $this->limitText(trim((string)$settings->get('forms_default_success_message', 'Thank you. Your message has been sent.')), 1000) ?: 'Thank you. Your message has been sent.',
            'rate_limit_count' => max(1, min(30, (int)$settings->get('forms_rate_limit_count', 6))),
            'rate_limit_window_minutes' => max(1, min(60, (int)$settings->get('forms_rate_limit_window_minutes', 10))),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function publishedPages(): array
    {
        $pages = new Pages();
        $rows = [];
        foreach ($pages->allPublished() as $page) {
            $id = (int)($page['id'] ?? 0);
            if ($id < 1) continue;
            $rows[] = [
                'id' => $id,
                'title' => (string)($page['page_title'] ?? ''),
                'url' => $pages->buildPublicUrl($page),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => strcasecmp((string)$a['title'], (string)$b['title']));
        return $rows;
    }

    private function isPublishedPageId(int $id): bool
    {
        if ($id < 1) return false;
        foreach ($this->publishedPages() as $page) {
            if ((int)$page['id'] === $id) return true;
        }
        return false;
    }

    private function createFormToken(int $formId): string
    {
        $this->ensureSession();
        $tokens = is_array($_SESSION['forms_tokens'] ?? null) ? $_SESSION['forms_tokens'] : [];
        $cutoff = time() - 7200;
        foreach ($tokens as $key => $meta) {
            if (!is_array($meta) || (int)($meta['created'] ?? 0) < $cutoff) unset($tokens[$key]);
        }
        if (count($tokens) > 30) $tokens = array_slice($tokens, -30, null, true);
        $token = bin2hex(random_bytes(24));
        $tokens[$token] = ['form_id' => $formId, 'created' => time()];
        $_SESSION['forms_tokens'] = $tokens;
        return $token;
    }

    private function consumeFormToken(int $formId, string $token): bool
    {
        $this->ensureSession();
        if ($token === '' || !isset($_SESSION['forms_tokens'][$token]) || !is_array($_SESSION['forms_tokens'][$token])) return false;
        $meta = $_SESSION['forms_tokens'][$token];
        unset($_SESSION['forms_tokens'][$token]);
        $age = time() - (int)($meta['created'] ?? 0);
        return (int)($meta['form_id'] ?? 0) === $formId && $age >= 0 && $age <= 7200;
    }

    private function setFrontendFlash(int $formId, array $flash): void
    {
        $this->ensureSession();
        $flash['instance'] = $this->submittedFormInstance($formId);
        $_SESSION['forms_flash'][$formId] = $flash;
    }

    private function pullFrontendFlash(int $formId, int $instance = 1): array
    {
        $this->ensureSession();
        $flash = $_SESSION['forms_flash'][$formId] ?? [];
        if (!is_array($flash) || (int)($flash['instance'] ?? 1) !== $instance) return [];
        unset($_SESSION['forms_flash'][$formId]);
        return $flash;
    }

    /** Instance number identifies a public HTML target, never the database form identity. */
    private function submittedFormInstance(int $formId): int
    {
        if ((int)($_POST['form_id'] ?? 0) !== $formId) return 1;
        $raw = $_POST['form_instance'] ?? 1;
        if (!is_scalar($raw) || !preg_match('/^[1-9][0-9]{0,3}$/D', (string)$raw)) return 1;
        return min(60, (int)$raw);
    }

    private function completeFrontendSuccess(array $form, string $returnUrl): never
    {
        if (($form['success_action'] ?? 'message') === 'redirect') {
            $redirect = $this->successRedirectUrl((int)($form['success_redirect_page_id'] ?? 0));
            if ($redirect !== null) $this->redirectFrontend($redirect);
        }
        $this->setFrontendFlash((int)$form['id'], ['success' => (string)($form['success_message'] ?? 'Thank you. Your message has been sent.')]);
        $this->redirectFrontend($returnUrl, (int)$form['id']);
    }

    private function successRedirectUrl(int $pageId): ?string
    {
        if ($pageId < 1) return null;
        $pages = new Pages();
        $page = $pages->findAdmin($pageId);
        if (!$page || ($page['page_status'] ?? '') !== 'published') return null;
        return $pages->buildPublicUrl($page);
    }

    private function sendNotification(array $form, array $snapshot): void
    {
        $to = $this->notificationEmail();
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;

        $formName = preg_replace('/[\r\n]+/', ' ', (string)$form['name']) ?: 'Formular';
        $subject = trim((string)($form['notification_subject'] ?? ''));
        if ($subject === '') $subject = 'Ny formularindsendelse · ' . $formName;
        $lines = ['Der er modtaget en ny indsendelse via ' . $formName . '.', ''];
        foreach ($snapshot as $item) {
            $lines[] = (string)($item['label'] ?? 'Felt') . ': ' . (string)($item['value'] ?? '');
        }

        $replyTo = $this->snapshotValue($snapshot, (string)($form['reply_to_field_key'] ?? ''));
        if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $replyTo = '';
        $this->sendPlainMail($to, $subject, implode("\n", $lines), $replyTo, 'notification for form #' . (int)$form['id']);
    }

    private function sendConfirmation(array $form, array $snapshot): void
    {
        $to = $this->snapshotValue($snapshot, (string)($form['confirmation_email_field_key'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;

        $subject = trim((string)($form['confirmation_subject'] ?? '')) ?: 'Thank you for contacting us';
        $message = trim((string)($form['confirmation_message'] ?? ''));
        if ($message === '') return;
        $replyTo = $this->notificationEmail();
        if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $replyTo = '';
        $this->sendPlainMail($to, $subject, $message, $replyTo, 'confirmation for form #' . (int)$form['id']);
    }

    private function sendPlainMail(string $to, string $subject, string $body, string $replyTo, string $context): void
    {
        $siteName = trim((string)$this->settings()->get('site_name', 'Boilerplate')) ?: 'Boilerplate';
        $siteName = preg_replace('/[\r\n]+/', ' ', $siteName) ?: 'Boilerplate';
        $subject = preg_replace('/[\r\n]+/', ' ', trim($subject)) ?: 'Formular';

        $host = (string)(parse_url(SiteUrl::origin($this->settings()), PHP_URL_HOST) ?: '');
        $host = preg_replace('/:\d+$/', '', strtolower($host));
        $host = preg_replace('/^www\./', '', (string)$host);
        $from = filter_var('no-reply@' . $host, FILTER_VALIDATE_EMAIL) ? 'no-reply@' . $host : $this->notificationEmail();
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) return;

        $headers = ['From: ' . $siteName . ' <' . $from . '>', 'Content-Type: text/plain; charset=UTF-8'];
        if ($replyTo !== '') $headers[] = 'Reply-To: ' . $replyTo;
        if (function_exists('mb_encode_mimeheader')) $subject = mb_encode_mimeheader($subject, 'UTF-8');

        if (!function_exists('mail')) {
            error_log('Forms mail is unavailable because PHP mail() is disabled (' . $context . ').');
            return;
        }
        if (!@mail($to, $subject, $body, implode("\r\n", $headers))) {
            error_log('Forms mail could not be sent (' . $context . ').');
        }
    }

    private function snapshotValue(array $snapshot, string $key): string
    {
        if ($key === '') return '';
        foreach ($snapshot as $item) {
            if ((string)($item['key'] ?? '') === $key) return trim((string)($item['value'] ?? ''));
        }
        return '';
    }

    private function notificationEmail(): string
    {
        $to = trim((string)$this->settings()->get('notification_email', ''));
        if ($to === '') $to = trim((string)$this->settings()->get('contact_notification_email', ''));
        if ($to === '') $to = trim((string)$this->settings()->get('admin_email', ''));
        return $to;
    }

    private function safeReturnUrl(string $url): string
    {
        $url = trim(str_replace(["\r", "\n", "\\"], '', $url));
        if ($url === '' || !str_starts_with($url, '/') || str_starts_with($url, '//')) return '/';
        $parts = parse_url($url);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) return '/';
        return $this->limitText($url, 2048);
    }

    private function safeAdminReturnUrl(string $url): string
    {
        $url = $this->safeReturnUrl($url);
        return str_starts_with($url, '/admin/forms') ? $url : '/admin/forms/submissions';
    }

    private function redirectFrontend(string $url, int $formId = 0): never
    {
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        $instance = $formId > 0 ? $this->submittedFormInstance($formId) : 1;
        $fragment = $formId > 0 ? '#form-' . $formId . ($instance === 1 ? '' : '-' . $instance) : '';
        header('Location: ' . $this->safeReturnUrl($url) . $fragment, true, 303);
        exit;
    }

    private function limitText(string $value, int $max): string
    {
        if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
            $value = function_exists('iconv') ? ((string)@iconv('UTF-8', 'UTF-8//IGNORE', $value)) : '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    private function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    }

    private function repository(): FormsRepository
    {
        if (!$this->repository instanceof FormsRepository) throw new RuntimeException('Forms repository er ikke startet.');
        return $this->repository;
    }

    private function settings(): Settings
    {
        if (!$this->settings instanceof Settings) $this->settings = new Settings();
        return $this->settings;
    }

    private function requireContext(): void
    {
        if (!$this->context instanceof ModuleContext) throw new RuntimeException('Forms Module er ikke startet korrekt.');
    }
}
