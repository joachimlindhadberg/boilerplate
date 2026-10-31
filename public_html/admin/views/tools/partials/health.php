<?php
$healthOverall = in_array($health['overall'] ?? '', ['good', 'warning', 'error'], true)
    ? (string)$health['overall'] : 'warning';
?>
<section class="admin-panel admin-tools-card" aria-labelledby="tools-health-title">
  <div class="admin-tools-card__heading">
    <span class="admin-tools-card__icon" aria-hidden="true"><?= admin_icon('heart-pulse'); ?></span>
    <div><h2 id="tools-health-title"><?= admin_e(t('site_health.title')); ?></h2>
      <p><?= admin_e(t('site_health.summary_description')); ?></p>
    </div>
  </div>
  <div class="admin-tools-card__footer">
    <div class="admin-tools-card__status" role="status">
      <span class="admin-health-indicator admin-health-indicator--<?= admin_e($healthOverall); ?>" aria-hidden="true"></span>
      <span><?= admin_e(t('health_report.status.' . $healthOverall)); ?> · <?= (int)($health['counts']['error'] ?? 0); ?> <?= admin_e(t('health_report.errors')); ?> · <?= (int)($health['counts']['warning'] ?? 0); ?> <?= admin_e(t('health_report.warnings')); ?></span>
    </div>
    <a class="admin-button admin-button--secondary" href="/admin/tools/site-health"><?= admin_e(t('site_health.view_details')); ?></a>
  </div>
</section>
