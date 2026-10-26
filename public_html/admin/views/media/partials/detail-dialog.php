<dialog class="admin-modal admin-modal--xl" data-admin-modal data-media-detail-dialog aria-labelledby="media-detail-title">
  <div class="admin-modal__surface admin-modal__surface--clip">
    <div class="admin-modal__header"><div><h2 id="media-detail-title" data-media-detail-heading><?= admin_e(t('Medie')); ?></h2><p data-media-detail-meta></p></div><button type="button" class="admin-modal__close" data-modal-close aria-label="<?= admin_e(t('Luk')); ?>">×</button></div>
    <div class="admin-modal__body admin-modal__body--flush">
      <div class="admin-media-detail">
        <div class="admin-media-detail__preview" data-media-detail-preview></div>
        <div class="admin-media-detail__form">
        <form id="media-detail-update-form" method="post" action="/admin/medier?mode=update">
          <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>"><input type="hidden" name="id" value="" data-media-detail-id>
          <div class="admin-field"><label><?= admin_e(t('Filnavn')); ?></label><input type="text" value="" readonly data-media-detail-filename></div>
          <div class="admin-field"><label for="media-title"><?= admin_e(t('Titel')); ?></label><input id="media-title" name="title" maxlength="255" data-media-detail-title></div>
          <div class="admin-field" data-media-alt-field><label for="media-alt"><?= admin_e(t('Alt-tekst')); ?></label><input id="media-alt" name="alt_text" maxlength="255" data-media-detail-alt><p class="admin-field__help"><?= admin_e(t('Beskriv billedets indhold kort og præcist for tilgængelighed.')); ?></p></div>
          <div class="admin-field"><label for="media-description"><?= admin_e(t('Beskrivelse')); ?></label><textarea id="media-description" name="description" rows="4" data-media-detail-description></textarea></div>
          <div class="admin-media-detail__info"><span><?= admin_e(t('Uploadet')); ?> <strong data-media-detail-created>—</strong></span><span data-media-detail-uploader-row><?= admin_e(t('af')); ?> <strong data-media-detail-uploader>—</strong></span></div>
        </form>
        </div>
      </div>
    </div>
    <div class="admin-modal__footer admin-modal__footer--split">
      <button class="admin-button admin-button--danger-outline" type="button" data-admin-open-delete="media-delete-dialog"><?= admin_e(t('Slet permanent')); ?></button>
      <button class="admin-button" type="submit" form="media-detail-update-form"><?= admin_e(t('Gem oplysninger')); ?></button>
    </div>
  </div>
</dialog>
