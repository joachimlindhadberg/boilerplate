<?php
/** Core Builder media-row presentation shared by saved items and the JS template. */
$galleryRow = is_array($galleryRow ?? null) ? $galleryRow : [];
$galleryMediaId = max(0, (int)($galleryRow['media_id'] ?? $galleryRow['id'] ?? 0));
$galleryTitle = (string)(($galleryRow['title'] ?? '') !== '' ? $galleryRow['title'] : ($galleryRow['original_filename'] ?? t('Billede')));
?>
<article class="admin-builder-block admin-builder-block--media" data-gallery-item data-media-id="<?= $galleryMediaId; ?>">
  <input type="hidden" name="media_ids[]" value="<?= $galleryMediaId; ?>">
  <header class="admin-builder-block__header">
    <button class="admin-builder-drag" type="button" draggable="true" data-gallery-drag aria-label="<?= admin_e(t('gallery:gallery_move_keyboard')); ?>" title="<?= admin_e(t('gallery:gallery_move_keyboard')); ?>"><?= admin_icon('grip', 'admin-builder-drag__icon'); ?></button>
    <div class="admin-builder-block__preview"><img src="<?= admin_e(ImageVariants::preferred((string)($galleryRow['public_url'] ?? $galleryRow['file_path'] ?? ''), 'thumbnail')); ?>" alt="" loading="lazy" draggable="false"></div>
    <div class="admin-builder-block__title"><strong><?= admin_e($galleryTitle); ?></strong><span><?= admin_e((string)($galleryRow['original_filename'] ?? '')); ?></span></div>
    <div class="admin-builder-block__actions">
      <?= admin_icon_action_button('delete', t('gallery:gallery_remove_image') . ': ' . $galleryTitle, ['data-gallery-remove' => ''], 'danger'); ?>
    </div>
  </header>
</article>
