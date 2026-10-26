<?php
$items = is_array($items ?? null) ? $items : [];
$paging = is_array($paging ?? null) ? $paging : [];
$scope = (string)($scope ?? 'upcoming');
$type = (string)($type ?? '');
$action = (string)($action ?? '');
$scopeCounts = is_array($scopeCounts ?? null) ? $scopeCounts : ['upcoming' => 0, 'overdue' => 0];
$makeUrl = static function (array $change = []) use ($scope,$type,$action): string {
    $params = array_merge(['scope'=>$scope,'type'=>$type,'action'=>$action],$change);
    $params = array_filter($params, static fn($v): bool => $v !== '' && $v !== null);
    return '/admin/scheduled-content' . ($params ? '?' . http_build_query($params) : '');
};
ob_start();
?>
<div class="admin-page-titlebar"><h1><?= admin_e(t('dashboard.scheduled_content')); ?></h1><p><?= admin_e(t('schedule.overview_description')); ?></p></div>
<section class="admin-panel">
  <div class="admin-list-toolbar">
    <?= AdminListUI::filterTabs([
      ['value' => 'upcoming', 'label' => t('schedule.upcoming'), 'count' => (int)($scopeCounts['upcoming'] ?? 0), 'url' => $makeUrl(['scope' => 'upcoming', 'page' => null])],
      ['value' => 'overdue', 'label' => t('schedule.overdue'), 'count' => (int)($scopeCounts['overdue'] ?? 0), 'url' => $makeUrl(['scope' => 'overdue', 'page' => null])],
    ], $scope, t('schedule.scope')); ?>
    <form method="get" action="/admin/scheduled-content" class="admin-list-search admin-list-search--filters">
      <input type="hidden" name="scope" value="<?= admin_e($scope); ?>">
      <select name="type" aria-label="<?= admin_e(t('schedule.type_filter')); ?>"><option value=""><?= admin_e(t('schedule.all_types')); ?></option><?php foreach ($typeOptions as $typeOption): ?><option value="<?= admin_e($typeOption); ?>" <?= $type===$typeOption?'selected':''; ?>><?= admin_e(ScheduledContent::typeLabel($typeOption)); ?></option><?php endforeach; ?></select>
      <select name="action" aria-label="<?= admin_e(t('schedule.action_filter')); ?>"><option value=""><?= admin_e(t('schedule.all_actions')); ?></option><option value="publish" <?= $action==='publish'?'selected':''; ?>><?= admin_e(t('dashboard.publish')); ?></option><option value="unpublish" <?= $action==='unpublish'?'selected':''; ?>><?= admin_e(t('dashboard.unpublish')); ?></option></select>
      <button type="submit" class="admin-button admin-button--compact admin-button--secondary"><?= admin_e(t('schedule.filter')); ?></button>
    </form>
  </div>
  <?php if (!empty($loadError)): ?>
    <div class="admin-empty-state admin-empty-state--large" role="alert"><strong><?= admin_e(t('dashboard.schedules_unavailable')); ?></strong></div>
  <?php else: ?>
    <div class="admin-list-summary"><h2><?= admin_e(t('schedule.actions')); ?></h2><span><?= (int)($total ?? 0); ?></span></div>
    <?php if (!empty($truncated)): ?><div class="admin-notice admin-notice--error" role="status"><?= admin_e(t('schedule.limit_notice')); ?></div><?php endif; ?>
    <?php if ($items): ?>
      <div class="admin-table-wrap"><table class="admin-list-table">
        <thead><tr><th><?= admin_e(t('schedule.content')); ?></th><th><?= admin_e(t('schedule.type_filter')); ?></th><th><?= admin_e(t('dashboard.action')); ?></th><th><?= admin_e(t('dashboard.date_time')); ?></th><th class="admin-list-table__actions"><?= admin_e(t('Handlinger')); ?></th></tr></thead>
        <tbody><?php foreach ($items as $item): ?><tr>
          <td><strong><?= admin_e($item['title']); ?></strong></td>
          <td><?= admin_e(ScheduledContent::typeLabel($item['type'])); ?></td>
          <td><?= admin_e(t($item['action_type']==='publish'?'dashboard.publish':'dashboard.unpublish')); ?></td>
          <td><time datetime="<?= admin_e(str_replace(' ', 'T', $item['due_at_utc']).'Z'); ?>"><?= admin_e(PageScheduler::localLabel($item['due_at_utc'])); ?></time></td>
          <td class="admin-list-table__actions"><?= AdminListUI::actionGroup([
            ['icon' => 'edit', 'label' => t('Rediger'), 'url' => (string)$item['edit_url']],
          ], t('schedule.actions_for', ['title' => (string)$item['title']])); ?></td>
        </tr><?php endforeach; ?></tbody>
      </table></div>
    <?php else: ?><div class="admin-empty-state admin-empty-state--large"><strong><?= admin_e(t('dashboard.no_scheduled_content')); ?></strong><span><?= admin_e(t('schedule.empty_hint')); ?></span></div><?php endif; ?>
    <?php admin_render_pagination((int)($paging['page']??1), (int)($paging['pages']??1), static fn(int $p): string => $makeUrl(['page'=>$p]), t('schedule.pagination')); ?>
  <?php endif; ?>
</section>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = t('dashboard.scheduled_content');
require __DIR__ . '/partials/layout.php';
