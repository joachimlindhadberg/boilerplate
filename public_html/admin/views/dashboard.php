<?php
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$counts = is_array($dashboard['counts'] ?? null) ? $dashboard['counts'] : [];
$recentActivity = is_array($dashboard['recent_activity'] ?? null) ? $dashboard['recent_activity'] : [];
$activityLoaded = !empty($dashboard['activity_loaded']);
$upcomingSchedules = is_array($dashboard['upcoming_schedules'] ?? null) ? $dashboard['upcoming_schedules'] : [];
$overdueSchedules = $dashboard['overdue_schedules'] ?? null;
$overdueItems = is_array($dashboard['overdue_items'] ?? null) ? $dashboard['overdue_items'] : [];
$schedulesLoaded = !empty($dashboard['schedules_loaded']);
$dashboardCards = is_array($dashboardCards ?? null) ? $dashboardCards : [];
$cardOptions = is_array($dashboardCards['available'] ?? null) ? $dashboardCards['available'] : [];
$cardSelection = is_array($dashboardCards['selection'] ?? null) ? $dashboardCards['selection'] : DashboardCards::DEFAULTS;
$counts = array_merge($counts, is_array($dashboardCards['counts'] ?? null) ? $dashboardCards['counts'] : []);
$metricLabel = static fn(string $key): string => !isset($counts[$key]) ? '—' : (string)(int)$counts[$key];
$cardsNotice = is_string($_GET['cards'] ?? null) ? (string)$_GET['cards'] : '';
// Optional namespace for fresh installs. Legacy installations retain their old browser preference.
$siteInstanceId = (string)AppConfig::get('CMS_SITE_INSTANCE_ID', '');
if (!preg_match('/^[a-f0-9]{32}$/D', $siteInstanceId)) $siteInstanceId = '';

ob_start();
?>
<div class="admin-page-titlebar admin-page-titlebar--split">
  <div><h1><?= admin_e(t('Dashboard')); ?></h1><p><?= admin_e(t('dashboard.overview')); ?></p></div>
  <div class="admin-page-actions">
    <button type="button" class="admin-dashboard-welcome__restore" data-dashboard-welcome-restore hidden><?= admin_e(t('dashboard.welcome_show_again')); ?></button>
  </div>
</div>

<section class="admin-dashboard-welcome" data-dashboard-welcome data-user-id="<?= (int)($user['id'] ?? 0); ?>" data-site-instance-id="<?= admin_e($siteInstanceId); ?>" aria-labelledby="dashboard-welcome-heading" hidden>
  <button type="button" class="admin-modal__close admin-dashboard-welcome__close" data-dashboard-welcome-close aria-label="<?= admin_e(t('dashboard.welcome_dismiss')); ?>" title="<?= admin_e(t('dashboard.welcome_dismiss')); ?>">×</button>
  <div class="admin-dashboard-welcome__content">
    <span class="admin-dashboard-welcome__kicker"><?= admin_e(t('dashboard.welcome_kicker')); ?></span>
    <h2 id="dashboard-welcome-heading"><?= admin_e(t('dashboard.welcome_title')); ?></h2>
    <p><?= admin_e(t('dashboard.welcome_description')); ?></p>
  </div>
  <nav class="admin-dashboard-welcome__links" aria-label="<?= admin_e(t('dashboard.welcome_shortcuts')); ?>">
    <?php if (!empty($canManageContent)): ?>
      <a href="<?= admin_e(is_string($homePageEditUrl ?? null) ? $homePageEditUrl : '/admin/sider'); ?>" class="admin-dashboard-welcome__link"><?= admin_icon('edit', 'admin-dashboard-welcome__link-icon'); ?><span><?= admin_e(t('dashboard.welcome_edit_homepage')); ?></span></a>
    <?php endif; ?>
    <?php if (!empty($canManageSettings)): ?>
      <a href="/admin/indstillinger" class="admin-dashboard-welcome__link"><?= admin_icon('settings', 'admin-dashboard-welcome__link-icon'); ?><span><?= admin_e(t('dashboard.welcome_site_settings')); ?></span></a>
    <?php endif; ?>
    <a href="/" class="admin-dashboard-welcome__link" target="_blank" rel="noopener"><?= admin_icon('external', 'admin-dashboard-welcome__link-icon'); ?><span><?= admin_e(t('dashboard.welcome_view_site')); ?></span></a>
  </nav>
</section>

<?php if (!empty($canViewUpdateNotice)): ?>
<section class="admin-panel admin-dashboard-attention" data-dashboard-update-notice data-update-url="/admin/index.php?mode=update-notice" aria-labelledby="dashboard-update-title" hidden>
  <div class="admin-panel__header"><h2 id="dashboard-update-title"><?= admin_e(t('updates.available')); ?></h2></div>
  <div class="admin-dashboard-attention__body">
    <?= admin_icon('refresh-cw', 'admin-dashboard-attention__icon'); ?>
    <div>
      <strong><?= admin_e(t('updates.latest_version')); ?>: <span data-dashboard-update-version></span></strong>
      <p><?= admin_e(t('updates.available_description')); ?></p>
      <a class="admin-text-action admin-dashboard-attention__action" href="/admin/tools/update"><?= admin_e(t('updates.open')); ?> <span aria-hidden="true">→</span></a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (is_int($overdueSchedules) && $overdueSchedules > 0): ?>
