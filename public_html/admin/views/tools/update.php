<?php
/**
 * Core-owned admin presentation; official updates only, no manual ZIP upload.
 * Installation is authorized separately by tools.manage, CSRF and the backend.
 */
$report = is_array($report ?? null) ? $report : [];
$installedVersion = trim((string)($report['version'] ?? ''));
if (!UpdateReleaseVersion::valid($installedVersion)) {
    $installedVersion = '—';
}
$updateChannel = UpdateReleaseVersion::channel((string)($updateChannel ?? 'stable'));
$lookupResult = is_array($lookupResult ?? null) ? $lookupResult : null;
$prepareResult = is_array($prepareResult ?? null) ? $prepareResult : null;
$preparedRelease = is_array($preparedRelease ?? null) ? $preparedRelease : null;
$pendingRecovery = (bool)($pendingRecovery ?? false);
$updateNotice = is_array($updateNotice ?? null) ? $updateNotice : null;
$preparedOk = ($preparedRelease['status'] ?? '') === 'prepared_for_review_only'
    && ($preparedRelease['install_ready'] ?? true) === false;
if ($preparedOk && $lookupResult === null) {
    $lookupResult = ['status' => 'available', 'latest_version' => (string)$preparedRelease['version'],
        'release_url' => ''];
}
$prepareOk = ($prepareResult['status'] ?? '') === 'prepared_for_review_only';
$prepareFailed = $prepareResult !== null && !$prepareOk;
$lookupStatus = (string)($lookupResult['status'] ?? 'not_checked');
$allowedStatuses = ['not_checked', 'not_configured', 'no_release', 'current', 'available', 'error', 'connection_error', 'github_rejected', 'rate_limited', 'service_unavailable', 'invalid_tag', 'invalid_response', 'csrf'];
if (!in_array($lookupStatus, $allowedStatuses, true)) $lookupStatus = 'error';
$statusKeys = [
    'not_checked' => ['updates.not_checked', 'updates.not_checked_description'],
    'not_configured' => ['updates.source_pending', 'updates.source_pending_description'],
    'no_release' => ['updates.no_release', 'updates.no_release_description'],
    'current' => ['updates.up_to_date', 'updates.up_to_date_description'],
    'available' => ['updates.available', 'updates.available_description'],
    'error' => ['updates.check_failed', 'updates.check_failed_description'],
    'connection_error' => ['updates.connection_error', 'updates.connection_error_description'],
    'github_rejected' => ['updates.github_rejected', 'updates.github_rejected_description'],
    'rate_limited' => ['updates.rate_limited', 'updates.rate_limited_description'],
    'service_unavailable' => ['updates.service_unavailable', 'updates.service_unavailable_description'],
    'invalid_tag' => ['updates.invalid_tag', 'updates.invalid_tag_description'],
    'invalid_response' => ['updates.invalid_response', 'updates.invalid_response_description'],
    'csrf' => ['updates.session_expired', 'updates.session_expired_description'],
];
[$statusTitle, $statusDescription] = $statusKeys[$lookupStatus];
if ($updateChannel === 'beta') {
    if ($lookupStatus === 'no_release') [$statusTitle, $statusDescription] = ['updates.no_beta_release', 'updates.no_beta_release_description'];
    if ($lookupStatus === 'current') $statusDescription = 'updates.up_to_date_beta_description';
    if ($lookupStatus === 'available') $statusDescription = 'updates.available_beta_description';
}
$latest = (string)($lookupResult['latest_version'] ?? '');
$releaseUrl = (string)($lookupResult['release_url'] ?? '');
$showRelease = $lookupStatus === 'available'
    && UpdateReleaseVersion::valid($latest)
    && (bool)preg_match('~^https://github\.com/[A-Za-z0-9%-]+/[A-Za-z0-9%._-]+/releases/tag/v?[0-9.]+(?:-(?:beta|rc)\.[1-9][0-9]*)?$~D', $releaseUrl);
ob_start();
?>
<div class="admin-page-titlebar admin-page-titlebar--split">
  <div>
    <h1><?= admin_e(t('updates.title')); ?></h1>
    <p><?= admin_e(t('updates.description')); ?></p>
  </div>
  <a class="admin-button admin-button--secondary" href="/admin/tools"><?= admin_icon('arrow-left', 'admin-button__icon'); ?><?= admin_e(t('Tilbage til Værktøjer')); ?></a>
