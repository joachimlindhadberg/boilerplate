<dialog class="admin-modal admin-modal--md" data-admin-modal data-media-upload-dialog aria-labelledby="media-upload-title">
  <form class="admin-modal__surface" method="post" action="/admin/medier?mode=upload" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
    <div class="admin-modal__header"><div><h2 id="media-upload-title"><?= admin_e(t('Upload medier')); ?></h2><p>JPEG, PNG, WebP, GIF, MP4, WebM eller MOV. Maks. <?= admin_e($effectiveFileMaxLabel); ?> pr. fil.</p></div><button type="button" class="admin-modal__close" data-modal-close aria-label="<?= admin_e(t('Luk')); ?>">×</button></div>
    <div class="admin-modal__body">
      <label class="admin-upload-dropzone" data-admin-upload-dropzone data-upload-types="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime" data-upload-extensions=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.mov" data-upload-type-error="<?= admin_e(t('ui.upload_supported_media')); ?>" data-upload-max-bytes="<?= (int)$effectiveFileMax; ?>" data-upload-total-max-bytes="<?= (int)$effectiveTotalMax; ?>" data-upload-size-error="<?= admin_e(t('ui.upload_each_max', ['size' => $effectiveFileMaxLabel])); ?>" data-upload-total-size-error="<?= admin_e(t('ui.upload_total_max')); ?>">
        <input class="admin-upload-dropzone__input" type="file" name="media_files[]" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime" multiple required data-admin-upload-input>
        <span class="admin-upload-dropzone__body"><?= admin_icon('upload','admin-upload-dropzone__icon'); ?><span class="admin-upload-dropzone__title"><?= admin_e(t('Træk &amp; slip medier her')); ?></span><span class="admin-upload-dropzone__text"><?= admin_e(t('eller')); ?> <span class="admin-upload-dropzone__browse"><?= admin_e(t('vælg filer')); ?></span></span></span>
        <span class="admin-upload-dropzone__selection" data-admin-upload-selection hidden><span class="admin-upload-dropzone__filemeta"><strong data-admin-upload-name></strong><span data-admin-upload-size></span></span><span class="admin-upload-dropzone__change"><?= admin_e(t('Vælg andre')); ?></span></span>
      </label>
    </div>
    <div class="admin-modal__footer"><button class="admin-button admin-button--secondary" type="button" data-modal-close><?= admin_e(t('Annuller')); ?></button><button class="admin-button" type="submit"><?= admin_icon('upload', 'admin-button__icon'); ?><?= admin_e(t('Upload')); ?></button></div>
  </form>
</dialog>
