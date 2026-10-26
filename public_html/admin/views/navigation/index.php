<?php
require __DIR__ . '/partials/context.php';
ob_start();
require __DIR__ . '/partials/header.php';
?>
<section class="admin-panel">
  <div class="admin-panel__header"><h2><?= admin_e(t('Menuer')); ?></h2></div>
  <?php if ($menus): ?>
    <div class="admin-table-wrap">
      <table class="admin-list-table admin-nav-editor__overview-table">
        <thead><tr>
          <th><?= admin_e(t('Menunavn')); ?></th>
          <th><?= admin_e(t('Menupunkter')); ?></th>
          <th><?= admin_e(t('Placering')); ?></th>
          <th class="admin-list-table__actions"><?= admin_e(t('Handlinger')); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($menus as $menu):
          $menuId = (int)$menu['id'];
          $menuName = (string)$menu['name'];
          $menuSlug = (string)$menu['slug'];
          $inHeader = $menuSlug === ($headerSlug ?? 'primary');
          $inFooter = $menuSlug === ($footerSlug ?? 'footer');
          $places = [];
          if ($inHeader) $places[] = 'Header';
          if ($inFooter) $places[] = 'Footer';
          $editUrl = '/admin/navigation?mode=edit&id=' . $menuId;
          $assigned = $inHeader || $inFooter;
          $actions = [
            ['icon' => 'edit', 'label' => t('Rediger') . ': ' . $menuName, 'url' => $editUrl],
          ];
          if ($assigned) {
            // Core Navigation::deleteMenu() rejects an assigned menu as well.
            $actions[] = [
              'type' => 'button', 'icon' => 'delete',
              'label' => t('nav.cannot_delete_assigned') . ': ' . $menuName,
              'tone' => 'danger', 'attributes' => ['disabled' => true],
            ];
          } else {
            // Core opens one shared modal; deletion remains POST + CSRF.
            $actions[] = [
              'type' => 'button', 'icon' => 'delete',
              'label' => t('Slet menu') . ': ' . $menuName, 'tone' => 'danger',
              'attributes' => [
                'data-admin-open-delete' => 'nav-list-delete-dialog',
                'data-nav-delete-id' => (string)$menuId,
                'data-nav-delete-name' => $menuName,
              ],
            ];
          }
        ?>
          <tr>
            <td><a class="admin-table-primary" href="<?= admin_e($editUrl); ?>"><?= admin_e($menuName); ?></a></td>
            <td><?= (int)$menu['item_count']; ?></td>
            <td><?= admin_e($places ? implode(' og ', $places) : t('nav.not_placed')); ?></td>
            <td class="admin-list-table__actions"><?= AdminListUI::actionGroup($actions, t('Handlinger') . ': ' . $menuName); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="admin-empty-state admin-empty-state--large">
      <strong><?= admin_e(t('Ingen menuer endnu')); ?></strong>
      <span><?= admin_e(t('Opret din første menu for at komme i gang.')); ?></span>
    </div>
  <?php endif; ?>
</section>
<?php // Core owns modal markup; Navigation owns its POST endpoint and server checks. ?>
<?= AdminDeleteDialog::render([
    'id' => 'nav-list-delete-dialog',
    'titleId' => 'nav-list-delete-title',
    'title' => t('Slet menu'),
    'warning' => t('Handlingen kan ikke fortrydes.'),
    'action' => '/admin/navigation?mode=delete&id=0',
    'csrfToken' => $token,
    'formAttributes' => ['data-nav-list-delete-form' => true],
    'fields' => ['action' => 'delete_menu', 'confirmed' => '1'],
    'prefix' => t('Vil du slette menuen'),
    'subject' => '',
    'subjectAttributes' => ['data-nav-list-delete-name' => true],
    'suffix' => t('og alle dens menupunkter? Siderne slettes ikke.'),
    'confirmLabel' => t('Slet menu'),
    'cancelLabel' => t('Annuller'),
    'closeLabel' => t('Luk'),
]); ?>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'Navigation';
require __DIR__ . '/../partials/layout.php';
