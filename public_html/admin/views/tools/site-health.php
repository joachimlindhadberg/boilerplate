<?php
$health = is_array($health ?? null) ? $health : ['checks' => [], 'counts' => [], 'overall' => 'warning'];
$errors = is_array($errors ?? null) ? $errors : [];
$overall = in_array($health['overall'] ?? '', ['good', 'warning', 'error'], true)
    ? (string)$health['overall'] : 'warning';
ob_start();
?>
<div class="admin-page-titlebar admin-page-titlebar--split">
  <div><h1><?= admin_e(t('site_health.title')); ?></h1><p><?= admin_e(t('site_health.description')); ?></p></div>
  <a class="admin-button admin-button--secondary" href="/admin/tools"><?= admin_icon('arrow-left', 'admin-button__icon'); ?><?= admin_e(t('Tilbage til Værktøjer')); ?></a>
</div>
<?php if (!empty($errors['form'])): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$errors['form'])); ?></div><?php endif; ?>
<?php if (!empty($errors['health_report'])): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$errors['health_report'])); ?></div><?php endif; ?>
<div class="admin-tools-grid">
<section class="admin-panel admin-tools-section" aria-labelledby="site-health-list-title">
  <div class="admin-panel__header admin-tools-section__header admin-health-section__header">
    <div><h2 id="site-health-list-title"><?= admin_e(t('site_health.checks_title')); ?></h2><p><?= admin_e(t('site_health.checks_description')); ?></p></div>
    <span class="admin-health-summary admin-health-summary--<?= admin_e($overall); ?>"><?= admin_e(t('health_report.status.' . $overall)); ?> · <?= (int)($health['counts']['error'] ?? 0); ?> <?= admin_e(t('health_report.errors')); ?> · <?= (int)($health['counts']['warning'] ?? 0); ?> <?= admin_e(t('health_report.warnings')); ?></span>
  </div>
  <div class="admin-health-list">
    <?php foreach ((array)($health['checks'] ?? []) as $check):
      if (!is_array($check)) continue;
      $status = in_array($check['status'] ?? '', ['good', 'warning', 'error'], true) ? (string)$check['status'] : 'warning';
    ?>
      <div class="admin-health-row">
        <span class="admin-health-indicator admin-health-indicator--<?= admin_e($status); ?>" aria-hidden="true"></span>
        <div><strong><?= admin_e((string)($check['label'] ?? '')); ?></strong><span><?= admin_e((string)($check['detail'] ?? '')); ?></span></div>
        <small><?= admin_e(t('health_report.status.' . $status)); ?></small>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<section class="admin-panel admin-tools-section" aria-labelledby="site-health-download-title">
  <div class="admin-panel__header admin-tools-section__header">
    <div><h2 id="site-health-download-title"><?= admin_e(t('health_report.export_title')); ?></h2><p><?= admin_e(t('health_report.export_description')); ?></p></div>
  </div>
  <div class="admin-tools-body">
    <div class="admin-tools-feature">
      <div><span><?= admin_e(t('health_report.description')); ?></span></div>
      <form method="post" action="/admin/tools/site-health">
        <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
        <input type="hidden" name="action" value="health_report">
        <button class="admin-button admin-button--secondary" type="submit"><?= admin_e(t('health_report.download')); ?></button>
      </form>
    </div>
  </div>
</section>
</div>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'site_health.title';
require __DIR__ . '/../partials/layout.php';
