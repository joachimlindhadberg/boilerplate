<?php
$errors = is_array($errors ?? null) ? $errors : [];
ob_start();
?>
<div class="admin-page-titlebar admin-page-titlebar--split">
  <div><h1><?= admin_e(t('tools.data_title')); ?></h1><p><?= admin_e(t('tools.data_page_description')); ?></p></div>
  <a class="admin-button admin-button--secondary" href="/admin/tools"><?= admin_icon('arrow-left', 'admin-button__icon'); ?><?= admin_e(t('Tilbage til Værktøjer')); ?></a>
</div>
<?php if (!empty($notice)): ?><div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div><?php endif; ?>
<?php if (!empty($errors['form'])): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$errors['form'])); ?></div><?php endif; ?>
<div class="admin-tools-grid">
  <?php require __DIR__ . '/partials/import.php'; ?>
  <?php require __DIR__ . '/partials/export.php'; ?>
</div>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'tools.data_title';
require __DIR__ . '/../partials/layout.php';
