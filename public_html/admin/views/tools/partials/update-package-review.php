<?php
// The uploaded file is inspected only for this HTTP request; nothing is staged
// permanently, and no operation is offered to install an archive or run SQL.
?>
<section class="admin-panel admin-tools-section" aria-labelledby="update-package-review-title">
  <div class="admin-panel__header admin-tools-section__header">
    <div><h2 id="update-package-review-title"><?= admin_e(t('update_package.title')); ?></h2><p><?= admin_e(t('update_package.description')); ?></p></div>
  </div>
  <div class="admin-tools-body">
    <?php if ($packageError !== ''): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t($packageError)); ?></div><?php endif; ?>
    <form method="post" action="/admin/vaerktoejer/update" enctype="multipart/form-data" class="admin-form">
      <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
      <input type="hidden" name="action" value="review_update_package">
      <div class="admin-field">
        <span class="admin-field__label"><?= admin_e(t('update_package.file')); ?></span>
        <label class="admin-upload-dropzone"
               data-admin-upload-dropzone
               data-upload-types="application/zip,application/x-zip-compressed"
               data-upload-extensions=".zip"
               data-upload-max-bytes="20971520"
               data-upload-type-error="<?= admin_e(t('update_package.drop_type_error')); ?>"
               data-upload-size-error="<?= admin_e(t('update_package.drop_size_error')); ?>"
               for="update-package-zip">
          <input class="admin-upload-dropzone__input"
                 id="update-package-zip"
                 type="file"
                 name="update_package"
                 accept=".zip,application/zip,application/x-zip-compressed"
                 required
                 data-admin-upload-input>
          <span class="admin-upload-dropzone__body">
            <?= admin_icon('upload', 'admin-upload-dropzone__icon'); ?>
            <span class="admin-upload-dropzone__title"><?= admin_e(t('update_package.drop_title')); ?></span>
            <span class="admin-upload-dropzone__text"><?= admin_e(t('update_package.drop_or')); ?> <span class="admin-upload-dropzone__browse"><?= admin_e(t('update_package.drop_browse')); ?></span></span>
          </span>
          <span class="admin-upload-dropzone__selection" data-admin-upload-selection hidden>
            <span class="admin-upload-dropzone__preview" data-admin-upload-preview aria-hidden="true"></span>
            <span class="admin-upload-dropzone__filemeta">
              <strong data-admin-upload-name></strong>
              <span data-admin-upload-size></span>
            </span>
            <span class="admin-upload-dropzone__change"><?= admin_e(t('update_package.drop_change')); ?></span>
          </span>
        </label>
        <div class="admin-upload-dropzone__meta">
          <span><?= admin_e(t('update_package.drop_format')); ?></span>
          <span><?= admin_e(t('update_package.limit')); ?></span>
        </div>
      </div>
      <div class="admin-form-actions admin-form-actions--left">
        <button type="submit" class="admin-button admin-button--secondary"><?= admin_icon('search', 'admin-button__icon'); ?><?= admin_e(t('update_package.inspect')); ?></button>
      </div>
    </form>
    <p class="admin-field__help"><?= admin_e(t('update_package.no_install')); ?></p>
    <?php if ($packageReport !== null): ?>
      <div class="admin-notice admin-notice--<?= $packageReport['status'] === 'blocked' ? 'error' : 'warning'; ?>" role="status">
        <strong><?= admin_e(t($packageReport['status'] === 'blocked' ? 'update_preflight.blocked' : 'update_preflight.review')); ?></strong>
        <?= admin_e(t('update_package.warning')); ?>
      </div>
      <p><strong><?= admin_e(t('update_package.version')); ?>:</strong> <?= admin_e($packageReport['current_version']); ?> → <?= admin_e($packageReport['target_version']); ?></p>
      <?php if ($packageReport['sha256'] !== ''): ?>
        <p><strong>SHA-256:</strong> <code><?= admin_e($packageReport['sha256']); ?></code></p>
      <?php endif; ?>
      <div class="admin-health-list">
        <?php foreach ($packageReport['checks'] as $check):
          $state = (string)$check['status'];
          $color = $state === 'good' ? 'good' : ($state === 'blocked' ? 'error' : 'warning');
        ?>
          <div class="admin-health-row">
            <span class="admin-health-indicator admin-health-indicator--<?= admin_e($color); ?>" aria-hidden="true"></span>
            <div><strong><?= admin_e((string)$check['label']); ?></strong><span><?= admin_e((string)$check['detail']); ?></span></div>
            <small><?= admin_e(t('update_preflight.status.' . $state)); ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
