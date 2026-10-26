<?php
$modules = is_array($modules ?? null) ? $modules : [];
ob_start();
?>
<div class="admin-page-titlebar">
  <h1><?= admin_e(t('Modules')); ?></h1>
  <p><?= admin_e(t('modules.subtitle')); ?></p>
</div>

<?php if (!empty($notice)): ?>
  <div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div>
<?php endif; ?>

<?php require __DIR__ . '/partials/module-list.php'; ?>


<?php require __DIR__ . '/partials/deactivate-dialog.php'; ?>


<?php require __DIR__ . '/partials/update-dialog.php'; ?>


<div class="admin-modules-note">
  <strong><?= admin_e(t('plugin.lifecycle_heading')); ?></strong>
  <p><?= admin_e(t('plugin.lifecycle_summary')); ?></p>
</div>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'Modules';
require __DIR__ . '/../partials/layout.php';
