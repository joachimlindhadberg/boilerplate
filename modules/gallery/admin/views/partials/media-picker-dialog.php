<dialog class="admin-modal admin-modal--lg" data-admin-modal data-gallery-media-dialog aria-labelledby="gallery-media-title">
  <div class="admin-modal__surface admin-modal__surface--clip">
    <div class="admin-modal__header"><div><h2 id="gallery-media-title"><?= admin_e(t('gallery:Tilføj billeder')); ?></h2><p><?= admin_e(t('gallery:Vælg ét eller flere billeder fra Medier.')); ?></p></div><button type="button" class="admin-modal__close" data-modal-close aria-label="<?= admin_e(t('Luk')); ?>">×</button></div>
    <div class="admin-media-picker-toolbar"><label><span class="sr-only"><?= admin_e(t('Søg billeder')); ?></span><?= admin_icon('search','admin-list-search__icon'); ?><input type="search" placeholder="<?= admin_e(t('Søg billeder…')); ?>" autocomplete="off" data-gallery-media-search></label></div>
    <div class="admin-modal__body"><div class="admin-media-picker-grid" data-gallery-media-grid></div><div class="admin-empty-state" data-gallery-media-empty hidden><?= admin_icon('image','admin-empty-state__icon'); ?><strong><?= admin_e(t('Ingen billeder fundet')); ?></strong><span><?= admin_e(t('Upload billeder under Medier eller prøv en anden søgning.')); ?></span></div><div class="admin-media-picker-loading" data-gallery-media-loading><?= admin_e(t('Indlæser billeder…')); ?></div></div>
    <div class="admin-modal__footer"><button type="button" class="admin-button admin-button--secondary" data-modal-close><?= admin_e(t('Annuller')); ?></button><button type="button" class="admin-button" data-gallery-media-add><?= admin_icon('plus', 'admin-button__icon'); ?><?= admin_e(t('gallery:Tilføj valgte')); ?> <span data-gallery-media-count></span></button></div>
  </div>
</dialog>

