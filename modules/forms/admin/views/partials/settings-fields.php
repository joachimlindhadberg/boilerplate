<?php
$formData = is_array($formData ?? null) ? $formData : [];
$errors = is_array($errors ?? null) ? $errors : [];
$fieldError = static fn(string $key): string => t((string)($errors[$key] ?? ''));
?>
<?php if (!empty($notice)): ?><div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div><?php endif; ?>
<?php if ($fieldError('form') !== ''): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$fieldError('form'))); ?></div><?php endif; ?>
<form method="post" action="/admin/forms/settings" class="admin-form" data-admin-track-changes>
  <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
  <div class="admin-editor-bar">
    <div class="admin-editor-bar__heading">
      <h2><?= admin_e(t('forms:Forms · Indstillinger')); ?></h2>
      <p><?= admin_e(t('forms:Standarder og spam-beskyttelse for nye formularer.')); ?></p>
    </div>
    <div class="admin-form-actions"><a class="admin-button admin-button--secondary" href="/admin/forms"><?= admin_e(t('Annuller')); ?></a><button class="admin-button" type="submit"><?= admin_e(t('Gem indstillinger')); ?></button></div>
  </div>
  <div class="admin-detail-grid">
    <div class="admin-detail-content">
      <section class="admin-panel">
        <div class="admin-panel__header"><h2><?= admin_e(t('forms:Standarder for nye formularer')); ?></h2></div>
        <div class="admin-form-section">
          <label class="admin-checkbox"><input type="checkbox" name="default_save_submissions" value="1" <?= !empty($formData['default_save_submissions'])?'checked':''; ?>><span><?= admin_e(t('forms:Gem indsendelser som standard')); ?></span></label>
          <label class="admin-checkbox"><input type="checkbox" name="default_send_notification" value="1" <?= !empty($formData['default_send_notification'])?'checked':''; ?>><span><?= admin_e(t('forms:Send e-mail-notifikation som standard')); ?></span></label>
          <div class="admin-field"><label for="forms-default-success"><?= admin_e(t('forms:Standard kvitteringstekst *')); ?></label><textarea id="forms-default-success" name="default_success_message" rows="4" maxlength="1000" required><?= admin_e((string)($formData['default_success_message'] ?? 'Thank you. Your message has been sent.')); ?></textarea><?php if($fieldError('default_success_message')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('default_success_message'))); ?></p><?php endif; ?><p class="admin-field__help"><?= admin_e(t('forms:Bruges kun som udgangspunkt, når en ny formular oprettes.')); ?></p></div>
        </div>
      </section>
      <section class="admin-panel">
        <div class="admin-panel__header"><h2><?= admin_e(t('forms:Rate limiting')); ?></h2></div>
        <div class="admin-form-section">
          <div class="admin-form-row">
            <div class="admin-field"><label for="forms-rate-count"><?= admin_e(t('forms:Maks. indsendelser')); ?></label><input id="forms-rate-count" type="number" min="1" max="30" name="rate_limit_count" value="<?= (int)($formData['rate_limit_count'] ?? 6); ?>"><p class="admin-field__help"><?= admin_e(t('forms:Pr. IP-adresse og formular inden for tidsvinduet.')); ?></p></div>
            <div class="admin-field"><label for="forms-rate-window"><?= admin_e(t('forms:Tidsvindue i minutter')); ?></label><input id="forms-rate-window" type="number" min="1" max="60" name="rate_limit_window_minutes" value="<?= (int)($formData['rate_limit_window_minutes'] ?? 10); ?>"></div>
          </div>
          <p class="admin-field__help"><?= admin_e(t('forms:Engangstoken, honeypot og server-side validering er altid aktive og kan ikke slås fra.')); ?></p>
        </div>
      </section>
    </div>
    <aside class="admin-detail-sidebar">
      <section class="admin-panel"><div class="admin-panel__header"><h2><?= admin_e(t('forms:Om indstillingerne')); ?></h2></div><div class="admin-form-section"><p><?= admin_e(t('forms:Ændringer her påvirker kun nye formularer, bortset fra rate limiting, som gælder alle Forms-indsendelser med det samme.')); ?></p></div></section>
    </aside>
  </div>
</form>
