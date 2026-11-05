<?php
$pageRecord = is_array($pageRecord ?? null) ? $pageRecord : [];
$revisions = is_array($revisions ?? null) ? $revisions : [];
$id = (int)($pageRecord['id'] ?? 0);
$pageNo = max(1, (int)($pageNo ?? 1));
$totalPages = max(1, (int)($totalPages ?? 1));
$revisionCount = (int)($revisionCount ?? 0);
$actions = [
    'baseline' => 'revisions.action_baseline',
    'create' => 'Oprettet',
    'save' => 'revisions.action_saved',
    'restore' => 'revisions.action_restored',
    'scheduled_publish' => 'revisions.action_auto_published',
    'scheduled_unpublish' => 'revisions.action_auto_unpublished',
];
ob_start();
?>
<div class="admin-detail-back"><a class="admin-back-link" href="/admin/sider?mode=view&amp;id=<?= $id; ?>"><?= admin_icon('arrow-left', 'admin-back-link__icon'); ?><?= admin_e(t('Tilbage til siden')); ?></a></div>
<div class="admin-page-titlebar admin-page-titlebar--split">
  <div><h1><?= admin_e(t('Versionshistorik')); ?></h1><p><?= admin_e((string)($pageRecord['page_title'] ?? t('Side'))); ?> · <?= admin_e(t('revisions.history_description')); ?></p></div>
  <?php if (LegacyHtmlPolicy::canEditPage($pageRecord, (string)($user['role'] ?? ''))): ?><a class="admin-button admin-button--secondary" href="/admin/sider?mode=edit&amp;id=<?= $id; ?>"><?= admin_e(t('Rediger side')); ?></a><?php endif; ?>
</div>
<?php if (!empty($notice)): ?><div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div><?php endif; ?>
<section class="admin-panel" aria-labelledby="revision-list-title">
  <div class="admin-list-summary"><h2 id="revision-list-title"><?= admin_e(t('Gemte versioner')); ?></h2><span><?= admin_e(t($revisionCount === 1 ? 'revisions.count_one' : 'revisions.count_many', ['count' => $revisionCount])); ?></span></div>
  <?php if ($revisions): ?>
  <div class="admin-table-wrap"><table class="admin-list-table"><thead><tr><th><?= admin_e(t('Version')); ?></th><th><?= admin_e(t('Dato')); ?></th><th><?= admin_e(t('Handling')); ?></th><th><?= admin_e(t('Redaktør')); ?></th><th class="admin-list-table__actions"><?= admin_e(t('Handlinger')); ?></th></tr></thead><tbody>
  <?php foreach ($revisions as $item):
    $author = trim((string)($item['firstname'] ?? '') . ' ' . (string)($item['lastname'] ?? ''));
    $time = strtotime((string)($item['created_at'] ?? ''));
    $actionKey = $actions[(string)($item['action'] ?? '')] ?? 'revisions.action_saved';
  ?>
  <tr><td><strong><?= admin_e(t('revisions.version_label', ['number' => (int)($item['version_number'] ?? 0)])); ?></strong></td>
    <td><?= $time ? admin_e(date('d.m.Y H:i', $time)) : '—'; ?></td>
    <td><?= admin_e(t($actionKey)); ?></td>
    <td><?= $author !== '' ? admin_e($author) : admin_e(t('revisions.unknown_editor')); ?></td>
    <td class="admin-list-table__actions"><a class="admin-text-action" href="/admin/sider?mode=revision&amp;id=<?= $id; ?>&amp;revision_id=<?= (int)($item['id'] ?? 0); ?>"><?= admin_e(t('Se og sammenlign')); ?></a></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <?php admin_render_pagination($pageNo, $totalPages, static fn(int $target): string => '/admin/sider?mode=history&id=' . $id . '&page=' . $target, t('revisions.pagination_label')); ?>
  <?php else: ?>
    <div class="admin-empty-state admin-empty-state--large"><strong><?= admin_e(t('Ingen versioner endnu')); ?></strong><span><?= admin_e(t('Den første startversion gemmes, næste gang siden redigeres. Eksisterende sider ændres ikke ved installation.')); ?></span></div>
  <?php endif; ?>
</section>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = t('Versionshistorik');
require __DIR__ . '/../partials/layout.php';
