<dialog class="admin-modal admin-modal--sm" data-admin-modal data-module-update-dialog aria-labelledby="module-update-title">
  <form class="admin-modal__surface" method="post" action="/admin/modules?mode=update">
    <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
    <input type="hidden" name="slug" value="" data-module-update-slug>
    <div class="admin-modal__header">
      <div>
        <h2 id="module-update-title"><?= admin_e(t('plugin.update_title')); ?></h2>
        <p><?= admin_e(t('plugin.update_explanation')); ?></p>
      </div>
      <button type="button" class="admin-modal__close" data-modal-close aria-label="<?= admin_e(t('Luk')); ?>">×</button>
    </div>
    <div class="admin-modal__body">
      <p class="admin-modal__message"><strong data-module-update-name></strong></p>
      <p class="admin-modal__hint"><?= admin_e(t('plugin.update_migrations_first')); ?></p>
    </div>
    <div class="admin-modal__footer">
      <button class="admin-button admin-button--secondary" type="button" data-modal-close><?= admin_e(t('Annuller')); ?></button>
      <button class="admin-button" type="submit" data-module-update-confirm><?= admin_e(t('plugin.complete_update')); ?></button>
    </div>
  </form>
</dialog>