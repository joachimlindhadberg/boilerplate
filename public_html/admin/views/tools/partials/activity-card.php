  <section class="admin-panel admin-tools-section" aria-labelledby="tools-activity-title">
    <div class="admin-panel__header admin-tools-section__header">
      <div><h2 id="tools-activity-title"><?= admin_e(t('activity.title')); ?></h2><p><?= admin_e(t('activity.tools_description')); ?></p></div>
    </div>
    <div class="admin-tools-body"><div class="admin-tools-feature">
      <div><strong><?= admin_e(t('activity.recent')); ?></strong><span><?= admin_e(t('activity.read_only')); ?></span></div>
      <a class="admin-button admin-button--secondary" href="/admin/vaerktoejer/activity"><?= admin_e(t('activity.view_all')); ?></a>
    </div></div>
  </section>
