<dialog class="admin-modal admin-modal--sm" data-admin-modal data-module-deactivate-dialog aria-labelledby="module-deactivate-title">
  <form class="admin-modal__surface" method="post" action="/admin/modules?mode=deactivate">
    <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
    <input type="hidden" name="slug" value="" data-module-deactivate-slug>

    <div class="admin-modal__header">
      <div>
        <h2 id="module-deactivate-title"><?= admin_e(t('Deaktiver module?')); ?></h2>
        <p><?= admin_e(t('Bekræft før modulet slås fra.')); ?></p>
      </div>
      <button type="button" class="admin-modal__close" data-modal-close aria-label="<?= admin_e(t('Luk')); ?>">×</button>
    </div>

    <div class="admin-modal__body">
      <p class="admin-modal__message"><?= admin_e(t('Er du sikker på, at du vil deaktivere')); ?> <strong data-module-deactivate-name><?= admin_e(t('modulet')); ?></strong>?</p>
      <p class="admin-modal__hint"><?= admin_e(t('Funktioner fra modulet bliver utilgængelige, indtil det aktiveres igen. Modulets filer og gemte data slettes ikke.')); ?></p>
    </div>

    <div class="admin-modal__footer">
      <button class="admin-button admin-button--secondary" type="button" data-modal-close><?= admin_e(t('Annuller')); ?></button>
      <button class="admin-button admin-button--danger" type="submit" data-module-deactivate-confirm><?= admin_e(t('Deaktiver module')); ?></button>
    </div>
  </form>
</dialog>