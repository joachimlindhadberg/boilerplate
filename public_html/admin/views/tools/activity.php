<?php
$entries = is_array($entries ?? null) ? $entries : [];
$paging = is_array($paging ?? null) ? $paging : ['page'=>1,'pages'=>1,'from'=>0,'to'=>0];
$activityUrl = static function (int $page) use ($search, $type, $action): string {
    return '/admin/vaerktoejer/activity?' . http_build_query(array_filter([
        'q'=>$search, 'type'=>$type, 'action'=>$action, 'page'=>$page,
    ], static fn($v) => $v !== ''));
};
ob_start();
?>
<div class="admin-page-titlebar"><h1><?= admin_e(t('activity.title')); ?></h1><p><?= admin_e(t('activity.read_only')); ?></p></div>
<?php if ($error): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e($error); ?></div><?php endif; ?>
<section class="admin-panel" data-admin-live-list>
  <div class="admin-list-toolbar">
    <form method="get" action="/admin/vaerktoejer/activity" class="admin-list-search admin-list-search--wide-filters" role="search" data-admin-live-search>
      <label class="admin-list-search__wide" for="activity-search"><span class="sr-only"><?= admin_e(t('activity.search')); ?></span><?= admin_icon('search', 'admin-list-search__icon'); ?><input id="activity-search" type="search" name="q" value="<?= admin_e($search); ?>" placeholder="<?= admin_e(t('activity.search')); ?>" autocomplete="off"></label>
      <label class="sr-only" for="activity-type"><?= admin_e(t('activity.type_filter')); ?></label>
      <select id="activity-type" name="type" class="admin-select--compact"><option value=""><?= admin_e(t('activity.all_types')); ?></option>
        <?php foreach ($types as $option): ?><option value="<?= admin_e($option); ?>" <?= $type === $option ? 'selected' : ''; ?>><?= admin_e(t('activity.type.' . $option) === 'activity.type.' . $option ? ucfirst($option) : t('activity.type.' . $option)); ?></option><?php endforeach; ?>
      </select>
      <label class="sr-only" for="activity-action"><?= admin_e(t('activity.action_filter')); ?></label>
      <select id="activity-action" name="action" class="admin-select--compact"><option value=""><?= admin_e(t('activity.all_actions')); ?></option>
        <?php foreach ($actions as $option): ?><option value="<?= admin_e($option); ?>" <?= $action === $option ? 'selected' : ''; ?>><?= admin_e(t('activity.action.' . $option) === 'activity.action.' . $option ? ucfirst(str_replace('_', ' ', $option)) : t('activity.action.' . $option)); ?></option><?php endforeach; ?>
      </select>
    
      <noscript><button class="admin-button admin-button--compact" type="submit"><?= admin_e(t('Filtrer')); ?></button></noscript>
    </form>
  </div>
  <div class="admin-list-summary"><h2><?= admin_e(t('activity.title')); ?></h2><span><?= (int)($total ?? 0) > 0 ? admin_e(t('common.showing', ['from' => (int)$paging['from'], 'to' => (int)$paging['to'], 'total' => (int)$total])) : admin_e(t('activity.empty')); ?></span></div>
  <?php if ($entries): ?><div class="admin-table-wrap"><table class="admin-list-table"><thead><tr>
    <th><?= admin_e(t('activity.activity')); ?></th><th><?= admin_e(t('activity.user')); ?></th><th><?= admin_e(t('activity.date_time')); ?></th>
  </tr></thead><tbody>
  <?php foreach ($entries as $entry): ?><tr>
    <td><strong><?= admin_e(admin_activity_label((string)$entry['action'], (string)$entry['subject_type'])); ?></strong><?php if ($entry['subject_label'] !== ''): ?><span class="admin-activity-subject"><?= admin_e((string)$entry['subject_label']); ?></span><?php endif; ?></td>
    <td><?= admin_e((string)$entry['actor_name']); ?></td>
    <td><time datetime="<?= admin_e(str_replace(' ', 'T', (string)$entry['created_at_utc']) . 'Z'); ?>"><?= admin_e(ActivityLog::localDate((string)$entry['created_at_utc'])); ?></time></td>
  </tr><?php endforeach; ?></tbody></table></div>
  <?php else: ?><div class="admin-empty-state admin-empty-state--large"><strong><?= admin_e(t('activity.empty')); ?></strong></div><?php endif; ?>
  <?php admin_render_pagination((int)$paging['page'], (int)$paging['pages'], $activityUrl, t('activity.pagination')); ?>
</section>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'activity.title';
require __DIR__ . '/../partials/layout.php';
