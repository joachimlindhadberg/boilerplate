      <section class="admin-panel">
        <div class="admin-panel__header"><h2><?= admin_e(t('Notifikationer')); ?></h2></div>
        <div class="admin-form-section">
          <div class="admin-form-row">
            <div class="admin-field">
              <label for="settings-admin-email"><?= admin_e(t('Administrator-e-mail *')); ?></label>
              <input id="settings-admin-email" type="email" name="admin_email" value="<?= admin_e((string)($form['admin_email'] ?? '')); ?>" required>
              <p class="admin-field__help"><?= admin_e(t('Bruges til systemrelaterede beskeder og som fallback for notifikationer.')); ?></p>
              <?php if($fieldError('admin_email')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('admin_email'))); ?></p><?php endif; ?>
            </div>
            <div class="admin-field">
              <label for="settings-notification-email"><?= admin_e(t('Standard notifikations-e-mail')); ?></label>
              <input id="settings-notification-email" type="email" name="notification_email" value="<?= admin_e((string)($form['notification_email'] ?? '')); ?>" placeholder="<?= admin_e((string)($form['admin_email'] ?? '')); ?>">
              <p class="admin-field__help"><?= admin_e(t('Modtager notifikationer fra formularer og andre CMS-funktioner. Tomt felt bruger administrator-e-mailen.')); ?></p>
              <?php if($fieldError('notification_email')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('notification_email'))); ?></p><?php endif; ?>
            </div>
          </div>
        </div>
      </section>
