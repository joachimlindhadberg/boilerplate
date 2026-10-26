  <section class="admin-panel admin-tools-section" aria-labelledby="tools-export-title">
    <div class="admin-panel__header admin-tools-section__header"><div><h2 id="tools-export-title"><?= admin_e(t('Eksporter')); ?></h2><p><?= admin_e(t('backup.export_description')); ?></p></div></div>
    <div class="admin-tools-body admin-export-options">
      <?php if (!empty($errors['export'])): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$errors['export'])); ?></div><?php endif; ?>
      <?php if (!empty($errors['backup'])): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$errors['backup'])); ?></div><?php endif; ?>
      <div class="admin-export-option admin-export-option--site-backup">
        <div class="admin-export-option__main">
          <div class="admin-export-option__description">
            <strong><?= admin_e(t('backup.site_data_title')); ?></strong>
            <span><?= admin_e(t('backup.site_data_desc')); ?></span>
          </div>
          <form method="post" action="/admin/vaerktoejer">
            <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
            <input type="hidden" name="action" value="site_backup">
            <button class="admin-button" type="submit"><?= admin_e(t('backup.download')); ?></button>
          </form>
        </div>
        <div class="admin-tools-warning admin-tools-warning--inline">
          <strong><?= admin_e(t('backup.sensitive_heading')); ?></strong>
          <span><?= admin_e(t('backup.sensitive_text')); ?></span>
        </div>
      </div>
      <div class="admin-export-option">
        <div>
          <strong><?= admin_e(t('backup.partial_title')); ?></strong>
          <span><?= admin_e(t('backup.partial_desc')); ?></span>
        </div>
        <form method="post" action="/admin/vaerktoejer">
          <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
          <input type="hidden" name="action" value="export">
          <input type="hidden" name="profile" value="content">
          <button class="admin-button admin-button--secondary" type="submit"><?= admin_e(t('backup.partial_download')); ?></button>
        </form>
      </div>
      <div class="admin-export-option">
        <div>
          <strong><?= admin_e(t('health_report.export_title')); ?></strong>
          <span><?= admin_e(t('health_report.export_description')); ?></span>
          <?php if (!empty($errors['health_report'])): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$errors['health_report'])); ?></div><?php endif; ?>
        </div>
        <form method="post" action="/admin/vaerktoejer">
          <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
          <input type="hidden" name="action" value="health_report">
          <button class="admin-button admin-button--secondary" type="submit"><?= admin_e(t('health_report.download')); ?></button>
        </form>
      </div>
      <p class="admin-field__help"><?= admin_e(t('backup.restore_note')); ?></p>
    </div>
  </section>
