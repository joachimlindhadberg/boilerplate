  <section class="admin-panel admin-tools-section" aria-labelledby="tools-health-title">
    <div class="admin-panel__header admin-tools-section__header admin-health-section__header">
      <div><h2 id="tools-health-title"><?= admin_e(t('Webstedshelbred')); ?></h2><p><?= admin_e(t('Teknisk status for den aktuelle installation.')); ?></p></div>
      <span class="admin-health-summary admin-health-summary--<?= admin_e((string)$health['overall']); ?>"><?= (int)($health['counts']['error'] ?? 0); ?> <?= admin_e(t('health_report.errors')); ?> · <?= (int)($health['counts']['warning'] ?? 0); ?> <?= admin_e(t('health_report.warnings')); ?></span>
    </div>
    <div class="admin-health-list">
      <?php foreach ((array)($health['checks'] ?? []) as $check): $status=(string)($check['status'] ?? 'warning'); ?>
        <div class="admin-health-row">
          <span class="admin-health-indicator admin-health-indicator--<?= admin_e($status); ?>" aria-hidden="true"></span>
          <div><strong><?= admin_e((string)($check['label'] ?? 'Kontrol')); ?></strong><span><?= admin_e((string)($check['detail'] ?? '')); ?></span></div>
          <small><?= admin_e($statusLabel($status)); ?></small>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
