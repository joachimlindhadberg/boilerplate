  <section class="admin-panel admin-tools-section" aria-labelledby="tools-import-title">
    <div class="admin-panel__header admin-tools-section__header"><div><h2 id="tools-import-title"><?= admin_e(t('Importer')); ?></h2><p><?= admin_e(t('Importer en JSON-datafil, som tidligere er eksporteret fra Boilerplate CMS.')); ?></p></div></div>
    <div class="admin-tools-body">
      <?php if (!empty($errors['import'])): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$errors['import'])); ?></div><?php endif; ?>
      <form method="post" action="/admin/vaerktoejer" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
        <input type="hidden" name="action" value="import">
        <div class="admin-field">
          <span class="admin-field__label"><?= admin_e(t('Boilerplate eksportfil')); ?></span>
          <label class="admin-upload-dropzone"
                 data-admin-upload-dropzone
                 data-upload-types="application/json,text/json"
                 data-upload-extensions=".json"
                 data-upload-max-bytes="10485760"
                 data-upload-type-error="Vælg en JSON-fil, som er eksporteret fra Boilerplate CMS."
                 data-upload-size-error="JSON-filen må højst fylde 10 MB."
                 for="tools-import-file">
            <input class="admin-upload-dropzone__input"
                   id="tools-import-file"
                   type="file"
                   name="import_file"
                   accept="application/json,.json"
                   required
                   data-admin-upload-input>
            <span class="admin-upload-dropzone__body">
              <?= admin_icon('upload', 'admin-upload-dropzone__icon'); ?>
              <span class="admin-upload-dropzone__title"><?= admin_e(t('Træk &amp; slip eksportfil her')); ?></span>
              <span class="admin-upload-dropzone__text"><?= admin_e(t('eller')); ?> <span class="admin-upload-dropzone__browse"><?= admin_e(t('vælg fil')); ?></span></span>
            </span>
            <span class="admin-upload-dropzone__selection" data-admin-upload-selection hidden>
              <span class="admin-upload-dropzone__preview admin-upload-dropzone__preview--json" data-admin-upload-preview aria-hidden="true"></span>
              <span class="admin-upload-dropzone__filemeta">
                <strong data-admin-upload-name></strong>
                <span data-admin-upload-size></span>
              </span>
              <span class="admin-upload-dropzone__change"><?= admin_e(t('Vælg en anden')); ?></span>
            </span>
          </label>
          <div class="admin-upload-dropzone__meta"><span><?= admin_e(t('Boilerplate JSON-eksport')); ?></span><span><?= admin_e(t('Maks. 10 MB')); ?></span></div>
          <p class="admin-field__help"><?= admin_e(t('backup.json_import_only')); ?></p>
          <p class="admin-field__help"><?= admin_e(t('Importen fletter data ind: poster med samme ID opdateres, øvrige poster bevares. Brugere og roller importeres aldrig.')); ?></p>
        </div>
        <div class="admin-tools-warning"><strong><?= admin_e(t('Vigtigt')); ?></strong><span><?= admin_e(t('Billed- og uploadfiler følger ikke med JSON-filen. Kopiér dem separat, hvis eksporten bruges til at flytte sitet til en anden installation.')); ?></span></div>
        <label class="admin-checkbox"><input type="checkbox" name="confirm_import" value="1" required><span><?= admin_e(t('Jeg forstår, at eksisterende poster med samme ID kan blive overskrevet.')); ?></span></label>
        <div class="admin-form-actions admin-form-actions--left"><button class="admin-button" type="submit"><?= admin_e(t('Importer data')); ?></button></div>
      </form>
    </div>
  </section>
