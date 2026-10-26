<?php
require __DIR__ . '/partials/context.php';
ob_start();
require __DIR__ . '/partials/header.php';
?>
  <section class="admin-panel admin-nav-editor__narrow"><div class="admin-panel__header"><h2><?= admin_e(t('Ny menu')); ?></h2></div><form class="admin-form-section" method="post" action="/admin/navigation?mode=new" data-admin-track-changes><input type="hidden" name="csrf_token" value="<?= admin_e($token); ?>"><input type="hidden" name="action" value="create_menu"><div class="admin-field"><label for="new-menu-name"><?= admin_e(t('Menunavn')); ?></label><input id="new-menu-name" name="name" value="<?= admin_e($_POST['name'] ?? ''); ?>" maxlength="120" placeholder="<?= admin_e(t('ui.example_service_menu')); ?>" required><p class="admin-field__help"><?= admin_e(t('Du kan tilføje menupunkter efter oprettelsen.')); ?></p></div><div class="admin-form-actions"><a class="admin-button admin-button--secondary" href="/admin/navigation"><?= admin_e(t('Annuller')); ?></a><button class="admin-button" type="submit"><?= admin_icon('plus', 'admin-button__icon'); ?><?= admin_e(t('Opret menu')); ?></button></div></form></section>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'Navigation';
require __DIR__ . '/../partials/layout.php';