<section class="admin-panel admin-dashboard-attention" aria-labelledby="dashboard-attention-title">
  <div class="admin-panel__header"><h2 id="dashboard-attention-title"><?= admin_e(t('dashboard.needs_attention')); ?></h2></div>
  <div class="admin-dashboard-attention__body">
    <?= admin_icon('warning', 'admin-dashboard-attention__icon'); ?>
    <div>
      <strong><?= admin_e(t($overdueSchedules === 1 ? 'dashboard.overdue_title_one' : 'dashboard.overdue_title_many', ['count' => $overdueSchedules])); ?></strong>
      <p><?= admin_e(t('dashboard.overdue_description')); ?></p>
      <?php if ($overdueItems): ?>
        <ul class="admin-dashboard-attention__list">
          <?php foreach ($overdueItems as $overdue): ?>
            <li><a href="<?= admin_e((string)$overdue['edit_url']); ?>"><?= admin_e((string)$overdue['title']); ?></a><span> · <?= admin_e(ScheduledContent::typeLabel((string)$overdue['type'])); ?> · <?= admin_e(t((string)$overdue['action_type'] === 'unpublish' ? 'dashboard.unpublish' : 'dashboard.publish')); ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <a class="admin-text-action admin-dashboard-attention__action" href="/admin/scheduled-content?scope=overdue"><?= admin_e(t('dashboard.review_schedules')); ?> <span aria-hidden="true">→</span></a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($cardsNotice === 'saved'): ?>
<div class="admin-notice admin-notice--success" role="status"><?= admin_e(t('dashboard.cards_saved')); ?></div>
<?php elseif ($cardsNotice === 'invalid'): ?>
<div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t('dashboard.cards_invalid')); ?></div>
<?php elseif ($cardsNotice === 'error' || !empty($dashboardCards['needs_migration'])): ?>
<div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t('dashboard.cards_unavailable')); ?></div>
<?php endif; ?>

<section class="admin-metrics" aria-label="<?= admin_e(t('dashboard.metrics')); ?>" data-dashboard-cards data-save-url="/admin/index.php?mode=dashboard-cards-ajax">
  <input type="hidden" data-dashboard-cards-csrf value="<?= admin_e(admin_csrf_token()); ?>">
  <?php foreach ($cardSelection as $index => $key): ?>
    <?php if (!isset($cardOptions[$key])) continue; $card = $cardOptions[$key]; ?>
    <div class="admin-metric" data-dashboard-card data-metric="<?= admin_e($key); ?>" data-slot="<?= (int)$index; ?>">
      <a class="admin-metric__link" data-dashboard-card-link href="<?= admin_e($card['url']); ?>">
        <span class="admin-metric__content"><span data-dashboard-card-label><?= admin_e(t($card['label'])); ?></span><strong data-dashboard-card-count><?= admin_e($metricLabel($key)); ?></strong></span>
        <span data-dashboard-card-icon><?= admin_icon($card['icon'], 'admin-metric__icon'); ?></span>
      </a>
      <button type="button" class="admin-metric__settings" data-dashboard-card-trigger
        aria-label="<?= admin_e(t('dashboard.cards_change', ['number' => (int)$index + 1])); ?>"
        aria-controls="dashboard-card-menu-<?= (int)$index; ?>" aria-expanded="false"
        title="<?= admin_e(t('dashboard.cards_change', ['number' => (int)$index + 1])); ?>">
        <?= admin_icon('dots', 'admin-metric__settings-icon'); ?>
      </button>
      <div class="admin-metric__menu" id="dashboard-card-menu-<?= (int)$index; ?>" data-dashboard-card-menu hidden>
        <span class="admin-metric__menu-heading"><?= admin_e(t('dashboard.cards_pick')); ?></span>
        <?php foreach ($cardOptions as $optionKey => $option): ?>
        <button type="button" class="admin-metric__choice" data-dashboard-card-choice="<?= admin_e($optionKey); ?>" aria-pressed="<?= $key === $optionKey ? 'true' : 'false'; ?>">
          <span><?= admin_e(t($option['label'])); ?></span><?= admin_icon('check', 'admin-metric__choice-check'); ?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <div class="sr-only" role="status" aria-live="polite" data-dashboard-cards-status></div>
</section>
<div class="admin-notice admin-notice--error" data-dashboard-cards-notice role="alert" hidden><?= admin_e(t('dashboard.cards_save_failed')); ?></div>
<div data-dashboard-cards-icons hidden>
  <?php foreach ($cardOptions as $optionKey => $option): ?>
    <template data-dashboard-icon="<?= admin_e($optionKey); ?>"><?= admin_icon($option['icon'], 'admin-metric__icon'); ?></template>
  <?php endforeach; ?>
