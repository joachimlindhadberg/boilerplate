<div class="admin-page-titlebar">
  <h1><?= admin_e((string)($moduleName ?? '')); ?></h1>
  <p><?= admin_e(t('__MODULE_SLUG__:starter.intro')); ?></p>
</div>
<section class="admin-panel">
  <div class="admin-panel__header">
    <h2><?= admin_e(t('__MODULE_SLUG__:starter.heading')); ?></h2>
  </div>
  <div class="admin-panel__body">
    <p><?= admin_e(t('__MODULE_SLUG__:starter.description')); ?></p>
  </div>
</section>
