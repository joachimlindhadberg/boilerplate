<?php
require __DIR__ . '/partials/context.php';
ob_start();
require __DIR__ . '/partials/header.php';
?>
  <section class="admin-panel admin-nav-editor__narrow admin-danger-zone"><div class="admin-panel__header"><h2><?= admin_e(t('Vil du slette menuen')); ?> “<?= admin_e($selectedMenu['name']); ?>”?</h2></div><div class="admin-form-section"><p><?= admin_e(t('Menuen og dens menupunkter slettes permanent. Hjemmesidens sider slettes ikke.')); ?></p><form method="post" action="<?= admin_e('/admin/navigation?mode=delete&id=' . $menuId); ?>" class="admin-form-actions"><input type="hidden" name="csrf_token" value="<?= admin_e($token); ?>"><input type="hidden" name="action" value="delete_menu"><input type="hidden" name="confirmed" value="1"><a class="admin-button admin-button--secondary" href="<?= admin_e($editUrl); ?>"><?= admin_e(t('Annuller')); ?></a><button class="admin-button admin-button--danger" type="submit"><?= admin_e(t('Slet menu')); ?></button></form></div></section>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'Navigation';
require __DIR__ . '/../partials/layout.php';
