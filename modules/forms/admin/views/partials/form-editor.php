<?php
$formData = is_array($formData ?? null) ? $formData : [];
$fields = is_array($fields ?? null) ? $fields : [];
$builderRows = is_array($builderRows ?? null) ? $builderRows : [];
$errors = is_array($errors ?? null) ? $errors : [];
$fieldTypes = is_array($fieldTypes ?? null) ? $fieldTypes : [];
$formTemplates = is_array($formTemplates ?? null) ? $formTemplates : [];
$publishedPages = is_array($publishedPages ?? null) ? $publishedPages : [];
$notificationEmail = trim((string)($notificationEmail ?? ''));
$isEdit = !empty($isEdit);
$formId = max(0, (int)($formId ?? 0));
$deleteDependencies = is_array($deleteDependencies ?? null) ? $deleteDependencies : ['submissions' => 0, 'pages' => 0];
$fieldError = static fn(string $key): string => t((string)($errors[$key] ?? ''));
$action = $isEdit ? '/admin/forms?screen=edit&amp;id=' . $formId : '/admin/forms?screen=new';
$emailFields = array_values(array_filter($fields, static fn(array $field): bool => ($field['field_type'] ?? '') === 'email'));
$successAction = (string)($formData['success_action'] ?? 'message');
// Keep users on the relevant panel after server-side validation, without URL-driven tabs.
$initialEditorTab = 'fields';
if (array_intersect(['name', 'submit_label', 'success_message', 'success_redirect_page_id'], array_keys($errors))) {
    $initialEditorTab = 'settings';
} elseif (array_intersect(['confirmation_email_field_key', 'confirmation_subject', 'confirmation_message'], array_keys($errors))) {
    $initialEditorTab = 'emails';
}
?>
<div class="admin-detail-back"><a class="admin-back-link" href="/admin/forms"><?= admin_icon('arrow-left', 'admin-back-link__icon'); ?><?= admin_e(t('forms:Tilbage til Forms')); ?></a></div>
<?php if (!empty($notice)): ?><div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div><?php endif; ?>
<?php if ($fieldError('form') !== ''): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$fieldError('form'))); ?></div><?php endif; ?>
<?php if ($fieldError('fields') !== ''): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$fieldError('fields'))); ?></div><?php endif; ?>

