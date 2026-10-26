      <section class="admin-panel">
        <div class="admin-panel__header"><h2><?= admin_e(t('SEO')); ?></h2></div>
        <div class="admin-form-section">
          <div class="admin-field"><label for="settings-seo-description"><?= admin_e(t('Standard meta description *')); ?></label><textarea id="settings-seo-description" name="seo_default_description" maxlength="320" required><?= admin_e((string)($form['seo_default_description'] ?? '')); ?></textarea><p class="admin-field__help"><?= admin_e(t('Bruges som fallback, når en side ikke har sin egen SEO-beskrivelse.')); ?></p><?php if($fieldError('seo_default_description')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('seo_default_description'))); ?></p><?php endif; ?></div>
          <div class="admin-form-row">
            <div class="admin-field"><label for="settings-separator"><?= admin_e(t('Titel-separator')); ?></label><input id="settings-separator" name="seo_title_separator" maxlength="3" value="<?= admin_e((string)($form['seo_title_separator'] ?? '|')); ?>" required><p class="admin-field__help"><?= admin_e(t('Bruges i sidetitler og SEO.')); ?></p><?php if($fieldError('seo_title_separator')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('seo_title_separator'))); ?></p><?php endif; ?></div>
            <div class="admin-field"><label><?= admin_e(t('Indeksering')); ?></label><label class="admin-checkbox"><input type="checkbox" name="seo_indexing" value="1" <?= ($form['seo_indexing'] ?? '1') === '1' ? 'checked' : ''; ?>><span><?= admin_e(t('Tillad søgemaskiner at indeksere sitet')); ?></span></label><p class="admin-field__help"><?= admin_e(t('Slår også sitemap fra og sætter robots til noindex, når deaktiveret.')); ?></p></div>
          </div>
        </div>
      </section>