</div>
<?php if ($updateNotice !== null): ?>
  <p class="admin-notice <?= ($updateNotice['type'] ?? '') === 'error' ? 'admin-notice--error' : 'admin-notice--success'; ?>" role="status"><?= admin_e((string)($updateNotice['message'] ?? '')); ?></p>
<?php endif; ?>
<section class="admin-panel admin-tools-section" aria-labelledby="updates-current-title">
  <div class="admin-panel__header admin-tools-section__header">
    <div>
      <h2 id="updates-current-title"><?= admin_e(t('updates.installed')); ?></h2>
      <p><?= admin_e(t('updates.current_version')); ?>: <?= admin_e($installedVersion); ?></p>
    </div>
  </div>
  <div class="admin-tools-body">
    <form method="post" action="/admin/tools/update" class="admin-tools-feature">
      <div>
        <strong><?= admin_e(t('updates.channel')); ?></strong>
        <span><?= admin_e(t('updates.channel_description')); ?></span>
        <label><input type="radio" name="channel" value="stable" <?= $updateChannel === 'stable' ? 'checked' : ''; ?>> <?= admin_e(t('updates.channel_stable')); ?></label>
        <label><input type="radio" name="channel" value="beta" <?= $updateChannel === 'beta' ? 'checked' : ''; ?>> <?= admin_e(t('updates.channel_beta')); ?></label>
        <?php if ($updateChannel === 'beta'): ?><span><?= admin_e(t('updates.beta_caution')); ?></span><?php endif; ?>
      </div>
      <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
      <input type="hidden" name="action" value="set_update_channel">
      <button type="submit" class="admin-button admin-button--secondary"><?= admin_e(t('updates.save_channel')); ?></button>
    </form>
    <div class="admin-tools-feature" role="status">
      <div>
        <strong><?= admin_e(t($statusTitle)); ?></strong>
        <span><?= admin_e(t($statusDescription)); ?></span>
        <?php if ($showRelease || $preparedOk): ?>
          <span><?= admin_e(t('updates.latest_version')); ?>: <?= admin_e($latest); ?></span>
        <?php endif; ?>
      </div>
      <form method="post" action="/admin/tools/update">
        <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
        <input type="hidden" name="action" value="check_for_updates">
        <button type="submit" class="admin-button admin-button--primary"><?= admin_icon('search', 'admin-button__icon'); ?><?= admin_e(t('updates.check')); ?></button>
      </form>
    </div>
    <?php if ($pendingRecovery): ?>
      <div class="admin-tools-feature" role="status">
        <div><strong><?= admin_e(t('updates.update_recovery_required')); ?></strong></div>
        <form method="post" action="/admin/tools/update">
          <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
          <input type="hidden" name="action" value="restore_interrupted_update">
          <button type="submit" class="admin-button admin-button--secondary"><?= admin_e(t('updates.restore_button')); ?></button>
        </form>
      </div>
    <?php elseif ($showRelease || $preparedOk): ?>
      <?php $targetVersion = $showRelease ? $latest : (string)$preparedRelease['version']; ?>
      <div class="admin-tools-feature">
        <div>
          <?php if ($showRelease): ?>
            <a class="admin-button admin-button--secondary" href="<?= admin_e($releaseUrl); ?>" target="_blank" rel="noopener noreferrer"><?= admin_icon('external', 'admin-button__icon'); ?><?= admin_e(t('updates.view_release')); ?></a>
          <?php endif; ?>
        </div>
        <form method="post" action="/admin/tools/update" data-official-update-form>
          <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
          <input type="hidden" name="action" value="install_official_update">
          <input type="hidden" name="expected_version" value="<?= admin_e($targetVersion); ?>">
          <input type="hidden" name="expected_channel" value="<?= admin_e($updateChannel); ?>">
          <button type="submit" class="admin-button admin-button--primary"><?= admin_icon('download', 'admin-button__icon'); ?><?= admin_e(t('updates.update_now')); ?></button>
          <p hidden role="status" data-official-update-progress><?= admin_e(t('updates.updating')); ?></p>
        </form>
      </div>
    <?php endif; ?>
    <?php if ($prepareFailed): ?>
      <p role="status"><?= admin_e(t('updates.prepare_failed')); ?></p>
    <?php endif; ?>
  </div>
</section>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'updates.title';
require __DIR__ . '/../partials/layout.php';
