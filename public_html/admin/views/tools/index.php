<?php
$health = is_array($health ?? null) ? $health : ['checks'=>[], 'counts'=>['good'=>0,'warning'=>0,'error'=>0], 'overall'=>'warning'];
$errors = is_array($errors ?? null) ? $errors : [];
$statusLabel = static fn(string $status): string => match($status) {
    'good' => 'God', 'warning' => 'Bemærk', 'error' => 'Fejl', default => 'Ukendt',
};
ob_start();
?>
<div class="admin-page-titlebar">
  <h1><?= admin_e(t('Værktøjer')); ?></h1>
  <p><?= admin_e(t('Kontrollér webstedets helbred og flyt CMS-data ind eller ud af Boilerplate.')); ?></p>
</div>
<?php if (!empty($notice)): ?><div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div><?php endif; ?>
<?php if (!empty($errors['form'])): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$errors['form'])); ?></div><?php endif; ?>

<div class="admin-tools-grid">
<?php require __DIR__ . '/partials/health.php'; ?>

<?php require __DIR__ . '/partials/redirects-card.php'; ?>

<?php require __DIR__ . '/partials/activity-card.php'; ?>

<?php require __DIR__ . '/partials/update-card.php'; ?>

<?php require __DIR__ . '/partials/import.php'; ?>

<?php require __DIR__ . '/partials/export.php'; ?>
</div>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'Værktøjer';
require __DIR__ . '/../partials/layout.php';
