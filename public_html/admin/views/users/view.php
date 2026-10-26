<?php
$managedUser = is_array($managedUser ?? null) ? $managedUser : [];
$id = (int)($managedUser['id'] ?? 0);
$status = (string)($managedUser['status'] ?? '');
$name = trim((string)($managedUser['firstname']??'') . ' ' . (string)($managedUser['lastname']??''));
$isSelf = $id === (int)($actorId ?? 0);
$deleteBlocked = !empty($deleteBlocked);
$formatDate = static function (?string $value, string $fallback='—'): string { if(!$value) return $fallback; $t=strtotime($value); return $t===false?'—':date('d.m.Y H:i',$t); };
ob_start();
?>
<div class="admin-detail-back"><a class="admin-back-link" href="/admin/brugere"><?= admin_icon('arrow-left', 'admin-back-link__icon'); ?><?= admin_e(t('Tilbage til brugere')); ?></a></div>
<div class="admin-page-titlebar admin-page-titlebar--split"><div><h1><?= admin_e($name !== '' ? $name : 'Bruger #' . $id); ?><?= $isSelf?' · Dig':''; ?></h1><p><?= admin_e((string)($managedUser['email']??'')); ?></p></div><a class="admin-button" href="/admin/brugere?mode=edit&amp;id=<?= $id; ?>"><?= admin_e(t('Rediger')); ?></a></div>
<?php if (!empty($notice)): ?><div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div><?php endif; ?>
<div class="admin-detail-grid">
  <div class="admin-detail-content">
    <section class="admin-panel"><div class="admin-panel__header"><h2><?= admin_e(t('Brugeroplysninger')); ?></h2></div><dl class="admin-meta-list admin-meta-list--wide">
      <div><dt><?= admin_e(t('Navn')); ?></dt><dd><?= admin_e($name !== '' ? $name : '—'); ?></dd></div>
      <div><dt><?= admin_e(t('E-mail')); ?></dt><dd><a class="admin-table-link" href="mailto:<?= admin_e((string)($managedUser['email']??'')); ?>"><?= admin_e((string)($managedUser['email']??'—')); ?></a></dd></div>
      <div><dt><?= admin_e(t('Telefon')); ?></dt><dd><?= admin_e(trim((string)($managedUser['phone']??'')) !== '' ? (string)$managedUser['phone'] : '—'); ?></dd></div>
      <div><dt><?= admin_e(t('Rolle')); ?></dt><dd><?= admin_e(admin_user_role_label((string)($managedUser['role_name']??''))); ?></dd></div>
    </dl></section>
    <section class="admin-panel"><div class="admin-panel__header"><h2><?= admin_e(t('Sikkerhed og adgang')); ?></h2></div><dl class="admin-meta-list admin-meta-list--wide">
      <div><dt><?= admin_e(t('Seneste login')); ?></dt><dd><?= admin_e($formatDate($managedUser['last_login']??null, 'Aldrig')); ?></dd></div>
      <div><dt><?= admin_e(t('Password-skift påkrævet')); ?></dt><dd><?= !empty($managedUser['must_change_password']) ? 'Ja' : 'Nej'; ?></dd></div>
      <div><dt><?= admin_e(t('Oprettet')); ?></dt><dd><?= admin_e($formatDate($managedUser['created_at']??null)); ?></dd></div>
      <div><dt><?= admin_e(t('Opdateret')); ?></dt><dd><?= admin_e($formatDate($managedUser['updated_at']??null)); ?></dd></div>
    </dl></section>
  </div>
  <aside class="admin-detail-sidebar">
    <section class="admin-panel"><div class="admin-panel__header"><h2><?= admin_e(t('Status')); ?></h2><span class="admin-status-label admin-status-label--large admin-status-label--<?= admin_e(admin_user_status_class($status)); ?>"><?= admin_e(admin_user_status_label($status)); ?></span></div><dl class="admin-meta-list">
      <div><dt><?= admin_e(t('Rolle')); ?></dt><dd><?= admin_e(admin_user_role_label((string)($managedUser['role_name']??''))); ?></dd></div>
      <div><dt><?= admin_e(t('CMS-adgang')); ?></dt><dd><?= in_array((string)($managedUser['role_name']??''), ['admin','editor'], true) && $status==='active' ? 'Ja' : 'Nej'; ?></dd></div>
    </dl></section>
    <section class="admin-panel admin-danger-zone"><div class="admin-panel__header"><h2><?= admin_e(t('Permanent sletning')); ?></h2></div><div class="admin-danger-zone__body">
      <p><?= admin_e($deleteBlocked ? (string)($deleteReason ?? 'Brugeren kan ikke slettes.') : 'Slet brugerkontoen permanent. Handlingen kan ikke fortrydes.'); ?></p>
      <button class="admin-button admin-button--danger-outline" type="button" data-admin-open-delete="user-delete-dialog" <?= $deleteBlocked ? 'disabled' : ''; ?>><?= admin_e(t('Slet bruger')); ?></button>
    </div></section>
  </aside>
</div>
<?php if (!$deleteBlocked): ?>
  <?= AdminDeleteDialog::render([
      'id' => 'user-delete-dialog',
      'titleId' => 'user-delete-title',
      'title' => t('Slet bruger'),
      'warning' => t('Handlingen kan ikke fortrydes.'),
      'action' => '/admin/brugere?mode=delete&id=' . $id,
      'csrfToken' => admin_csrf_token(),
      'fields' => [
          'id' => $id,
          'confirm_delete' => 'permanent',
      ],
      'prefix' => t('Vil du slette brugeren'),
      'subject' => $name,
      'suffix' => t('permanent?'),
      'confirmLabel' => t('Slet bruger'),
      'cancelLabel' => t('Annuller'),
      'closeLabel' => t('Luk'),
  ]); ?>
<?php endif; ?>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = $name !== '' ? $name : 'Bruger';
require __DIR__ . '/../partials/layout.php';