<form method="post" action="<?= $action; ?>" class="admin-form" data-forms-builder data-admin-track-changes>
  <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $formId; ?>"><?php endif; ?>

  <div class="admin-editor-bar">
    <div class="admin-editor-bar__heading">
      <h2><?= admin_e($isEdit ? (string)($formData['name'] ?? t('forms:Rediger formular')) : t('forms:Ny formular')); ?></h2>
      <p><?= admin_e(t('forms:Opret eller rediger formularens felter.')); ?></p>
    </div>
    <div class="admin-form-actions"><a class="admin-button admin-button--secondary" href="/admin/forms"><?= admin_e(t('Annuller')); ?></a><button class="admin-button" type="submit"><?= admin_e(t('forms:Gem formular')); ?></button></div>
  </div>

  <nav class="admin-editor-tabs" aria-label="<?= admin_e(t('forms:Formulareditor')); ?>" data-admin-editor-tabs>
    <div class="admin-editor-tabs__list" role="tablist" aria-label="<?= admin_e(t('forms:Formulareditor')); ?>">
      <button type="button" class="admin-editor-tabs__tab<?= $initialEditorTab === 'fields' ? ' is-active' : ''; ?>" role="tab" id="forms-tab-fields" aria-controls="forms-panel-fields" aria-selected="<?= $initialEditorTab === 'fields' ? 'true' : 'false'; ?>" tabindex="<?= $initialEditorTab === 'fields' ? '0' : '-1'; ?>" data-admin-editor-tab="fields"><?= admin_e(t('forms:Felter')); ?></button>
      <button type="button" class="admin-editor-tabs__tab<?= $initialEditorTab === 'settings' ? ' is-active' : ''; ?>" role="tab" id="forms-tab-settings" aria-controls="forms-panel-settings" aria-selected="<?= $initialEditorTab === 'settings' ? 'true' : 'false'; ?>" tabindex="<?= $initialEditorTab === 'settings' ? '0' : '-1'; ?>" data-admin-editor-tab="settings"><?= admin_e(t('Indstillinger')); ?></button>
      <button type="button" class="admin-editor-tabs__tab<?= $initialEditorTab === 'emails' ? ' is-active' : ''; ?>" role="tab" id="forms-tab-emails" aria-controls="forms-panel-emails" aria-selected="<?= $initialEditorTab === 'emails' ? 'true' : 'false'; ?>" tabindex="<?= $initialEditorTab === 'emails' ? '0' : '-1'; ?>" data-admin-editor-tab="emails"><?= admin_e(t('forms:E-mails')); ?></button>
    </div>
  </nav>

  <div class="admin-detail-grid">
    <div class="admin-detail-content">
      <div id="forms-panel-fields" class="admin-editor-tabpanel" data-admin-editor-panel="fields" role="tabpanel" aria-labelledby="forms-tab-fields" <?= $initialEditorTab !== 'fields' ? 'hidden' : ''; ?>>
      <section class="admin-panel">
        <div class="admin-panel__header admin-panel__header--split admin-panel__header--builder">
          <div>
            <h2><?= admin_e(t('forms:Form Builder')); ?></h2>
            <p><?= admin_e(t('forms:Opret eller rediger formularens felter.')); ?></p>
          </div>
          <button class="admin-button admin-button--secondary admin-button--compact" type="button" data-forms-open-templates><?= admin_e(t('forms:Skabeloner')); ?></button>
        </div>
        <div class="admin-form-section forms-builder">
          <div class="forms-builder__rows" data-forms-rows>
            <?php foreach (forms_admin_group_rows($fields, $builderRows) as $rowIndex => $row): ?>
              <?= forms_admin_builder_row($row, (string)$rowIndex, $fieldTypes); ?>
            <?php endforeach; ?>
          </div>
          <button class="forms-builder__add" type="button" data-forms-add-element>
            <?= admin_icon('plus', 'forms-builder__add-icon'); ?>
            <?= admin_e(t('forms:Klik her for at tilføje et formularfelt')); ?>
          </button>
        </div>
      </section>
      </div>
      <div id="forms-panel-settings" class="admin-editor-tabpanel" data-admin-editor-panel="settings" role="tabpanel" aria-labelledby="forms-tab-settings" <?= $initialEditorTab !== 'settings' ? 'hidden' : ''; ?>>
      <section class="admin-panel">
        <div class="admin-panel__header"><h2><?= admin_e(t('forms:Formular')); ?></h2></div>
        <div class="admin-form-section">
            <div class="admin-field"><label for="forms-name"><?= admin_e(t('Navn *')); ?></label><input id="forms-name" name="name" maxlength="191" value="<?= admin_e((string)($formData['name'] ?? '')); ?>" required><?php if($fieldError('name')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('name'))); ?></p><?php endif; ?></div>
          <div class="admin-field"><label for="forms-submit-label"><?= admin_e(t('forms:Knaptekst *')); ?></label><input id="forms-submit-label" name="submit_label" maxlength="80" value="<?= admin_e((string)($formData['submit_label'] ?? 'Submit')); ?>" required><?php if($fieldError('submit_label')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('submit_label'))); ?></p><?php endif; ?></div>
        </div>
      </section>
      <section class="admin-panel">
        <div class="admin-panel__header"><h2><?= admin_e(t('forms:Efter afsendelse')); ?></h2></div>
        <div class="admin-form-section">
          <div class="admin-field">
            <label for="forms-success-action"><?= admin_e(t('Handling')); ?></label>
            <select id="forms-success-action" name="success_action" data-forms-success-action>
              <option value="message" <?= $successAction === 'message' ? 'selected' : ''; ?>><?= admin_e(t('forms:Vis kvittering')); ?></option>
              <option value="redirect" <?= $successAction === 'redirect' ? 'selected' : ''; ?>><?= admin_e(t('forms:Send videre til side')); ?></option>
            </select>
          </div>
          <div class="admin-field" data-forms-success-message <?= $successAction === 'redirect' ? 'hidden' : ''; ?>>
            <label for="forms-success-message"><?= admin_e(t('forms:Kvitteringstekst *')); ?></label>
            <textarea id="forms-success-message" name="success_message" rows="3" maxlength="1000"><?= admin_e((string)($formData['success_message'] ?? 'Thank you. Your message has been sent.')); ?></textarea>
            <?php if($fieldError('success_message')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('success_message'))); ?></p><?php endif; ?>
          </div>
          <div class="admin-field" data-forms-success-redirect <?= $successAction !== 'redirect' ? 'hidden' : ''; ?>>
            <label for="forms-success-redirect"><?= admin_e(t('forms:Side *')); ?></label>
            <select id="forms-success-redirect" name="success_redirect_page_id">
              <option value="0"><?= admin_e(t('Vælg side…')); ?></option>
              <?php foreach ($publishedPages as $page): ?>
                <option value="<?= (int)$page['id']; ?>" <?= (int)($formData['success_redirect_page_id'] ?? 0) === (int)$page['id'] ? 'selected' : ''; ?>><?= admin_e((string)$page['title']); ?> · <?= admin_e((string)$page['url']); ?></option>
              <?php endforeach; ?>
            </select>
            <?php if($fieldError('success_redirect_page_id')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('success_redirect_page_id'))); ?></p><?php endif; ?>
            <p class="admin-field__help"><?= admin_e(t('forms:Redirectet bruger sidens aktuelle URL, så senere slug-ændringer følger automatisk med.')); ?></p>
          </div>
        </div>
      </section>
      </div>
      <div id="forms-panel-emails" class="admin-editor-tabpanel" data-admin-editor-panel="emails" role="tabpanel" aria-labelledby="forms-tab-emails" <?= $initialEditorTab !== 'emails' ? 'hidden' : ''; ?>>
      <section class="admin-panel">
        <div class="admin-panel__header"><h2><?= admin_e(t('forms:E-mail-notifikation')); ?></h2></div>
        <div class="admin-form-section">
          <label class="admin-checkbox"><input type="checkbox" name="send_notification" value="1" data-forms-notification-toggle <?= !empty($formData['send_notification'])?'checked':''; ?>><span><?= admin_e(t('forms:Send e-mail-notifikation ved ny indsendelse')); ?></span></label>
          <p class="admin-field__help"><?= admin_e(t('forms:Sendes til')); ?> <strong><?= admin_e($notificationEmail !== '' ? $notificationEmail : t('forms:den centrale notifikationsadresse')); ?></strong> <?= admin_e(t('forms:fra Indstillinger → Notifikationer.')); ?></p>
          <div data-forms-notification-options <?= empty($formData['send_notification']) ? 'hidden' : ''; ?>>
            <div class="admin-form-row">
              <div class="admin-field">
                <label for="forms-notification-subject"><?= admin_e(t('forms:Emne')); ?></label>
                <input id="forms-notification-subject" name="notification_subject" maxlength="191" value="<?= admin_e((string)($formData['notification_subject'] ?? '')); ?>" placeholder="Ny formularindsendelse · Formularnavn">
                <p class="admin-field__help"><?= admin_e(t('forms:Tomt felt bruger automatisk formularens navn.')); ?></p>
              </div>
              <div class="admin-field">
                <label for="forms-reply-to"><?= admin_e(t('forms:Reply-To')); ?></label>
                <select id="forms-reply-to" name="reply_to_field_key" data-forms-email-select data-selected="<?= admin_e((string)($formData['reply_to_field_key'] ?? '')); ?>">
                  <option value=""><?= admin_e(t('Ingen')); ?></option>
                  <?php foreach ($emailFields as $field): ?><option value="<?= admin_e((string)$field['field_key']); ?>" <?= ($formData['reply_to_field_key']??'')===$field['field_key']?'selected':''; ?>><?= admin_e((string)$field['label']); ?></option><?php endforeach; ?>
                </select>
                <p class="admin-field__help"><?= admin_e(t('forms:Når du trykker Svar i mailprogrammet, svarer du direkte til værdien fra dette e-mailfelt.')); ?></p>
              </div>
            </div>
          </div>
        </div>
      </section>
      <section class="admin-panel">
        <div class="admin-panel__header"><h2><?= admin_e(t('forms:Bekræftelsesmail')); ?></h2></div>
        <div class="admin-form-section">
          <label class="admin-checkbox"><input type="checkbox" name="send_confirmation" value="1" data-forms-confirmation-toggle <?= !empty($formData['send_confirmation'])?'checked':''; ?>><span><?= admin_e(t('forms:Send en bekræftelse til afsenderen')); ?></span></label>
          <div class="forms-conditional-stack" data-forms-confirmation-options <?= empty($formData['send_confirmation']) ? 'hidden' : ''; ?>>
            <div class="admin-field">
              <label for="forms-confirmation-email-field"><?= admin_e(t('forms:Send til e-mailfelt *')); ?></label>
              <select id="forms-confirmation-email-field" name="confirmation_email_field_key" data-forms-email-select data-selected="<?= admin_e((string)($formData['confirmation_email_field_key'] ?? '')); ?>">
                <option value=""><?= admin_e(t('forms:Vælg e-mailfelt…')); ?></option>
                <?php foreach ($emailFields as $field): ?><option value="<?= admin_e((string)$field['field_key']); ?>" <?= ($formData['confirmation_email_field_key']??'')===$field['field_key']?'selected':''; ?>><?= admin_e((string)$field['label']); ?></option><?php endforeach; ?>
              </select>
              <?php if($fieldError('confirmation_email_field_key')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('confirmation_email_field_key'))); ?></p><?php endif; ?>
            </div>
            <div class="admin-field">
              <label for="forms-confirmation-subject"><?= admin_e(t('forms:Emne *')); ?></label>
              <input id="forms-confirmation-subject" name="confirmation_subject" maxlength="191" value="<?= admin_e((string)($formData['confirmation_subject'] ?? 'Thank you for contacting us')); ?>">
              <?php if($fieldError('confirmation_subject')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('confirmation_subject'))); ?></p><?php endif; ?>
            </div>
            <div class="admin-field">
              <label for="forms-confirmation-message"><?= admin_e(t('forms:Besked *')); ?></label>
              <textarea id="forms-confirmation-message" name="confirmation_message" rows="5" maxlength="3000"><?= admin_e((string)($formData['confirmation_message'] ?? '')); ?></textarea>
              <?php if($fieldError('confirmation_message')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('confirmation_message'))); ?></p><?php endif; ?>
              <p class="admin-field__help"><?= admin_e(t('forms:Svar på bekræftelsesmailen går til den centrale notifikationsadresse.')); ?></p>
            </div>
          </div>
        </div>
      </section>
      </div>
    </div>

    <aside class="admin-detail-sidebar">
      <section class="admin-panel">
        <div class="admin-panel__header"><h2><?= admin_e(t('forms:Formularstatus')); ?></h2></div>
        <div class="admin-form-section">
            <input type="hidden" id="forms-status" name="status" value="<?= ($formData['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active'; ?>" data-forms-status-value>
            <?= AdminToggle::render('forms-visibility', t('Synlighed'), ($formData['status'] ?? 'active') !== 'inactive', '', ['data-forms-visibility-switch' => '1'], ['on'=>t('Vises'), 'off'=>t('Skjult')]); ?>
            <span class="admin-toggle__description"><?= admin_e(t('forms:Kun aktive formularer kan vælges i Page Builder og sendes fra frontend.')); ?></span>
          <label class="admin-checkbox"><input type="checkbox" name="save_submissions" value="1" <?= !empty($formData['save_submissions'])?'checked':''; ?>><span><?= admin_e(t('forms:Gem indsendelser i CMS\'et')); ?></span></label>
        </div>
      </section>
      <section class="admin-panel"><div class="admin-panel__header"><h2><?= admin_e(t('forms:Brug på sider')); ?></h2></div><div class="admin-form-section">
        <?php if ($isEdit && ($formData['status']??'active') === 'active'): ?><p><?= admin_e(t('forms:Åbn en side i Page Builder, tilføj')); ?> <strong><?= admin_e(t('forms:Dynamisk komponent')); ?></strong> <?= admin_e(t('forms:og vælg:')); ?></p><p><strong>Formular · <?= admin_e((string)($formData['name'] ?? '')); ?></strong></p><?php elseif ($isEdit): ?><p><?= admin_e(t('forms:Aktivér formularen for at gøre den tilgængelig i Page Builder.')); ?></p><?php else: ?><p><?= admin_e(t('forms:Gem formularen først. Derefter bliver den tilgængelig som dynamisk Page Builder-komponent.')); ?></p><?php endif; ?>
      </div></section>
