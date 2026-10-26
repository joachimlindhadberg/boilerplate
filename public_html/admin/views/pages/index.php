<?php
$data = is_array($pagesData ?? null) ? $pagesData : [];
$items = is_array($data['items'] ?? null) ? $data['items'] : [];
$counts = is_array($data['counts'] ?? null) ? $data['counts'] : ['all'=>0,'published'=>0,'draft'=>0,'protected'=>0];
$search = (string)($data['search'] ?? '');
$status = (string)($data['status'] ?? '');
$page = max(1, (int)($data['page'] ?? 1));
$pages = max(1, (int)($data['pages'] ?? 1));
$total = max(0, (int)($data['total'] ?? 0));
$from = max(0, (int)($data['from'] ?? 0));
$to = max(0, (int)($data['to'] ?? 0));
$filterTitles = ['' => 'Alle sider', 'published' => 'Publicerede', 'draft' => 'Kladder', 'protected' => 'Beskyttede'];
$listTitle = $search !== '' ? ($status !== '' ? t('pages.search_in_status', ['status' => t($filterTitles[$status] ?? 'Alle sider')]) : t('pages.search_results')) : t($filterTitles[$status] ?? 'Alle sider');
$hasFilters = $search !== '' || $status !== '';
$filters = [
    '' => ['Alle', (int)($counts['all'] ?? 0)],
    'published' => ['Publicerede', (int)($counts['published'] ?? 0)],
    'draft' => ['Kladder', (int)($counts['draft'] ?? 0)],
    'protected' => ['Beskyttede', (int)($counts['protected'] ?? 0)],
];
ob_start();
?>
<div class="admin-page-titlebar admin-page-titlebar--split">
  <div><h1><?= admin_e(t('Sider')); ?></h1><p><?= admin_e(t('Administrér hjemmesideindhold, URL-hierarki og SEO.')); ?></p></div>
  <a class="admin-button" href="/admin/sider?mode=new"><?= admin_icon('plus', 'admin-button__icon'); ?><?= admin_e(t('Ny side')); ?></a>
