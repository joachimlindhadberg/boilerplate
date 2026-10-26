<section class="admin-panel admin-modules-panel" aria-labelledby="modules-list-title">
  <div class="admin-list-summary">
    <h2 id="modules-list-title"><?= admin_e(t('modules.installed')); ?></h2>
    <span><?= admin_e(t(count($modules) === 1 ? 'modules.count_one' : 'modules.count_many', ['count' => count($modules)])); ?></span>
  </div>

  <?php if (!$modules): ?>
    <div class="admin-empty-state admin-empty-state--large">
      <?= admin_icon('cube', 'admin-empty-state__icon'); ?>
      <strong><?= admin_e(t('modules.empty')); ?></strong>
      <span><?= admin_e(t('modules.empty_hint')); ?> <code>/modules/&lt;slug&gt;/</code>.</span>
    </div>
  <?php else: ?>
    <div class="admin-module-list">
      <?php foreach ($modules as $module):
        $active = !empty($module['active']);
        $valid = !empty($module['valid']) && !empty($module['available']);
        $compatible = !empty($module['compatible']);
        $canActivate = $valid && $compatible && empty($module['update_available']) && empty($module['version_mismatch']);
        $updateAvailable = $valid && $compatible && !empty($module['update_available']);
        $versionMismatch = !empty($module['version_mismatch']);
        $runtimeError = trim((string)($module['runtime_error'] ?? ''));
        $manifestError = trim((string)($module['error'] ?? ''));
        $compatibilityError = trim((string)($module['compatibility_error'] ?? ''));
      ?>
        <article class="admin-module-card<?= $active ? ' is-active' : ''; ?><?= !$valid ? ' has-error' : ''; ?>">
          <div class="admin-module-card__main">
            <div class="admin-module-card__heading">
              <div>
                <h3><?= admin_e((string)($module['name'] ?? $module['slug'] ?? t('Module'))); ?></h3>
                <p><?= admin_e(t((string)($module['description'] ?? ''))); ?></p>
              </div>
            </div>

            <div class="admin-module-card__meta">
              <span><?= admin_e(t('Version')); ?> <strong><?= admin_e((string)($module['version'] ?? '—')); ?></strong></span>
              <?php if ($updateAvailable): ?><span><?= admin_e(t('plugin.installed_version')); ?> <strong><?= admin_e((string)$module['installed_version']); ?></strong></span><?php endif; ?>
              <?php if (!empty($module['author'])): ?><span><?= admin_e(t('Af')); ?> <strong><?= admin_e((string)$module['author']); ?></strong></span><?php endif; ?>
              <?php if (!empty($module['requires'])): ?><span><?= admin_e(t('Kræver Boilerplate')); ?> <strong><?= admin_e((string)$module['requires']); ?>+</strong></span><?php endif; ?>
            </div>

            <?php if ($updateAvailable): ?>
              <div class="admin-module-card__error"><?= admin_e(t('plugin.update_available')); ?></div>
            <?php elseif ($versionMismatch): ?>
              <div class="admin-module-card__error"><?= admin_e(t('plugin.downgrade_warning')); ?></div>
            <?php elseif (!$valid && $manifestError !== ''): ?>
              <div class="admin-module-card__error"><?= admin_e(t('modules.error_prefix')); ?> <?= admin_e($manifestError); ?></div>
            <?php elseif (!$compatible && $compatibilityError !== ''): ?>
              <div class="admin-module-card__error"><?= admin_e(t('modules.compatibility_prefix')); ?> <?= admin_e($compatibilityError); ?></div>
            <?php elseif ($runtimeError !== ''): ?>
              <div class="admin-module-card__error"><?= admin_e(t('modules.runtime_prefix')); ?> <?= admin_e($runtimeError); ?></div>
            <?php endif; ?>
          </div>

          <div class="admin-module-card__actions">
            <span class="admin-status-label <?= $active ? 'admin-status-label--module-active' : ''; ?>"><?= admin_e(t($active ? 'Aktiv' : 'Inaktiv')); ?></span>
            <?php if ($updateAvailable): ?>
              <button
                class="admin-button admin-button--secondary admin-button--compact"
                type="button"
                data-module-update-open
                data-module-slug="<?= admin_e((string)($module['slug'] ?? '')); ?>"
                data-module-name="<?= admin_e((string)($module['name'] ?? $module['slug'] ?? t('Module'))); ?>"
              ><?= admin_e(t('plugin.complete_update')); ?></button>
            <?php endif; ?>
            <?php if ($active): ?>
              <button
                class="admin-button admin-button--secondary admin-button--compact"
                type="button"
                data-module-deactivate-open
                data-module-slug="<?= admin_e((string)($module['slug'] ?? '')); ?>"
                data-module-name="<?= admin_e((string)($module['name'] ?? $module['slug'] ?? t('Module'))); ?>"
              ><?= admin_e(t('Deaktiver')); ?></button>
            <?php else: ?>
              <form method="post" action="/admin/modules?mode=activate">
                <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
                <input type="hidden" name="slug" value="<?= admin_e((string)($module['slug'] ?? '')); ?>">
                <button class="admin-button admin-button--compact" type="submit" <?= !$canActivate ? 'disabled' : ''; ?>><?= admin_e(t('Aktiver')); ?></button>
              </form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>