<?php if ($isEdit && $formId > 0): ?>
      <section class="admin-panel admin-danger-zone">
        <div class="admin-panel__header"><h2><?= admin_e(t('Slet')); ?></h2></div>
        <div class="admin-form-section">
          <?php if ((int)$deleteDependencies['submissions'] > 0): ?>
            <p><?= admin_e(t('forms:Formularen har gemte indsendelser. Slet dem først, eller deaktivér formularen.')); ?></p>
            <span class="admin-button admin-button--danger is-disabled" aria-disabled="true"><?= admin_e(t('forms:Slet formular')); ?></span>
          <?php elseif ((int)$deleteDependencies['pages'] > 0): ?>
            <p><?= admin_e(t('forms:Formularen bruges i Page Builder. Fjern den fra siderne først.')); ?></p>
            <span class="admin-button admin-button--danger is-disabled" aria-disabled="true"><?= admin_e(t('forms:Slet formular')); ?></span>
          <?php else: ?>
            <p><?= admin_e(t('forms:Slet formularen og dens felter permanent.')); ?></p>
            <button class="admin-button admin-button--danger" type="button" data-admin-open-delete="forms-delete-dialog"><?= admin_e(t('forms:Slet formular')); ?></button>
          <?php endif; ?>
        </div>
      </section>
<?php endif; ?>
    </aside>
  </div>
</form>

<?php if ($isEdit && $formId > 0 && (int)$deleteDependencies['submissions'] === 0 && (int)$deleteDependencies['pages'] === 0): ?>
<?= AdminDeleteDialog::render([
    'id' => 'forms-delete-dialog',
    'titleId' => 'forms-delete-dialog-title',
    'title' => t('forms:Slet formular'),
    'warning' => t('forms:Denne handling kan ikke fortrydes.'),
    'action' => '/admin/forms?screen=edit&id=' . $formId,
    'csrfToken' => admin_csrf_token(),
    'fields' => [
        'id' => $formId,
        'form_action' => 'delete',
        'confirm_delete' => 'permanent',
    ],
    'prefix' => t('forms:Er du sikker på, at du vil slette formularen'),
    'subject' => (string)($formData['name'] ?? ''),
    'suffix' => '?',
    'confirmLabel' => t('forms:Slet formular'),
    'closeLabel' => t('Luk'),
    'cancelLabel' => t('Annuller'),
]); ?>
<?php endif; ?>
<dialog class="admin-modal admin-modal--sm" data-admin-modal data-forms-choice-dialog aria-labelledby="forms-choice-title">
  <div class="admin-modal__surface">
    <div class="admin-modal__header"><div><h2 id="forms-choice-title"><?= admin_e(t('forms:Tilføj til formularen')); ?></h2><p><?= admin_e(t('forms:Vælg et formularfelt eller en række med to kolonner.')); ?></p></div><button class="admin-modal__close" type="button" data-modal-close aria-label="<?= admin_e(t('Luk')); ?>">×</button></div>
    <div class="admin-modal__body forms-choice-list">
      <button type="button" class="forms-choice" data-forms-choose-element="field"><strong><?= admin_e(t('forms:Formularfelt')); ?></strong><span><?= admin_e(t('forms:Et felt i fuld bredde (100%).')); ?></span></button>
      <button type="button" class="forms-choice" data-forms-choose-element="row"><strong><?= admin_e(t('forms:Form Row')); ?></strong><span><?= admin_e(t('forms:To tomme kolonner side om side (50/50).')); ?></span></button>
    </div>
    <div class="admin-modal__footer"><button type="button" class="admin-button admin-button--secondary" data-modal-close><?= admin_e(t('Annuller')); ?></button></div>
  </div>
</dialog>
<dialog class="admin-modal admin-modal--sm" data-admin-modal data-forms-type-dialog aria-labelledby="forms-type-title">
  <div class="admin-modal__surface">
    <div class="admin-modal__header"><div><h2 id="forms-type-title"><?= admin_e(t('forms:Vælg felttype')); ?></h2><p><?= admin_e(t('forms:Vælg den felttype, du vil indsætte.')); ?></p></div><button class="admin-modal__close" type="button" data-modal-close aria-label="<?= admin_e(t('Luk')); ?>">×</button></div>
    <div class="admin-modal__body forms-type-list">
      <?php foreach ($fieldTypes as $value => $name): ?>
        <button type="button" class="forms-choice" data-forms-choose-type="<?= admin_e((string)$value); ?>"><strong><?= admin_e(t('forms:' . (string)$name)); ?></strong></button>
      <?php endforeach; ?>
    </div>
    <div class="admin-modal__footer"><button type="button" class="admin-button admin-button--secondary" data-modal-close><?= admin_e(t('Annuller')); ?></button></div>
  </div>
</dialog>
<dialog class="admin-modal admin-modal--sm" data-admin-modal data-forms-templates-dialog aria-labelledby="forms-templates-title">
  <div class="admin-modal__surface">
    <div class="admin-modal__header"><div><h2 id="forms-templates-title"><?= admin_e(t('forms:Skabeloner')); ?></h2><p><?= admin_e(t('forms:Formularskabeloner')); ?></p></div><button class="admin-modal__close" type="button" data-modal-close aria-label="<?= admin_e(t('Luk')); ?>">×</button></div>
    <div class="admin-modal__body">
      <p class="admin-field__help"><?= admin_e(t('forms:Vælg en skabelon. Alle felter og rækker kan redigeres bagefter.')); ?></p>
      <div class="forms-template-list" role="radiogroup" aria-label="<?= admin_e(t('forms:Formularskabeloner')); ?>">
        <?php foreach ($formTemplates as $templateIndex => $template): ?>
          <label class="forms-template-option">
            <input type="radio" name="forms_template_choice" value="<?= admin_e((string)$template['id']); ?>" data-forms-template-option <?= $templateIndex === 0 ? 'checked' : ''; ?>>
            <span class="forms-template-option__body"><strong><?= admin_e((string)$template['title']); ?></strong><span><?= admin_e((string)$template['description']); ?></span></span>
          </label>
        <?php endforeach; ?>
      </div>
      <p class="admin-field__help"><?= admin_e(t('forms:Skabelonen erstatter kun formularens rækker og felter. Andre formularindstillinger ændres ikke.')); ?></p>
    </div>
    <div class="admin-modal__footer"><button type="button" class="admin-button admin-button--secondary" data-modal-close><?= admin_e(t('Annuller')); ?></button><button type="button" class="admin-button" data-forms-apply-template <?= !$formTemplates ? 'disabled' : ''; ?>><?= admin_e(t('forms:Brug skabelon')); ?></button></div>
  </div>
</dialog>
<dialog class="admin-modal admin-modal--sm" data-admin-modal data-forms-row-settings-dialog aria-labelledby="forms-row-settings-title">
  <div class="admin-modal__surface">
    <div class="admin-modal__header"><div><h2 id="forms-row-settings-title"><?= admin_e(t('forms:Row-indstillinger')); ?></h2><p><?= admin_e(t('forms:Vælg antal kolonner for rækken.')); ?></p></div><button class="admin-modal__close" type="button" data-modal-close aria-label="<?= admin_e(t('Luk')); ?>">×</button></div>
    <div class="admin-modal__body">
      <fieldset class="admin-page-row-layout-picker">
        <legend><?= admin_e(t('forms:Kolonnelayout')); ?></legend>
        <div class="admin-page-row-layout-picker__options">
          <?php foreach (['100' => [1, '100 %'], '50-50' => [2, '50/50']] as $value => [$count, $label]): ?>
            <label class="admin-page-row-layout-choice"><input type="radio" name="forms_row_layout_choice" value="<?= admin_e($value); ?>" data-forms-row-layout-choice><span class="admin-page-row-layout-choice__graphic" aria-hidden="true"><?php for ($i = 0; $i < $count; $i++): ?><span></span><?php endfor; ?></span><span><?= admin_e($label); ?></span></label>
          <?php endforeach; ?>
        </div>
        <p class="admin-field__help"><?= admin_e(t('forms:Felter i kolonne 2 skal fjernes, før du kan vælge én kolonne.')); ?></p>
        <p class="admin-field-error" role="alert" data-forms-layout-warning hidden></p>
      </fieldset>
    </div>
    <div class="admin-modal__footer"><button class="admin-button admin-button--secondary" type="button" data-modal-close><?= admin_e(t('Annuller')); ?></button><button class="admin-button" type="button" data-forms-save-row-settings><?= admin_e(t('forms:Gem indstillinger')); ?></button></div>
  </div>
