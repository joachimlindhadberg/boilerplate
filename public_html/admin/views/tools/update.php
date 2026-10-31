<?php
/** Minimal, Core-owned official release picker. No client-supplied ZIP or URL. */
$report = is_array($report ?? null) ? $report : [];
$installedVersion = trim((string)($report['version'] ?? ''));
if (!UpdateReleaseVersion::valid($installedVersion)) $installedVersion = '—';
$updateChoices = is_array($updateChoices ?? null) ? $updateChoices : null;
$pendingRecovery = (bool)($pendingRecovery ?? false);
$updateNotice = is_array($updateNotice ?? null) ? $updateNotice : null;
$choicesStatus = (string)($updateChoices['status'] ?? 'not_checked');
$releases = ($choicesStatus === 'available' && is_array($updateChoices['releases'] ?? null))
    ? $updateChoices['releases'] : [];
$statusKeys = [
    'not_checked' => ['updates.not_checked', 'updates.not_checked_description'],
    'current' => ['updates.up_to_date', 'updates.up_to_date_beta_description'],
    'no_release' => ['updates.no_beta_release', 'updates.no_beta_release_description'],
    'error' => ['updates.check_failed', 'updates.check_failed_description'],
    'not_configured' => ['updates.source_pending', 'updates.source_pending_description'],
    'connection_error' => ['updates.connection_error', 'updates.connection_error_description'],
    'github_rejected' => ['updates.github_rejected', 'updates.github_rejected_description'],
    'rate_limited' => ['updates.rate_limited', 'updates.rate_limited_description'],
    'service_unavailable' => ['updates.service_unavailable', 'updates.service_unavailable_description'],
    'invalid_tag' => ['updates.invalid_tag', 'updates.invalid_tag_description'],
    'invalid_response' => ['updates.invalid_response', 'updates.invalid_response_description'],
    'csrf' => ['updates.session_expired', 'updates.session_expired_description'],
];
[$statusTitle, $statusDescription] = $statusKeys[$choicesStatus] ?? $statusKeys['error'];
ob_start();
?>
<div class="admin-page-titlebar admin-page-titlebar--split">
  <div><h1><?= admin_e(t('updates.title')); ?></h1><p><?= admin_e(t('updates.description')); ?></p></div>
  <a class="admin-button admin-button--secondary" href="/admin/tools"><?= admin_icon('arrow-left', 'admin-button__icon'); ?><?= admin_e(t('Tilbage til Værktøjer')); ?></a>
</div>
<?php if ($updateNotice !== null): ?>
  <p class="admin-notice <?= ($updateNotice['type'] ?? '') === 'error' ? 'admin-notice--error' : 'admin-notice--success'; ?>" role="status"><?= admin_e((string)($updateNotice['message'] ?? '')); ?></p>
<?php endif; ?>
<section class="admin-panel admin-tools-section" aria-labelledby="updates-current-title">
  <div class="admin-panel__header admin-tools-section__header">
    <div><h2 id="updates-current-title"><?= admin_e(t('updates.installed')); ?></h2>
      <p><?= admin_e(t('updates.current_version')); ?>: <?= admin_e($installedVersion); ?></p>
    </div>
  </div>
  <div class="admin-tools-body">
    <div class="admin-tools-feature">
      <?php if ($choicesStatus !== 'not_checked'): ?>
      <div>
        <?php if ($releases): ?>
          <strong><?= admin_e(t('updates.choices_title')); ?></strong>
          <span><?= admin_e(t('updates.choices_description')); ?></span>
        <?php else: ?>
          <strong><?= admin_e(t($statusTitle)); ?></strong>
          <span><?= admin_e(t($statusDescription)); ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <form method="post" action="/admin/tools/update">
        <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
        <input type="hidden" name="action" value="check_for_updates">
        <button type="submit" class="admin-button admin-button--<?= $releases ? 'secondary' : 'primary'; ?>"><?= admin_icon('search', 'admin-button__icon'); ?><?= admin_e(t('updates.check')); ?></button>
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
    <?php elseif ($releases): ?>
      <form method="post" action="/admin/tools/update" data-official-update-form>
        <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
        <input type="hidden" name="action" value="install_official_update">
        <div class="admin-update-options" role="group" aria-label="<?= admin_e(t('updates.choices_title')); ?>">
          <?php foreach ($releases as $index => $release): ?>
            <?php
              $kind = (string)($release['kind'] ?? '');
              $version = (string)($release['version'] ?? '');
              $releaseUrl = (string)($release['url'] ?? '');
              if (!in_array($kind, ['stable', 'beta'], true) || !UpdateReleaseVersion::valid($version)) continue;
              $safeUrl = (bool)preg_match('~^https://github\.com/joachimlindhadberg/boilerplate/releases/tag/v?[0-9.]+(?:-(?:beta|rc)\.[1-9][0-9]*)?$~D', $releaseUrl);
            ?>
            <div class="admin-update-option">
              <label class="admin-update-option__choice">
                <input type="radio" name="release_selection" value="<?= admin_e($kind . ':' . $version); ?>" <?= $index === 0 ? 'checked' : ''; ?> required>
                <span class="admin-update-option__details">
                  <strong><?= admin_e($version); ?> — <?= admin_e(t($kind === 'stable' ? 'updates.stable_label' : 'updates.preview_label')); ?></strong>
                  <span><?= admin_e(t($kind === 'stable' ? 'updates.stable_help' : 'updates.preview_help')); ?></span>
                </span>
              </label>
              <?php if ($safeUrl): ?>
                <a href="<?= admin_e($releaseUrl); ?>" target="_blank" rel="noopener noreferrer"><?= admin_e(t('updates.view_release')); ?></a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="admin-form-actions admin-form-actions--left">
          <button type="submit" class="admin-button admin-button--primary"><?= admin_icon('download', 'admin-button__icon'); ?><?= admin_e(t('updates.update_selected')); ?></button>
        </div>
        <p hidden role="status" data-official-update-progress><?= admin_e(t('updates.updating')); ?></p>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'updates.title';
require __DIR__ . '/../partials/layout.php';
