  <section class="admin-panel admin-tools-section" aria-labelledby="tools-update-title">
    <div class="admin-panel__header admin-tools-section__header">
      <div><h2 id="tools-update-title"><?= admin_e(t('updates.title')); ?></h2><p><?= admin_e(t('updates.description')); ?></p></div>
    </div>
    <div class="admin-tools-body"><div class="admin-tools-feature">
      <div><strong><?= admin_e(t('updates.not_checked')); ?></strong><span><?= admin_e(t('updates.source_pending')); ?></span></div>
      <a class="admin-button admin-button--secondary" href="/admin/vaerktoejer/update"><?= admin_e(t('updates.open')); ?></a>
    </div></div>
  </section>
