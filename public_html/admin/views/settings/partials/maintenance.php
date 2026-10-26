      <section class="admin-panel">
        <div class="admin-panel__header"><h2><?= admin_e(t('Vedligeholdelse')); ?></h2></div>
        <div class="admin-form-section">
          <label class="admin-checkbox"><input type="checkbox" name="maintenance_mode" value="1" <?= ($form['maintenance_mode'] ?? '0') === '1' ? 'checked' : ''; ?>><span><?= admin_e(t('Aktivér vedligeholdelsestilstand')); ?></span></label>
          <p class="admin-field__help"><?= admin_e(t('Den offentlige hjemmeside svarer med HTTP 503. CMS’et forbliver tilgængeligt.')); ?></p>
          <div class="admin-field"><label for="settings-maintenance-message"><?= admin_e(t('Besked')); ?></label><textarea id="settings-maintenance-message" name="maintenance_message" maxlength="600"><?= admin_e((string)($form['maintenance_message'] ?? '')); ?></textarea><?php if($fieldError('maintenance_message')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('maintenance_message'))); ?></p><?php endif; ?></div>
        </div>
      </section>
