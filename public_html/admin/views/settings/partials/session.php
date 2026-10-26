      <section class="admin-panel">
        <div class="admin-panel__header"><h2><?= admin_e(t('CMS-session')); ?></h2></div>
        <div class="admin-form-section">
          <div class="admin-field"><label for="settings-session-timeout"><?= admin_e(t('Session timeout (min.)')); ?></label><input id="settings-session-timeout" type="number" min="5" max="1440" step="1" name="session_timeout_minutes" value="<?= admin_e((string)($form['session_timeout_minutes'] ?? '30')); ?>" required><p class="admin-field__help"><?= admin_e(t('Inaktivitet før CMS-login udløber.')); ?></p><?php if($fieldError('session_timeout_minutes')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('session_timeout_minutes'))); ?></p><?php endif; ?></div>
          <div class="admin-field"><label for="settings-remember-days"><?= admin_e(t('“Husk mig” (dage)')); ?></label><input id="settings-remember-days" type="number" min="1" max="365" step="1" name="remember_me_days" value="<?= admin_e((string)($form['remember_me_days'] ?? '30')); ?>" required><?php if($fieldError('remember_me_days')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('remember_me_days'))); ?></p><?php endif; ?></div>
        </div>
      </section>
