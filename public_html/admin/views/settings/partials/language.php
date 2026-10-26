      <section class="admin-panel">
        <div class="admin-panel__header"><h2><?= admin_e(t('settings.language_region')); ?></h2></div>
        <div class="admin-form-section">
          <div class="admin-field">
            <label for="settings-admin-language"><?= admin_e(t('settings.admin_language')); ?></label>
            <select id="settings-admin-language" name="admin_language">
              <?php foreach ([
                'en' => 'locale.english',
                'da' => 'locale.danish',
                'de' => 'locale.german',
                'sv' => 'locale.swedish',
                'nb' => 'locale.norwegian_bokmal',
              ] as $languageCode => $translationKey): ?>
                <option value="<?= admin_e($languageCode); ?>" <?= ($form['admin_language'] ?? 'en') === $languageCode ? 'selected' : ''; ?>><?= admin_e(t($translationKey)); ?></option>
              <?php endforeach; ?>
            </select>
            <p class="admin-field__help"><?= admin_e(t('settings.admin_language_help')); ?></p>
            <?php if($fieldError('admin_language')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('admin_language'))); ?></p><?php endif; ?>
          </div>
        </div>
      </section>
