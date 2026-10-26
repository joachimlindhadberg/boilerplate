<?php
/**
 * Simple, source-agnostic updates UI. Actual network lookup is a separate,
 * future release; do not claim the installed release is up to date until then.
 * Keep the private compatibility and archive-review services available to Core.
 */
$report = is_array($report ?? null) ? $report : [];
$installedVersion = trim((string)($report['version'] ?? ''));
if (!preg_match('/^\d+\.\d+\.\d+(?:\.\d+)?$/D', $installedVersion)) {
    $installedVersion = '—';
}
ob_start();
?>
<div class="admin-page-titlebar admin-page-titlebar--split">
  <div>
    <h1><?= admin_e(t('updates.title')); ?></h1>
    <p><?= admin_e(t('updates.description')); ?></p>
  </div>
  <a class="admin-button admin-button--secondary" href="/admin/vaerktoejer"><?= admin_icon('arrow-left', 'admin-button__icon'); ?><?= admin_e(t('Tilbage til Værktøjer')); ?></a>
</div>
<section class="admin-panel admin-tools-section" aria-labelledby="updates-current-title">
  <div class="admin-panel__header admin-tools-section__header">
    <div>
      <h2 id="updates-current-title"><?= admin_e(t('updates.installed')); ?></h2>
      <p><?= admin_e(t('updates.current_version')); ?>: <?= admin_e($installedVersion); ?></p>
    </div>
  </div>
  <div class="admin-tools-body">
    <div class="admin-tools-feature">
      <div>
        <strong><?= admin_e(t('updates.not_checked')); ?></strong>
        <span><?= admin_e(t('updates.source_pending')); ?></span>
      </div>
      <button type="button" class="admin-button admin-button--primary" disabled><?= admin_e(t('updates.check')); ?></button>
    </div>
    <div class="admin-notice admin-notice--warning" role="status">
      <?= admin_e(t('updates.coming_soon')); ?>
    </div>
  </div>
</section>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'updates.title';
require __DIR__ . '/../partials/layout.php';