</div>
<noscript>
  <section class="admin-panel admin-dashboard-cards-panel" aria-labelledby="dashboard-cards-noscript-title">
    <div class="admin-panel__header"><h2 id="dashboard-cards-noscript-title"><?= admin_e(t('dashboard.cards_customize')); ?></h2></div>
    <form method="post" action="/admin/index.php?mode=dashboard-cards" class="admin-dashboard-cards-panel__body">
      <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
      <div class="admin-dashboard-cards-panel__fields">
        <?php foreach ($cardSelection as $index => $currentKey): ?>
          <div class="admin-field"><label for="dashboard-card-slot-<?= (int)$index; ?>"><?= admin_e(t('dashboard.cards_slot', ['number' => (int)$index + 1])); ?></label>
            <select id="dashboard-card-slot-<?= (int)$index; ?>" name="cards[]" required>
              <?php foreach ($cardOptions as $optionKey => $option): ?>
                <option value="<?= admin_e($optionKey); ?>"<?= $optionKey === $currentKey ? ' selected' : ''; ?>><?= admin_e(t($option['label'])); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="admin-button"><?= admin_e(t('dashboard.cards_save')); ?></button>
    </form>
  </section>
</noscript>

<div class="admin-dashboard-grid admin-dashboard-grid--equal">
<section class="admin-panel" aria-labelledby="recent-activity-title">
  <div class="admin-panel__header"><h2 id="recent-activity-title"><?= admin_e(t('activity.recent')); ?></h2><?php if (!empty($canViewActivityLog)): ?><a class="admin-panel__action" href="/admin/tools/activity"><?= admin_e(t('activity.view_all')); ?> <span aria-hidden="true">→</span></a><?php endif; ?></div>
  <?php if ($recentActivity): ?>
    <div class="admin-table-wrap"><table class="admin-list-table">
      <thead><tr><th><?= admin_e(t('activity.activity')); ?></th><th><?= admin_e(t('activity.user')); ?></th><th><?= admin_e(t('activity.date_time')); ?></th></tr></thead>
      <tbody><?php foreach ($recentActivity as $entry): ?><tr>
        <td><strong><?= admin_e(admin_activity_label((string)$entry['action'], (string)$entry['subject_type'])); ?></strong><?php if ((string)$entry['subject_label'] !== ''): ?><span class="admin-activity-subject"><?= admin_e((string)$entry['subject_label']); ?></span><?php endif; ?></td>
        <td><?= admin_e((string)$entry['actor_name']); ?></td>
        <td><time datetime="<?= admin_e(str_replace(' ', 'T', (string)$entry['created_at_utc']) . 'Z'); ?>"><?= admin_e(ActivityLog::localDate((string)$entry['created_at_utc'])); ?></time></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
  <?php elseif (!$activityLoaded): ?><div class="admin-empty-state admin-empty-state--large" role="status"><strong><?= admin_e(t('dashboard.activity_unavailable')); ?></strong></div>
  <?php else: ?><div class="admin-empty-state admin-empty-state--large"><strong><?= admin_e(t('activity.empty')); ?></strong><span><?= admin_e(t('activity.empty_hint')); ?></span></div><?php endif; ?>
</section>
<section class="admin-panel" id="scheduled-content" aria-labelledby="scheduled-content-title">
  <div class="admin-panel__header"><h2 id="scheduled-content-title"><?= admin_e(t('dashboard.scheduled_content')); ?></h2><a class="admin-panel__action" href="/admin/scheduled-content"><?= admin_e(t('dashboard.view_schedules')); ?> <span aria-hidden="true">→</span></a></div>
  <?php if ($upcomingSchedules): ?>
    <div class="admin-table-wrap"><table class="admin-list-table">
      <thead><tr><th><?= admin_e(t('schedule.content')); ?></th><th><?= admin_e(t('dashboard.action')); ?></th><th><?= admin_e(t('dashboard.date_time')); ?></th></tr></thead>
      <tbody><?php foreach ($upcomingSchedules as $schedule): ?><tr>
        <td><strong><?= admin_e((string)$schedule['title']); ?></strong><span class="admin-table-secondary"><?= admin_e(ScheduledContent::typeLabel((string)$schedule['type'])); ?></span></td>
        <td><?= admin_e(t((string)$schedule['action_type'] === 'publish' ? 'dashboard.publish' : 'dashboard.unpublish')); ?></td>
        <td><time datetime="<?= admin_e(str_replace(' ', 'T', (string)$schedule['due_at_utc']) . 'Z'); ?>"><?= admin_e(PageScheduler::localLabel((string)$schedule['due_at_utc'])); ?></time></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
  <?php elseif (!$schedulesLoaded): ?><div class="admin-empty-state admin-empty-state--large"><strong><?= admin_e(t('dashboard.schedules_unavailable')); ?></strong></div>
  <?php else: ?><div class="admin-empty-state admin-empty-state--large"><strong><?= admin_e(t('dashboard.no_scheduled_content')); ?></strong><span><?= admin_e(t('dashboard.no_scheduled_hint')); ?></span></div><?php endif; ?>
</section>
</div>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'Dashboard';
require __DIR__ . '/partials/layout.php';
