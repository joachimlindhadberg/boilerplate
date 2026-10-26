      <section class="admin-panel">
        <div class="admin-panel__header"><h2><?= admin_e(t('Website')); ?></h2></div>
        <div class="admin-form-section">
          <div class="admin-form-row">
            <div class="admin-field"><label for="settings-site-name"><?= admin_e(t('Sitenavn *')); ?></label><input id="settings-site-name" name="site_name" maxlength="120" value="<?= admin_e((string)($form['site_name'] ?? '')); ?>" required><?php if($fieldError('site_name')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('site_name'))); ?></p><?php endif; ?></div>
            <div class="admin-field"><label for="settings-site-tagline"><?= admin_e(t('settings.tagline')); ?></label><input id="settings-site-tagline" name="site_tagline" maxlength="180" value="<?= admin_e((string)($form['site_tagline'] ?? '')); ?>"><?php if($fieldError('site_tagline')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('site_tagline'))); ?></p><?php endif; ?></div>
          </div>
          <div class="admin-form-row">
            <div class="admin-field"><label for="settings-site-url"><?= admin_e(t('settings.website_url')); ?></label><input id="settings-site-url" type="url" name="site_url" value="<?= admin_e((string)($form['site_url'] ?? '')); ?>" required><p class="admin-field__help"><?= admin_e(t('settings.app_url_priority')); ?></p><p class="admin-field__help"><?= admin_e(t('settings.active_url')); ?>: <?= admin_e((string)($resolvedSiteUrl['url'] ?? '')); ?> (<?= admin_e(t('settings.url_source_' . (string)($resolvedSiteUrl['source'] ?? 'request'))); ?>)</p><?php if($fieldError('site_url')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('site_url'))); ?></p><?php endif; ?></div>
            <div class="admin-field"><label for="settings-timezone"><?= admin_e(t('Tidszone *')); ?></label><input id="settings-timezone" name="timezone" value="<?= admin_e((string)($form['timezone'] ?? 'Europe/Copenhagen')); ?>" required><p class="admin-field__help"><?= admin_e(t('IANA-format, fx Europe/Copenhagen.')); ?></p><?php if($fieldError('timezone')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('timezone'))); ?></p><?php endif; ?></div>
          </div>
        </div>
      </section>
