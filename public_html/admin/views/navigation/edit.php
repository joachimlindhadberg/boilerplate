<?php
require __DIR__ . '/partials/context.php';
ob_start();
require __DIR__ . '/partials/header.php';
?>
  <form method="post" action="<?= admin_e($editUrl); ?>" data-nav-editor data-admin-track-changes>
    <input type="hidden" name="csrf_token" value="<?= admin_e($token); ?>"><input type="hidden" name="action" value="save_editor">
    <div class="admin-detail-grid">
      <div class="admin-detail-content">
        <section class="admin-panel admin-nav-editor__main"><div class="admin-panel__header admin-panel__header--split"><div><h2><?= admin_e(t('Menupunkter')); ?></h2><p><?= admin_e(t('nav.editor_drag_help')); ?></p></div><button class="admin-button" type="button" data-nav-add hidden><?= admin_icon('plus', 'admin-button__icon'); ?><?= admin_e(t('Tilføj menupunkt')); ?></button></div>
          <div class="admin-nav-editor__list-wrap"><ul class="admin-nav-editor__list" data-nav-list>
          <?php foreach ($renderedItems as $item) $renderItem($item); ?>
          </ul><p class="sr-only" role="status" aria-live="polite" data-nav-move-status></p><div class="admin-empty-state" data-nav-empty <?= $renderedItems ? 'hidden' : ''; ?>><strong><?= admin_e(t('Ingen menupunkter')); ?></strong><span><?= admin_e(t('Tilføj dit første menupunkt med knappen ovenfor.')); ?></span></div></div>
          <div class="admin-nav-editor__hint"><?= admin_e(t('Punkter med indrykning er undermenuer. Ændringer bliver først offentliggjort, når du klikker Gem ændringer.')); ?></div>
        </section>
      </div>
      <aside class="admin-detail-sidebar">
        <section class="admin-panel admin-nav-editor__settings"><div class="admin-panel__header"><h2><?= admin_e(t('Menuindstillinger')); ?></h2></div><div class="admin-form-section">
          <div class="admin-field"><label for="nav-name"><?= admin_e(t('Menunavn')); ?></label><input id="nav-name" name="name" maxlength="120" value="<?= admin_e($_POST['name'] ?? $selectedMenu['name']); ?>" required></div>
          <div class="admin-field"><label for="nav-placement"><?= admin_e(t('Placering')); ?></label><select id="nav-placement" name="placement"><option value="none" <?= ($_POST['placement'] ?? $placement) === 'none' ? 'selected' : ''; ?>><?= admin_e(t('Ingen placering')); ?></option><option value="header" <?= ($_POST['placement'] ?? $placement) === 'header' ? 'selected' : ''; ?>><?= admin_e(t('Header')); ?></option><option value="footer" <?= ($_POST['placement'] ?? $placement) === 'footer' ? 'selected' : ''; ?>><?= admin_e(t('Footer')); ?></option><option value="both" <?= ($_POST['placement'] ?? $placement) === 'both' ? 'selected' : ''; ?>><?= admin_e(t('Header og Footer')); ?></option></select></div>
        </div></section>
        <section class="admin-panel admin-danger-zone"><div class="admin-panel__header"><h2><?= admin_e(t('Slet')); ?></h2></div><div class="admin-form-section">
          <p><?= admin_e(t($placement !== 'none' ? 'nav.cannot_delete_assigned' : 'Menuen og dens menupunkter slettes permanent. Hjemmesidens sider slettes ikke.')); ?></p>
          <button class="admin-button admin-button--danger-outline" type="button" data-admin-open-delete="nav-delete-dialog" <?= $placement !== 'none' ? 'disabled' : ''; ?> title="<?= admin_e($placement !== 'none' ? t('nav.cannot_delete_assigned') : t('Slet menu')); ?>"><?= admin_e(t('Slet menu')); ?></button>
        </div></section>
        <div class="admin-form-actions"><a class="admin-button admin-button--secondary" href="/admin/navigation"><?= admin_e(t('Annuller')); ?></a><button class="admin-button" type="submit"><?= admin_e(t('Gem ændringer')); ?></button></div>
      </aside>
    </div>
  </form>
  <?php if ($placement === 'none'): ?>
    <?= AdminDeleteDialog::render([
        'id' => 'nav-delete-dialog',
        'titleId' => 'nav-delete-title',
        'title' => t('Slet menu'),
        'warning' => t('Handlingen kan ikke fortrydes.'),
        'action' => '/admin/navigation?mode=delete&id=' . $menuId,
        'csrfToken' => $token,
        'fields' => ['action' => 'delete_menu', 'confirmed' => '1'],
        'prefix' => t('Vil du slette menuen'),
        'subject' => (string)$selectedMenu['name'],
        'suffix' => t('og alle dens menupunkter? Siderne slettes ikke.'),
        'confirmLabel' => t('Slet menu'),
        'cancelLabel' => t('Annuller'),
        'closeLabel' => t('Luk'),
    ]); ?>
  <?php endif; ?>
  <template data-nav-template><?php $renderItem(['id'=>'__KEY__','type'=>'page','page_id'=>0,'label'=>'','url'=>'','parent_id'=>'0','is_visible'=>1,'target'=>'_self']); ?></template>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'Navigation';
require __DIR__ . '/../partials/layout.php';