</div>
<?php if (!empty($notice)): ?><div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div><?php endif; ?>
<section class="admin-panel" aria-labelledby="pages-list-title" data-admin-live-list>
  <div class="admin-list-toolbar">
    <?= AdminListUI::filterTabs(array_map(
      static fn(string $filterStatus, array $filter): array => [
        'value' => $filterStatus,
        'label' => t((string)$filter[0]),
        'count' => (int)$filter[1],
        'url' => admin_pages_url(['status'=>$filterStatus,'page'=>null]),
      ],
      array_keys($filters), array_values($filters)
    ), $status, t('ui.filter_pages_status')); ?>
    <form class="admin-list-search" method="get" action="/admin/sider" role="search" data-admin-live-search>
      <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= admin_e($status); ?>"><?php endif; ?>
      <label><span class="sr-only"><?= admin_e(t('Søg sider')); ?></span><?= admin_icon('search','admin-list-search__icon'); ?><input type="search" name="q" value="<?= admin_e($search); ?>" placeholder="<?= admin_e(t('Søg titel, slug eller SEO…')); ?>" autocomplete="off"></label>
    
      <noscript><button class="admin-button admin-button--compact" type="submit"><?= admin_e(t('Filtrer')); ?></button></noscript>
    </form>
  </div>
  <div class="admin-list-summary"><h2 id="pages-list-title"><?= admin_e($listTitle); ?></h2><span><?= $total > 0 ? admin_e(t('common.showing', ['from' => $from, 'to' => $to, 'total' => $total])) : t('common.zero_pages'); ?></span></div>
  <?php if ($items): ?>
    <div class="admin-table-wrap"><table class="admin-list-table"><thead><tr><th><?= admin_e(t('Side')); ?></th><th><?= admin_e(t('URL')); ?></th><th><?= admin_e(t('Status')); ?></th><th><?= admin_e(t('Editor')); ?></th><th><?= admin_e(t('Overordnet')); ?></th><th><?= admin_e(t('Opdateret')); ?></th><th class="admin-list-table__actions"><?= admin_e(t('Handlinger')); ?></th></tr></thead><tbody>
      <?php foreach ($items as $item): $st=(string)($item['page_status']??''); $isSystem=Pages::isSystemSlug((string)($item['page_slug']??'')); $hasChildren=!empty($item['has_children']); $canDelete=!$isSystem && !$hasChildren; $id=(int)$item['id']; ?>
      <tr>
        <td><a class="admin-table-primary" href="/admin/sider?mode=view&amp;id=<?= (int)$item['id']; ?>"><?= admin_e((string)($item['page_title'] ?? '—')); ?></a><span class="admin-table-secondary"><?= $isSystem ? admin_e(t('pages.system_page')) : (!empty($item['parent_title']) ? admin_e(t('pages.child_of', ['title' => (string)$item['parent_title']])) : ''); ?></span></td>
        <td><code><?= admin_e(admin_page_public_url($item)); ?></code></td>
        <td><span class="admin-status-label admin-status-label--<?= admin_e(admin_page_status_class($st)); ?>"><?= admin_e(admin_page_status_label($st)); ?></span><?php if (!empty($item['publish_at_utc'])): ?><span class="admin-table-secondary"><?= admin_e(t('pages.scheduled_publish', ['time' => PageScheduler::localLabel($item['publish_at_utc'])])); ?></span><?php endif; ?><?php if (!empty($item['unpublish_at_utc'])): ?><span class="admin-table-secondary"><?= admin_e(t('pages.scheduled_unpublish', ['time' => PageScheduler::localLabel($item['unpublish_at_utc'])])); ?></span><?php endif; ?></td>
        <td><span class="admin-editor-label"><?= admin_e((($item['content_mode'] ?? 'legacy') === 'builder') ? t('Page Builder') : t('Legacy HTML')); ?></span></td>
        <td><?= !empty($item['parent_title']) ? admin_e((string)$item['parent_title']) : '—'; ?></td>
        <td><?= !empty($item['updated']) ? admin_e(date('d.m.Y H:i', strtotime((string)$item['updated']))) : '—'; ?></td>
        <td class="admin-list-table__actions"><?= AdminListUI::actionGroup([
          ['icon' => 'eye', 'label' => t('Se side'), 'url' => '/admin/sider?mode=view&id=' . $id],
          ['icon' => 'edit', 'label' => t('Rediger'), 'url' => '/admin/sider?mode=edit&id=' . $id],
          ['type' => 'button', 'icon' => 'delete', 'label' => $canDelete ? t('Slet side') : t($isSystem ? 'pages.cannot_delete_system' : 'pages.cannot_delete_children'), 'tone' => 'danger', 'attributes' => [
            'data-admin-open-delete' => 'page-list-delete-dialog',
            'data-page-delete-id' => (string)$id,
            'data-page-delete-title' => (string)($item['page_title'] ?? ''),
            'disabled' => !$canDelete,
          ]],
        ], t('pages.actions_for', ['title' => (string)($item['page_title'] ?? '')])); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php admin_render_pagination($page, $pages, static fn(int $targetPage): string => admin_pages_url(['page'=>$targetPage]), t('Sider')); ?>
  <?php else: ?>
    <div class="admin-empty-state admin-empty-state--large"><?= admin_icon('file','admin-empty-state__icon'); ?><strong><?= admin_e(t('Ingen sider fundet')); ?></strong><span><?= admin_e(t($hasFilters ? 'pages.empty_filtered' : 'pages.empty_unfiltered')); ?></span><a class="admin-button admin-button--secondary admin-button--compact" href="<?= $hasFilters ? '/admin/sider' : '/admin/sider?mode=new'; ?>"><?= !$hasFilters ? admin_icon('plus', 'admin-button__icon') : ''; ?><?= admin_e(t($hasFilters ? 'pages.clear_filters' : 'Ny side')); ?></a></div>
  <?php endif; ?>
</section>
<?php // One Core modal shared by all page rows, including live-search results. ?>
<?= AdminDeleteDialog::render([
    'id' => 'page-list-delete-dialog',
    'titleId' => 'page-list-delete-title',
    'title' => t('Slet side'),
    'warning' => t('Handlingen kan ikke fortrydes.'),
    'action' => '/admin/sider?mode=delete',
    'csrfToken' => admin_csrf_token(),
    'fields' => [
        'id' => ['value' => '', 'attributes' => ['data-page-list-delete-id' => true]],
        'confirm_delete' => 'permanent',
        'return_to' => 'list',
    ],
    'prefix' => t('Vil du slette siden'),
    'subject' => '',
    'subjectAttributes' => ['data-page-list-delete-title' => true],
    'suffix' => t('permanent? Sidens Page Builder-indhold og versionshistorik bliver også slettet.'),
    'confirmLabel' => t('Slet side'),
    'cancelLabel' => t('Annuller'),
    'closeLabel' => t('Luk'),
]); ?>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = t('Sider');
require __DIR__ . '/../partials/layout.php';
