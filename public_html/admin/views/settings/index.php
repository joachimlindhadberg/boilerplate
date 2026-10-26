<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$fieldError = static fn(string $key): string => t((string)($errors[$key] ?? ''));
$resolvedSiteUrl = is_array($resolvedSiteUrl ?? null) ? $resolvedSiteUrl : [];
ob_start();
?>
<div class="admin-page-titlebar">
  <h1><?= admin_e(t('Indstillinger')); ?></h1>
  <p><?= admin_e(t('Globale website-, SEO-, notifikations- og sessionsindstillinger.')); ?></p>
</div>
<?php if (!empty($notice)): ?><div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div><?php endif; ?>
<?php if ($fieldError('form')): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$fieldError('form'))); ?></div><?php endif; ?>
<form method="post" action="/admin/indstillinger" class="admin-form" data-admin-track-changes>
  <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
  <div class="admin-detail-grid">
    <div class="admin-detail-content">
<?php require __DIR__ . '/partials/language.php'; ?>
<?php require __DIR__ . '/partials/website.php'; ?>

<?php require __DIR__ . '/partials/seo.php'; ?>

<?php require __DIR__ . '/partials/notifications.php'; ?>
    </div>

    <aside class="admin-detail-sidebar">
<?php require __DIR__ . '/partials/maintenance.php'; ?>

<?php require __DIR__ . '/partials/session.php'; ?>

      <div class="admin-form-actions"><button class="admin-button" type="submit"><?= admin_e(t('Gem indstillinger')); ?></button></div>
    </aside>
  </div>
</form>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'Indstillinger';
require __DIR__ . '/../partials/layout.php';
