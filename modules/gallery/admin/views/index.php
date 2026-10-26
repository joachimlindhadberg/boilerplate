<?php
$galleries = is_array($galleries ?? null) ? $galleries : [];
$search = (string)($search ?? '');
$total = max(0, (int)($total ?? 0));
$paging = is_array($paging ?? null) ? $paging : admin_crud_pagination($total);
?>
<div class="admin-page-titlebar admin-page-titlebar--split">
  <div>
    <h1><?= admin_e(t('gallery:Gallerier')); ?></h1>
    <p><?= admin_e(t('gallery:Opret genbrugelige billedgallerier baseret på billeder fra Medier.')); ?></p>
  </div>
  <a class="admin-button" href="/admin/gallerier?screen=new"><?= admin_icon('plus', 'admin-button__icon'); ?><?= admin_e(t('gallery:Nyt galleri')); ?></a>
</div>

<?php if (!empty($notice)): ?>
  <div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div>
<?php endif; ?>

<section class="admin-panel" aria-labelledby="gallery-list-title" data-admin-live-list>
  <div class="admin-list-toolbar">
    <form class="admin-list-search" action="/admin/gallerier" method="get" role="search" data-admin-live-search>
      <label><span class="sr-only"><?= admin_e(t('gallery:Gallerier')); ?></span><?= admin_icon('search', 'admin-list-search__icon'); ?><input name="q" type="search" value="<?= admin_e($search); ?>" placeholder="<?= admin_e(t('gallery:Søg gallerier…')); ?>" autocomplete="off"></label>
      <noscript><button class="admin-button admin-button--compact" type="submit"><?= admin_e(t('Filtrer')); ?></button></noscript>
    </form>
  </div>
  <div class="admin-list-summary">
    <h2 id="gallery-list-title"><?= admin_e(t('gallery:Gallerier')); ?></h2>
    <span><?= $total > 0 ? admin_e(t('common.showing', ['from'=>$paging['from'], 'to'=>$paging['to'], 'total'=>$total])) : '0'; ?></span>
  </div>

  <?php if ($galleries): ?>
    <div class="admin-table-wrap">
      <table class="admin-list-table">
        <thead><tr><th><?= admin_e(t('gallery:Galleri')); ?></th><th><?= admin_e(t('Status')); ?></th><th><?= admin_e(t('gallery:Billeder')); ?></th><th><?= admin_e(t('Opdateret')); ?></th><th class="admin-list-table__actions"><?= admin_e(t('Handlinger')); ?></th></tr></thead>
        <tbody>
        <?php foreach ($galleries as $gallery): $id=(int)$gallery['id']; $active=($gallery['status']??'active')==='active'; ?>
          <tr>
            <td><a class="admin-table-primary" href="/admin/gallerier?screen=edit&amp;id=<?= $id; ?>"><?= admin_e((string)$gallery['name']); ?></a><span class="admin-table-secondary"><?= admin_e(t('gallery:gallery_page_builder_component')); ?> · <?= $id; ?></span></td>
            <td><span class="admin-status-label <?= $active ? 'admin-status-label--module-active' : ''; ?>"><?= $active ? t('Aktiv') : t('Inaktiv'); ?></span></td>
            <td><?= (int)($gallery['image_count'] ?? 0); ?></td>
            <td><?= !empty($gallery['updated_at']) ? admin_e(date('d.m.Y H:i', strtotime((string)$gallery['updated_at']))) : '—'; ?></td>
            <td class="admin-list-table__actions"><?= AdminListUI::actionGroup([
              ['icon' => 'edit', 'label' => t('gallery:Rediger galleri') . ': ' . (string)$gallery['name'], 'url' => '/admin/gallerier?screen=edit&id=' . $id],
              ['type' => 'button', 'icon' => 'delete', 'label' => t('gallery:Slet galleri') . ': ' . (string)$gallery['name'], 'tone' => 'danger', 'attributes' => [
                'data-admin-open-delete' => 'gallery-list-delete-dialog',
                'data-gallery-list-delete-id' => (string)$id,
                'data-gallery-list-delete-name' => (string)$gallery['name'],
              ]],
            ], t('Handlinger') . ': ' . (string)$gallery['name']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="admin-empty-state admin-empty-state--large">
      <?= admin_icon('image', 'admin-empty-state__icon'); ?>
      <strong><?= admin_e(t('gallery:Ingen gallerier endnu')); ?></strong>
      <span><?= admin_e(t('gallery:Opret et galleri, vælg billeder fra Medier og indsæt det derefter via Page Builder.')); ?></span>
      <?php if ($search === ''): ?><a class="admin-button" href="/admin/gallerier?screen=new"><?= admin_icon('plus', 'admin-button__icon'); ?><?= admin_e(t('gallery:Opret galleri')); ?></a><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php admin_render_pagination((int)$paging['page'], (int)$paging['pages'], static function (int $page) use ($search): string { return '/admin/gallerier?' . http_build_query(array_filter(['q'=>$search, 'page'=>$page], static fn($value) => $value !== '')); }, t('gallery:Gallerier')); ?>
</section>

<?php // One Core confirmation modal, outside the live-search region. ?>
<?= AdminDeleteDialog::render([
    'id' => 'gallery-list-delete-dialog',
    'titleId' => 'gallery-list-delete-title',
    'title' => t('gallery:Slet galleri?'),
    'warning' => t('gallery:Denne handling kan ikke fortrydes.'),
    'action' => '/admin/gallerier?screen=edit',
    'csrfToken' => admin_csrf_token(),
    'fields' => [
        'id' => ['value' => '', 'attributes' => ['data-gallery-list-delete-target' => true]],
        'gallery_action' => 'delete',
    ],
    'prefix' => t('gallery:Galleri'),
    'subject' => '',
    'subjectAttributes' => ['data-gallery-list-delete-subject' => true],
    'suffix' => t('gallery:Galleriet kan kun slettes, hvis det ikke længere bruges på en side. Billederne i Medier bliver ikke slettet.'),
    'confirmLabel' => t('gallery:Slet galleri'),
    'cancelLabel' => t('Annuller'),
    'closeLabel' => t('Luk'),
]); ?>