</dialog>
<template data-forms-row-template="100"><?= forms_admin_builder_row(['layout' => '100', 'fields' => []], '__ROW__', $fieldTypes); ?></template>
<template data-forms-row-template="50-50"><?= forms_admin_builder_row(['layout' => '50-50', 'fields' => []], '__ROW__', $fieldTypes); ?></template>
<template data-forms-field-template><?= forms_admin_field_card([], '__ROW__', '__COL__', $fieldTypes); ?></template>

<script>
(() => {
  const root = document.querySelector('[data-forms-builder]');
  if (!root) return;

  // Core visibility switch maps onto the EXISTING active/inactive POST field.
  // Keep a single server-compatible name=status value and no new schema field.
  const formsVisibility = root.querySelector('[data-forms-visibility-switch]');
  const formsStatus = root.querySelector('[data-forms-status-value]');
  const formsVisibilityState = formsVisibility?.closest('.admin-toggle')?.querySelector('[data-admin-toggle-state]');
  const syncFormsVisibility = () => {
    if (!formsVisibility || !formsStatus) return;
    formsStatus.value = formsVisibility.checked ? 'active' : 'inactive';
    if (formsVisibilityState) formsVisibilityState.textContent = formsVisibility.checked ? formsVisibilityState.dataset.stateOn : formsVisibilityState.dataset.stateOff;
  };
  formsVisibility?.addEventListener('change', syncFormsVisibility);
  root.addEventListener('submit', syncFormsVisibility);
  syncFormsVisibility();

  // Core editor tabs only change the workspace view; all inputs remain in the same POST form.
  const editorTabs = [...root.querySelectorAll('[data-admin-editor-tab]')];
  const editorPanels = [...root.querySelectorAll('[data-admin-editor-panel]')];
  const activateEditorTab = (id, focus = false) => {
    const selected = editorTabs.find((tab) => tab.dataset.adminEditorTab === id);
    if (!selected) return;
    editorTabs.forEach((tab) => {
      const active = tab === selected;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;
    });
    editorPanels.forEach((panel) => {
      panel.hidden = panel.dataset.adminEditorPanel !== id;
    });
    if (focus) selected.focus();
  };
  root.addEventListener('click', (event) => {
    const tab = event.target.closest('[data-admin-editor-tab]');
    if (tab && root.contains(tab)) activateEditorTab(tab.dataset.adminEditorTab);
  });
  root.addEventListener('keydown', (event) => {
    const tab = event.target.closest('[data-admin-editor-tab]');
    if (!tab || !['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const index = editorTabs.indexOf(tab);
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? editorTabs.length - 1
      : (index + (event.key === 'ArrowRight' ? 1 : -1) + editorTabs.length) % editorTabs.length;
    activateEditorTab(editorTabs[next].dataset.adminEditorTab, true);
  });
  // Native browser validation fires invalid before the browser focuses the failing field.
  // Reveal the corresponding hidden panel, including required fields in settings/emails.
  let invalidPanelRevealed = false;
  root.addEventListener('invalid', (event) => {
    // One native constraint-validation pass can emit multiple invalid events.
    // Reveal the FIRST failing control's panel (the one the browser will focus).
    if (invalidPanelRevealed) return;
    const panel = event.target.closest('[data-admin-editor-panel]');
    if (!panel) return;
    invalidPanelRevealed = true;
    queueMicrotask(() => { invalidPanelRevealed = false; });
    activateEditorTab(panel.dataset.adminEditorPanel);
  }, true);

  const rows = root.querySelector('[data-forms-rows]');
  const addButton = root.querySelector('[data-forms-add-element]');
  const choiceDialog = document.querySelector('[data-forms-choice-dialog]');
  const typeDialog = document.querySelector('[data-forms-type-dialog]');
  const templatesDialog = document.querySelector('[data-forms-templates-dialog]');
  const rowSettingsDialog = document.querySelector('[data-forms-row-settings-dialog]');
  const rowLayoutChoices = [...(rowSettingsDialog?.querySelectorAll('[data-forms-row-layout-choice]') || [])];
  const rowLayoutWarning = rowSettingsDialog?.querySelector('[data-forms-layout-warning]');
  let settingsRow = null;
  let settingsReturnFocus = null;
  const templateCatalog = <?= json_encode($formTemplates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR); ?>;
  let pendingColumn = null;
  let pendingFullWidth = false;
  let returnFocus = null;
  const uiText = <?= json_encode([
    'row' => t('forms:Row'),
    'moveKeyboard' => t('forms:Alt + pil op/ned flytter rækken'),
    'column' => t('forms:Kolonne'),
    'oneColumn' => '1 ' . t('Kolonne'),
    'twoColumns' => '2 ' . t('page_builder_v2.columns'),
    'oneField' => t('forms:1 felt'),
    'zeroFields' => t('forms:0 felter'),
    'field' => t('forms:Felt'),
    'required' => t('forms:Påkrævet'),
    'optional' => t('forms:Valgfrit'),
    'edit' => t('forms:Åbn redigering'),
    'close' => t('forms:Luk redigering'),
    'removeField' => t('forms:Fjern dette felt? Ændringen gemmes først, når du gemmer formularen.'),
    'removeFieldTitle' => t('forms:Fjern felt'),
    'replaceTitle' => t('forms:Erstat formularens felter?'),
    'replaceMessage' => t('forms:Skabelonen erstatter alle eksisterende rækker og felter. Ændringen gemmes først, når du gemmer formularen. Vil du fortsætte?'),
    'replaceAccept' => t('forms:Erstat felter'),
    'invalidTemplate' => t('forms:Skabelonen kunne ikke indsættes. Prøv igen.'),
    'layoutBlocked' => t('forms:Kolonne 2 indeholder et felt. Fjern feltet, før du skifter til én kolonne.'),
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const fieldTemplate = document.querySelector('[data-forms-field-template]');
  let draggedRow = null;
  let draggedField = null;

  const typeLabels = <?= json_encode(array_map(static fn(string $label): string => t('forms:' . $label), $fieldTypes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;');

  const slugKey = (value, fallback) => {
    const key = String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '')
      .slice(0, 80);
    return key || fallback;
  };

  const allFields = () => [...rows.querySelectorAll('[data-forms-field]')];

  const refreshEmailSelects = () => {
    const used = new Set();
    const options = [];

    allFields().forEach((field, i) => {
      const type = field.querySelector('[data-forms-type]')?.value || '';
      const label = field.querySelector('[data-forms-label]')?.value.trim() || `${uiText.field} ${i + 1}`;
      const keyInput = field.querySelector('[data-forms-field-key]');
      let key = keyInput?.value.trim() || slugKey(label, `field_${i + 1}`);
      const base = key;
      let suffix = 2;
      while (used.has(key)) key = `${base.slice(0, 74)}_${suffix++}`;
      used.add(key);
      if (keyInput) keyInput.value = key;

      if (type === 'email') {
        options.push({key, label});
      }
    });

    root.querySelectorAll('[data-forms-email-select]').forEach((select) => {
      const current = select.value || select.dataset.selected || '';
      const firstText = select.name === 'reply_to_field_key' ? (window.cmsTranslate?.('Ingen') ?? 'Ingen') : (window.cmsTranslate?.('Vælg e-mailfelt…') ?? 'Vælg e-mailfelt…');
      select.innerHTML = `<option value="">${firstText}</option>` + options
        .map((item) => `<option value="${escapeHtml(item.key)}">${escapeHtml(item.label)}</option>`)
        .join('');
      if (options.some((item) => item.key === current)) select.value = current;
      select.dataset.selected = select.value;
    });
  };

  const updateFieldSummary = (field, fallbackNumber = 1) => {
    const label = field.querySelector('[data-forms-label]')?.value.trim();
    const type = field.querySelector('[data-forms-type]')?.value || 'text';
    const required = field.querySelector('[data-forms-required]')?.checked;
    const title = field.querySelector('[data-forms-field-title]');
    const summary = field.querySelector('[data-forms-field-summary]');
    if (title) title.textContent = label || `${uiText.field} ${fallbackNumber}`;
    if (summary) summary.textContent = `${typeLabels[type] || typeLabels.text} · ${required ? uiText.required : uiText.optional}`;
  };

  const reindex = () => {
    const orderedRows = [...rows.querySelectorAll('[data-forms-row]')];
    orderedRows.forEach((row, rowIndex) => {
      row.dataset.rowIndex = String(rowIndex);
      row.querySelector('[data-forms-row-title]')?.replaceChildren(document.createTextNode(uiText.row));

      const layoutInput = row.querySelector('[data-forms-row-layout]');
      if (layoutInput) layoutInput.name = `rows[${rowIndex}][layout]`;

      [...row.querySelectorAll('[data-forms-column]')].forEach((column) => {
        const columnIndex = Number(column.dataset.columnIndex || 0);
        const field = column.querySelector('[data-forms-field]');
        const countLabel = column.querySelector('[data-forms-column-count]');
        if (countLabel) countLabel.textContent = field ? uiText.oneField : uiText.zeroFields;
        if (!field) return;

        field.querySelectorAll('[name]').forEach((el) => {
          el.name = el.name.replace(/rows\[[^\]]+\]\[fields\]\[[^\]]+\]/, `rows[${rowIndex}][fields][${columnIndex}]`);
        });
        // A field can be moved into a different column or created from a template.
        // Rebind native Core Toggle input + label IDs, never duplicate an ID.
        const required = field.querySelector('[data-forms-required]');
        if (required) {
          const id = `forms-required-${rowIndex}-${columnIndex}`;
          required.id = id;
          const label = required.closest('label');
          if (label) label.htmlFor = id;
        }
      });
    });

    allFields().forEach((field, index) => updateFieldSummary(field, index + 1));
    refreshEmailSelects();
  };

  const toggleConditional = (toggleSelector, contentSelector) => {
    const toggle = root.querySelector(toggleSelector);
    const content = root.querySelector(contentSelector);
    if (toggle && content) content.hidden = !toggle.checked;
  };

  const updateSuccess = () => {
    const select = root.querySelector('[data-forms-success-action]');
    const message = root.querySelector('[data-forms-success-message]');
    const redirect = root.querySelector('[data-forms-success-redirect]');
    if (!select) return;
    if (message) message.hidden = select.value === 'redirect';
    if (redirect) redirect.hidden = select.value !== 'redirect';
  };

  const openDialog = (dialog) => {
    if (!dialog) return;
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
  };
  const closeDialog = (dialog) => {
    if (!dialog) return;
    if (typeof dialog.close === 'function') dialog.close();
    else dialog.removeAttribute('open');
  };
  // Row settings only affect the currently selected row after an explicit save.
  // Never discard a field when shrinking from two columns to one.
  const clearRowLayoutWarning = () => {
    if (!rowLayoutWarning) return;
    rowLayoutWarning.hidden = true;
    rowLayoutWarning.textContent = '';
  };
  const openRowSettings = (row, trigger) => {
    if (!row || !rowSettingsDialog) return;
    settingsRow = row;
    settingsReturnFocus = trigger;
    const currentLayout = row.querySelector('[data-forms-row-layout]')?.value || '100';
    rowLayoutChoices.forEach((choice) => { choice.checked = choice.value === currentLayout; });
    clearRowLayoutWarning();
    openDialog(rowSettingsDialog);
    rowLayoutChoices.find((choice) => choice.checked)?.focus();
  };
  rowSettingsDialog?.addEventListener('close', () => {
    settingsRow = null;
    clearRowLayoutWarning();
    if (settingsReturnFocus?.isConnected) settingsReturnFocus.focus({preventScroll: true});
    settingsReturnFocus = null;
  });
  rowLayoutChoices.forEach((choice) => choice.addEventListener('change', clearRowLayoutWarning));
  rowSettingsDialog?.querySelector('[data-forms-save-row-settings]')?.addEventListener('click', () => {
    if (!settingsRow?.isConnected) return;
    const chosen = rowLayoutChoices.find((choice) => choice.checked)?.value;
    if (!['100', '50-50'].includes(chosen)) return;
    const layoutInput = settingsRow.querySelector('[data-forms-row-layout]');
    const container = settingsRow.querySelector('.forms-row__columns');
    if (!layoutInput || !container) return;
    const columns = [...container.querySelectorAll(':scope > [data-forms-column]')];
    if (chosen === '100' && columns.length === 2 && columns[1].querySelector('[data-forms-field]')) {
      if (rowLayoutWarning) {
        rowLayoutWarning.textContent = uiText.layoutBlocked;
        rowLayoutWarning.hidden = false;
        rowLayoutWarning.tabIndex = -1;
        rowLayoutWarning.focus();
      }
      return;
    }
    if (chosen === '50-50' && columns.length === 1) {
      const second = document.querySelector('[data-forms-row-template="50-50"]')
        ?.content?.querySelector('[data-forms-column][data-column-index="1"]')?.cloneNode(true);
      if (!second) return;
      container.appendChild(second);
    } else if (chosen === '100' && columns.length === 2) {
      columns[1].remove();
    }
    layoutInput.value = chosen;
    container.classList.toggle('forms-row__columns--2', chosen === '50-50');
    const label = settingsRow.querySelector('[data-forms-row-layout-label]');
    if (label) label.textContent = chosen === '50-50' ? uiText.twoColumns : uiText.oneColumn;
    reindex();
    closeDialog(rowSettingsDialog);
  });

  const addRow = (layout) => {
    const normalized = layout === '50-50' ? '50-50' : '100';
    const template = document.querySelector(`[data-forms-row-template="${normalized}"]`);
    if (!template) return null;

    const rowIndex = rows.querySelectorAll('[data-forms-row]').length;
    const wrap = document.createElement('div');
    wrap.innerHTML = template.innerHTML.replaceAll('__ROW__', String(rowIndex)).trim();
    const row = wrap.firstElementChild;
    if (!row) return null;
    rows.appendChild(row);
    reindex();
    return row;
  };

  const addFieldToColumn = (column, type) => {
    if (!column || column.querySelector('[data-forms-field]') || !fieldTemplate || !Object.hasOwn(typeLabels, type)) return false;
    const row = column.closest('[data-forms-row]');
    if (!row) return false;

    const rowIndex = [...rows.querySelectorAll('[data-forms-row]')].indexOf(row);
    const columnIndex = Number(column.dataset.columnIndex || 0);
    const wrap = document.createElement('div');
    wrap.innerHTML = fieldTemplate.innerHTML
      .replaceAll('__ROW__', String(rowIndex))
      .replaceAll('__COL__', String(columnIndex))
      .trim();
    const field = wrap.firstElementChild;
    if (!field) return false;
    field.querySelector('[data-forms-type]').value = type;
    const options = field.querySelector('[data-forms-options]');
    if (options) options.hidden = !['select', 'radio'].includes(type);

    column.querySelector('.forms-column__content')?.appendChild(field);
    const empty = column.querySelector('[data-forms-column-empty]');
    if (empty) empty.hidden = true;
    reindex();
    setFieldOpen(field, true);
    field.querySelector('[data-forms-label]')?.focus();
    return true;
  };

  // Construct a complete replacement off-DOM so failed/cancelled actions leave
  // all existing fields, hidden keys, positions and settings untouched.
  const instantiateTemplate = (template) => {
    if (!template || !Array.isArray(template.rows) || !template.rows.length || template.rows.length > 100) return null;
    const built = [];
    const reservedKeys = new Set(allFields().map((field) => field.querySelector('[data-forms-field-key]')?.value).filter(Boolean));
    let count = 0;
    for (const rowData of template.rows) {
      if (!['100', '50-50'].includes(rowData?.layout) || !Array.isArray(rowData.fields)
        || rowData.fields.length < 1 || rowData.fields.length > (rowData.layout === '50-50' ? 2 : 1)) return null;
      const rowTemplate = document.querySelector(`[data-forms-row-template="${rowData.layout}"]`);
      if (!rowTemplate) return null;
      const wrapper = document.createElement('div');
      wrapper.innerHTML = rowTemplate.innerHTML.replaceAll('__ROW__', String(built.length)).trim();
      const row = wrapper.firstElementChild;
      if (!row) return null;
      for (const [columnIndex, definition] of rowData.fields.entries()) {
        if (!definition || !Object.hasOwn(typeLabels, definition.field_type)
          || typeof definition.label !== 'string' || !definition.label.trim()
          || (['select', 'radio'].includes(definition.field_type) && (!Array.isArray(definition.options) || !definition.options.length))) return null;
        const column = [...row.querySelectorAll('[data-forms-column]')][columnIndex];
        if (!column || !fieldTemplate) return null;
        const node = document.createElement('div');
        node.innerHTML = fieldTemplate.innerHTML.replaceAll('__ROW__', String(built.length)).replaceAll('__COL__', String(columnIndex)).trim();
        const field = node.firstElementChild;
        if (!field) return null;
        field.querySelector('[data-forms-type]').value = definition.field_type;
        field.querySelector('[data-forms-label]').value = definition.label.slice(0, 191);
        const fields = {
          '[name$="[placeholder]"]': String(definition.placeholder || '').slice(0, 255),
          '[name$="[help_text]"]': String(definition.help_text || '').slice(0, 500),
        };
        Object.entries(fields).forEach(([selector, value]) => {
          const input = field.querySelector(selector);
          if (input) input.value = value;
        });
        const required = field.querySelector('[data-forms-required]');
        if (required) required.checked = definition.is_required === true;
        const optionsBox = field.querySelector('[data-forms-options]');
        if (optionsBox) {
          optionsBox.hidden = !['select','radio'].includes(definition.field_type);
          const textarea = optionsBox.querySelector('textarea');
          if (textarea) textarea.value = Array.isArray(definition.options) ? definition.options.join('\n') : '';
        }
        const keyInput = field.querySelector('[data-forms-field-key]');
        if (!keyInput) return null;
        let key = slugKey(definition.label, `field_${++count}`);
        const base = key;
        let suffix = 2;
        while (reservedKeys.has(key)) key = `${base.slice(0, 74)}_${suffix++}`;
        reservedKeys.add(key);
        keyInput.value = key;
        field.querySelector('[data-forms-field-editor]').hidden = true;
        field.querySelector('[data-forms-edit-field]').setAttribute('aria-expanded', 'false');
        column.querySelector('.forms-column__content')?.appendChild(field);
        column.querySelector('[data-forms-column-empty]').hidden = true;
      }
      built.push(row);
    }
    return built;
  };

  const setFieldOpen = (field, open) => {
    const body = field?.querySelector('[data-forms-field-editor]');
    const toggle = field?.querySelector('[data-forms-edit-field]');
    if (!body || !toggle) return;
    body.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    // Core chevron stays mounted; its orientation follows aria-expanded via CSS.
    toggle.setAttribute('aria-label', open ? uiText.close : uiText.edit);
    toggle.setAttribute('title', open ? uiText.close : uiText.edit);
  };

  const requestFieldType = (column, trigger = null) => {
    if (!column || column.querySelector('[data-forms-field]')) return;
    pendingColumn = column;
    pendingFullWidth = false;
    returnFocus = trigger || column.querySelector('[data-forms-add-field]');
    openDialog(typeDialog);
  };

  addButton?.addEventListener('click', () => {
    pendingColumn = null;
    pendingFullWidth = false;
    returnFocus = addButton;
    openDialog(choiceDialog);
  });
  root.querySelector('[data-forms-open-templates]')?.addEventListener('click', () => openDialog(templatesDialog));
  templatesDialog?.querySelector('[data-forms-apply-template]')?.addEventListener('click', async (event) => {
    const selected = templatesDialog.querySelector('[data-forms-template-option]:checked')?.value;
    const template = templateCatalog.find((item) => item.id === selected);
    const replacement = instantiateTemplate(template);
    if (!replacement) {
      window.adminNotify?.(uiText.invalidTemplate);
      return;
    }
    // Even a row without fields is user-created content; never remove it silently.
    if (rows.querySelector('[data-forms-row]')) {
      const confirmed = await window.adminConfirm(uiText.replaceMessage, uiText.replaceTitle, uiText.replaceAccept);
      if (!confirmed) return;
    }
    rows.replaceChildren(...replacement);
    reindex();
    closeDialog(templatesDialog);
    addButton?.focus({preventScroll: true});
  });


  choiceDialog?.querySelectorAll('[data-forms-choose-element]').forEach((button) => {
    button.addEventListener('click', () => {
      const selection = button.dataset.formsChooseElement;
      closeDialog(choiceDialog);
      if (selection === 'row') {
        const row = addRow('50-50');
        row?.querySelector('[data-forms-add-field]')?.focus();
      } else if (selection === 'field') {
        pendingColumn = null;
        pendingFullWidth = true;
        openDialog(typeDialog);
      }
    });
  });
  typeDialog?.querySelectorAll('[data-forms-choose-type]').forEach((button) => {
    button.addEventListener('click', () => {
      const type = button.dataset.formsChooseType || '';
      if (!Object.hasOwn(typeLabels, type)) return;
      // A cancelled chooser must never create an empty 100% row.
      let column = pendingColumn;
      if (pendingFullWidth) column = addRow('100')?.querySelector('[data-forms-column]') || null;
      pendingColumn = null;
      pendingFullWidth = false;
      closeDialog(typeDialog);
      addFieldToColumn(column, type);
    });
  });
  typeDialog?.addEventListener('close', () => {
    pendingColumn = null;
    pendingFullWidth = false;
    if (returnFocus?.isConnected && document.activeElement === document.body) returnFocus.focus({ preventScroll: true });
  });
  choiceDialog?.addEventListener('close', () => {
    if (returnFocus?.isConnected && !typeDialog?.open && document.activeElement === document.body) returnFocus.focus({ preventScroll: true });
  });

  const removeField = (field) => {
    const column = field.closest('[data-forms-column]');
    field.remove();
    const empty = column?.querySelector('[data-forms-column-empty]');
    if (empty) empty.hidden = false;
    reindex();
  };

  root.addEventListener('click', async (event) => {
    const editButton = event.target.closest('[data-forms-edit-field]');
    if (editButton) {
      const field = editButton.closest('[data-forms-field]');
      const expanded = editButton.getAttribute('aria-expanded') === 'true';
      setFieldOpen(field, !expanded);
      if (!expanded) field?.querySelector('[data-forms-label]')?.focus();
      return;
    }
    const addFieldButton = event.target.closest('[data-forms-add-field]');
    if (addFieldButton) {
      requestFieldType(addFieldButton.closest('[data-forms-column]'), addFieldButton);
      return;
    }

    const settingsButton = event.target.closest('[data-forms-row-settings]');
    if (settingsButton) {
      openRowSettings(settingsButton.closest('[data-forms-row]'), settingsButton);
      return;
    }

    const removeRowButton = event.target.closest('[data-forms-remove-row]');
    if (removeRowButton) {
      if (await window.adminConfirm((window.cmsTranslate?.('Fjern rækken og alle felter i den? Ændringen gemmes først, når du gemmer formularen.') ?? 'Fjern rækken og alle felter i den? Ændringen gemmes først, når du gemmer formularen.'), (window.cmsTranslate?.('Fjern række') ?? 'Fjern række'), (window.cmsTranslate?.('Fjern række') ?? 'Fjern række'))) {
        removeRowButton.closest('[data-forms-row]')?.remove();
        reindex();
      }
      return;
    }

    const removeFieldButton = event.target.closest('[data-forms-remove-field]');
    if (removeFieldButton) {
      const field = removeFieldButton.closest('[data-forms-field]');
      if (field && await window.adminConfirm(uiText.removeField, uiText.removeFieldTitle, uiText.removeFieldTitle)) removeField(field);
    }
  });

  // A required label inside a collapsed card must become focusable when
  // native constraint validation runs; otherwise the browser cannot focus it.
  root.addEventListener('invalid', (event) => {
    if (event.target.matches('[data-forms-label]')) {
      setFieldOpen(event.target.closest('[data-forms-field]'), true);
    }
  }, true);

  allFields().forEach((field) => {
    if (!field.querySelector('[data-forms-label]')?.value.trim()) setFieldOpen(field, true);
  });

  root.addEventListener('input', (event) => {
    if (event.target.matches('[data-forms-label]')) {
      const field = event.target.closest('[data-forms-field]');
      if (field) updateFieldSummary(field, allFields().indexOf(field) + 1);
      refreshEmailSelects();
    }
  });

  root.addEventListener('change', (event) => {
    if (event.target.matches('[data-forms-required]')) {
      const field = event.target.closest('[data-forms-field]');
      if (field) updateFieldSummary(field, allFields().indexOf(field) + 1);
    }
    if (event.target.matches('[data-forms-success-action]')) updateSuccess();
    if (event.target.matches('[data-forms-notification-toggle]')) toggleConditional('[data-forms-notification-toggle]','[data-forms-notification-options]');
    if (event.target.matches('[data-forms-confirmation-toggle]')) toggleConditional('[data-forms-confirmation-toggle]','[data-forms-confirmation-options]');
    if (event.target.matches('[data-forms-email-select]')) event.target.dataset.selected = event.target.value;
  });

  // The same compact row grip as Page Builder replaces persistent up/down arrows.
  // Alt+ArrowUp/ArrowDown gives keyboard users an equivalent reorder action.
  root.addEventListener('keydown', (event) => {
    const handle = event.target.closest('[data-forms-row-drag]');
    if (!handle || !event.altKey || event.ctrlKey || event.metaKey || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
    const row = handle.closest('[data-forms-row]');
    const sibling = event.key === 'ArrowUp' ? row?.previousElementSibling : row?.nextElementSibling;
    if (!row || !sibling || !sibling.matches('[data-forms-row]')) return;
    event.preventDefault();
    if (event.key === 'ArrowUp') rows.insertBefore(row, sibling);
    else rows.insertBefore(sibling, row);
    reindex();
    handle.focus();
  });

  root.addEventListener('keydown', (event) => {
    const grip = event.target.closest('[data-forms-field-drag]');
    if (!grip || !event.altKey || event.ctrlKey || event.metaKey || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
    const field = grip.closest('[data-forms-field]');
    const source = field?.closest('[data-forms-column]');
    const dest = event.key === 'ArrowRight' ? source?.nextElementSibling : source?.previousElementSibling;
    if (!source || !dest?.matches('[data-forms-column]') || dest.querySelector('[data-forms-field]')) return;
    event.preventDefault();
    dest.querySelector('.forms-column__content')?.appendChild(field);
    const oldEmpty = source.querySelector('[data-forms-column-empty]');
    const newEmpty = dest.querySelector('[data-forms-column-empty]');
    if (oldEmpty) oldEmpty.hidden = false;
    if (newEmpty) newEmpty.hidden = true;
    reindex();
    grip.focus();
  });

  root.addEventListener('dragstart', (event) => {
    const fieldGrip = event.target.closest('[data-forms-field-drag]');
    if (fieldGrip) {
      draggedField = fieldGrip.closest('[data-forms-field]');
      if (!draggedField || !event.dataTransfer) return;
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', 'forms-field');
      const rect = draggedField.getBoundingClientRect();
      event.dataTransfer.setDragImage(draggedField, Math.max(0, Math.round(event.clientX - rect.left)), Math.max(0, Math.round(event.clientY - rect.top)));
      draggedField.classList.add('is-dragging');
      return;
    }
    const handle = event.target.closest('[data-forms-row-drag]');
    if (!handle) {
      // A draggable row must not hijack text selection or field editing.
      if (event.target.closest('[data-forms-row]')) event.preventDefault();
      return;
    }
    draggedRow = handle.closest('[data-forms-row]');
    if (!draggedRow || !event.dataTransfer) return;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', 'forms-row');
    // Use the whole row for the drag ghost, exactly like Page Builder.
    const rect = draggedRow.getBoundingClientRect();
    event.dataTransfer.setDragImage(draggedRow, Math.max(0, Math.round(event.clientX - rect.left)), Math.max(0, Math.round(event.clientY - rect.top)));
    draggedRow.classList.add('is-dragging');
  });

  const emptyDropColumn = (target) => {
    const column = target.closest('[data-forms-column]');
    if (!column?.closest('[data-forms-rows]')) return null;
    const existing = column.querySelector('[data-forms-field]');
    return existing && existing !== draggedField ? null : column;
  };

  rows.addEventListener('dragover', (event) => {
    if (draggedField) {
      const column = emptyDropColumn(event.target);
      if (!column) return;
      event.preventDefault();
      event.stopPropagation();
      if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
      return;
    }
    if (!draggedRow) return;
    event.preventDefault();
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
    // The midpoint algorithm handles the whitespace between rows as well as row bodies.
    const siblings = [...rows.querySelectorAll(':scope > [data-forms-row]')].filter((row) => row !== draggedRow);
    const next = siblings.find((row) => {
      const rect = row.getBoundingClientRect();
      return event.clientY < rect.top + rect.height / 2;
    });
    if (next) rows.insertBefore(draggedRow, next);
    else rows.appendChild(draggedRow);
  });

  rows.addEventListener('drop', (event) => {
    if (draggedField) {
      const destination = emptyDropColumn(event.target);
      if (!destination) return;
      event.preventDefault();
      event.stopPropagation();
      const source = draggedField.closest('[data-forms-column]');
      if (source && source !== destination) {
        destination.querySelector('.forms-column__content')?.appendChild(draggedField);
        const oldEmpty = source.querySelector('[data-forms-column-empty]');
        const newEmpty = destination.querySelector('[data-forms-column-empty]');
        if (oldEmpty) oldEmpty.hidden = false;
        if (newEmpty) newEmpty.hidden = true;
        reindex(); // Preserve field_key while rebinding the new row/column names.
      }
      return;
    }
    if (!draggedRow) return;
    event.preventDefault();
    reindex();
  });

  root.addEventListener('dragend', () => {
    if (draggedRow) draggedRow.classList.remove('is-dragging');
    if (draggedField) draggedField.classList.remove('is-dragging');
    draggedRow = null;
    draggedField = null;
    reindex();
  });

  // New forms start empty; do not create a row unless the user selects one.
  updateSuccess();
  toggleConditional('[data-forms-notification-toggle]','[data-forms-notification-options]');
  toggleConditional('[data-forms-confirmation-toggle]','[data-forms-confirmation-options]');
  reindex();
})();
</script>

<?php
/** @return array<int,array{layout:string,fields:array<int,array<string,mixed>>}> */
function forms_admin_group_rows(array $fields, array $savedRows = []): array
{
    $rows = [];
    $savedOrderMap = [];
    // A row is a first-class layout item even with zero fields. Preserve its
    // original position relative to legacy and populated rows.
    foreach ($savedRows as $savedRow) {
        if (!is_array($savedRow)) continue;
        $order = max(0, (int)($savedRow['row_order'] ?? 0));
        $savedOrderMap[$order] = true;
        $rows[$order] = [
            'layout' => ($savedRow['layout'] ?? '100') === '50-50' ? '50-50' : '100',
            'fields' => [],
        ];
    }
    foreach ($fields as $fallback => $field) {
        if (!is_array($field)) continue;
        $rowOrder = max(0, (int)($field['row_order'] ?? $fallback));
        $layout = (string)($field['row_layout'] ?? '100') === '50-50' ? '50-50' : '100';
        $columnIndex = $layout === '50-50' ? max(0, min(1, (int)($field['column_index'] ?? 0))) : 0;

        if (!isset($rows[$rowOrder])) {
            $rows[$rowOrder] = ['layout' => $layout, 'fields' => []];
        }

        // If corrupted/legacy data tries to occupy an existing column, retain
        // every field by moving the duplicate into a fresh 100% row.
        if (isset($rows[$rowOrder]['fields'][$columnIndex])) {
            $newOrder = $rows ? (max(array_keys($rows)) + 1) : 0;
            $rows[$newOrder] = ['layout' => '100', 'fields' => [0 => $field]];
            continue;
        }

        // Saved row metadata takes precedence; legacy-only rows use fields.
        if (!isset($savedOrderMap[$rowOrder]) || ($columnIndex === 1 && $rows[$rowOrder]['layout'] === '100')) {
            // Never hide a real field if legacy/corrupt metadata conflicts with a two-column slot.
            $rows[$rowOrder]['layout'] = $layout;
        }
        $rows[$rowOrder]['fields'][$columnIndex] = $field;
    }

    ksort($rows);
    foreach ($rows as &$row) {
        ksort($row['fields']);
    }
    unset($row);

    return array_values($rows);
}

function forms_admin_builder_row(array $row, string $rowIndex, array $fieldTypes): string
{
    $layout = (string)($row['layout'] ?? '100') === '50-50' ? '50-50' : '100';
    $fields = is_array($row['fields'] ?? null) ? $row['fields'] : [];
    $columnCount = $layout === '50-50' ? 2 : 1;
    $layoutLabel = $columnCount . ' ' . t($columnCount === 1 ? 'Kolonne' : 'page_builder_v2.columns');

    ob_start(); ?>
    <article class="admin-builder-block admin-page-row forms-row" data-forms-row data-row-index="<?= admin_e($rowIndex); ?>" draggable="true">
      <header class="admin-builder-block__header">
        <button class="admin-builder-drag" type="button" data-forms-row-drag draggable="true" aria-label="<?= admin_e(t('Træk for at flytte række') . '. ' . t('forms:Alt + pil op/ned flytter rækken')); ?>" title="<?= admin_e(t('Træk for at flytte') . ' · ' . t('forms:Alt + pil op/ned flytter rækken')); ?>">⋮⋮</button>
        <div class="admin-page-row__heading forms-row__heading">
          <strong data-forms-row-title><?= admin_e(t('forms:Row')); ?></strong>
          <span class="admin-page-row__layout" data-forms-row-layout-label><?= admin_e($layoutLabel); ?></span>
        </div>
        <div class="admin-builder-block__actions admin-page-row__actions">
          <?= admin_icon_action_button('settings', t('forms:Row-indstillinger'), ['data-forms-row-settings' => '1']); ?>
          <?= admin_icon_action_button('delete', t('forms:Fjern række'), ['data-forms-remove-row' => '1'], 'danger'); ?>
        </div>
      </header>
      <input type="hidden" name="rows[<?= admin_e($rowIndex); ?>][layout]" value="<?= admin_e($layout); ?>" data-forms-row-layout>
      <div class="admin-builder-block__body">
        <div class="forms-row__columns <?= $columnCount === 2 ? 'forms-row__columns--2' : ''; ?>">
          <?php for ($columnIndex = 0; $columnIndex < $columnCount; $columnIndex++): ?>
            <?php $field = is_array($fields[$columnIndex] ?? null) ? $fields[$columnIndex] : null; ?>
            <div class="forms-column" data-forms-column data-column-index="<?= $columnIndex; ?>">
              <header class="forms-column__header"><strong><?= admin_e(t('forms:Kolonne')); ?> <?= $columnIndex + 1; ?></strong><span data-forms-column-count><?= admin_e(t($field !== null ? 'forms:1 felt' : 'forms:0 felter')); ?></span></header>
              <div class="forms-column__content">
                <?php if ($field !== null): ?>
                  <?= forms_admin_field_card($field, $rowIndex, (string)$columnIndex, $fieldTypes); ?>
                <?php endif; ?>
                <?= forms_admin_empty_column($fieldTypes, $field !== null); ?>
              </div>
            </div>
          <?php endfor; ?>
        </div>
      </div>
    </article>
    <?php return (string)ob_get_clean();
}

function forms_admin_empty_column(array $fieldTypes, bool $hidden = false): string
{
    ob_start(); ?>
    <div class="forms-column__empty" data-forms-column-empty <?= $hidden ? 'hidden' : ''; ?>>
      <button class="forms-column__add" type="button" data-forms-add-field><?= admin_icon('plus', 'forms-column__add-icon'); ?><span><?= admin_e(t('forms:Tilføj felt')); ?></span></button>
    </div>
    <?php return (string)ob_get_clean();
}

function forms_admin_field_card(array $field, string $rowIndex, string $columnIndex, array $fieldTypes): string
{
    $label = (string)($field['label'] ?? '');
    $type = (string)($field['field_type'] ?? 'text');
    if (!array_key_exists($type, $fieldTypes)) $type = 'text';
    $key = (string)($field['field_key'] ?? '');
    $options = is_array($field['options'] ?? null) ? implode("\n", $field['options']) : (string)($field['options'] ?? '');
    $typeLabel = t('forms:' . (string)($fieldTypes[$type] ?? 'Tekst'));
    $requiredLabel = !empty($field['is_required']) ? t('forms:Påkrævet') : t('forms:Valgfrit');
    $prefix = 'rows[' . $rowIndex . '][fields][' . $columnIndex . ']';

    ob_start(); ?>
    <article class="admin-builder-block admin-builder-block--simple forms-field" data-forms-field draggable="true">
      <header class="admin-builder-block__header">
        <button class="admin-grid-child__drag" type="button" data-forms-field-drag draggable="true" aria-label="<?= admin_e(t('Flyt blok')); ?>" title="<?= admin_e(t('Træk for at flytte')); ?>">⋮⋮</button>
        <div class="admin-builder-block__title">
          <strong data-forms-field-title><?= admin_e($label !== '' ? $label : t('forms:Nyt felt')); ?></strong>
          <span data-forms-field-summary><?= admin_e($typeLabel . ' · ' . $requiredLabel); ?></span>
        </div>
        <div class="admin-builder-block__actions">
          <?= admin_icon_action_button('chevron-down', t('forms:Åbn redigering'), ['data-forms-edit-field' => '1', 'aria-expanded' => 'false']); ?>
          <?= admin_icon_action_button('delete', t('forms:Fjern felt'), ['data-forms-remove-field' => '1'], 'danger'); ?>
        </div>
      </header>
      <div class="admin-builder-block__body" data-forms-field-editor hidden>
        <input type="hidden" name="<?= admin_e($prefix); ?>[field_key]" value="<?= admin_e($key); ?>" data-forms-field-key>
        <input type="hidden" name="<?= admin_e($prefix); ?>[field_type]" value="<?= admin_e($type); ?>" data-forms-type>

        <div class="forms-field__editor-stack">
          <div class="admin-field forms-field__validation">
            <?= AdminToggle::render('forms-required-' . substr(hash('sha256', $prefix), 0, 16), t('forms:Validering'), !empty($field['is_required']), '', ['name' => $prefix . '[is_required]', 'value' => '1', 'data-forms-required' => '1'], ['on' => t('forms:Påkrævet'), 'off' => t('forms:Valgfrit')]); ?>
          </div>
          <div class="admin-field">
            <label><?= admin_e(t('forms:Label *')); ?></label>
            <input name="<?= admin_e($prefix); ?>[label]" value="<?= admin_e($label); ?>" maxlength="191" data-forms-label required>
          </div>
          <div class="admin-field">
            <label><?= admin_e(t('forms:Placeholder')); ?></label>
            <input name="<?= admin_e($prefix); ?>[placeholder]" value="<?= admin_e((string)($field['placeholder'] ?? '')); ?>" maxlength="255">
          </div>

          <div class="admin-field">
            <label><?= admin_e(t('forms:Hjælpetekst')); ?></label>
            <input name="<?= admin_e($prefix); ?>[help_text]" value="<?= admin_e((string)($field['help_text'] ?? '')); ?>" maxlength="500">
          </div>

          <div class="admin-field" data-forms-options <?= !in_array($type, ['select', 'radio'], true) ? 'hidden' : ''; ?>>
            <label><?= admin_e(t('forms:Valgmuligheder')); ?></label>
            <textarea class="forms-field__options" name="<?= admin_e($prefix); ?>[options]" rows="4" placeholder="<?= admin_e(t('forms:Én mulighed pr. linje.')); ?>"><?= admin_e($options); ?></textarea>
            <p class="admin-field__help"><?= admin_e(t('forms:Én mulighed pr. linje.')); ?></p>
          </div>
        </div>
      </div>
    </article>
    <?php return (string)ob_get_clean();
}
?>