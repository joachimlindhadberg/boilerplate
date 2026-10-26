<?php
$data = is_array($mediaData ?? null) ? $mediaData : [];
$items = is_array($data['items'] ?? null) ? $data['items'] : [];
$counts = is_array($data['counts'] ?? null) ? $data['counts'] : ['all'=>0, 'image'=>0, 'video'=>0];
$search = (string)($data['search'] ?? '');
$kind = (string)($data['kind'] ?? '');
$page = max(1, (int)($data['page'] ?? 1));
$pages = max(1, (int)($data['pages'] ?? 1));
$total = max(0, (int)($data['total'] ?? 0));
$from = max(0, (int)($data['from'] ?? 0));
$to = max(0, (int)($data['to'] ?? 0));
$appUploadMax = MediaLibrary::maxUploadBytes();
$phpUploadMax = admin_ini_size_bytes(ini_get('upload_max_filesize'));
$phpPostMax = admin_ini_size_bytes(ini_get('post_max_size'));
$effectiveFileMax = $appUploadMax;
if ($phpUploadMax > 0) {
    $effectiveFileMax = min($effectiveFileMax, $phpUploadMax);
}
// Leave a little multipart/form-data overhead below post_max_size.
$effectiveTotalMax = $phpPostMax > 0 ? max(1, $phpPostMax - (1024 * 1024)) : 0;
$effectiveFileMaxLabel = admin_format_bytes($effectiveFileMax);
ob_start();
?>
<div class="admin-page-titlebar admin-page-titlebar--split">
  <div><h1><?= admin_e(t('Medier')); ?></h1><p><?= admin_e(t('Upload og administrér billeder og videoer, som kan genbruges på tværs af hjemmesiden.')); ?></p></div>
  <button class="admin-button" type="button" data-media-upload-open><?= admin_icon('upload', 'admin-button__icon'); ?><?= admin_e(t('Upload medier')); ?></button>
</div>

<?php if (!empty($notice)): ?><div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div><?php endif; ?>

<section class="admin-panel" aria-labelledby="media-library-title" data-admin-live-list>
  <div class="admin-list-toolbar admin-media-toolbar">
    <?= AdminListUI::filterTabs([
      ['value' => '', 'label' => t('Alle'), 'count' => (int)($counts['all'] ?? 0), 'url' => admin_media_url(['type' => '', 'page' => null])],
      ['value' => 'image', 'label' => t('Billeder'), 'count' => (int)($counts['image'] ?? 0), 'url' => admin_media_url(['type' => 'image', 'page' => null])],
      ['value' => 'video', 'label' => t('Videoer'), 'count' => (int)($counts['video'] ?? 0), 'url' => admin_media_url(['type' => 'video', 'page' => null])],
    ], $kind, t('ui.filter_media_type')); ?>
    <form class="admin-list-search" method="get" action="/admin/medier" role="search" data-admin-live-search>
      <?php if ($kind !== ''): ?><input type="hidden" name="type" value="<?= admin_e($kind); ?>"><?php endif; ?>
      <label><span class="sr-only"><?= admin_e(t('Søg medier')); ?></span><?= admin_icon('search','admin-list-search__icon'); ?><input type="search" name="q" value="<?= admin_e($search); ?>" placeholder="<?= admin_e(t('Søg filnavn, titel eller alt-tekst…')); ?>" autocomplete="off"></label>
    
      <noscript><button class="admin-button admin-button--compact" type="submit"><?= admin_e(t('Filtrer')); ?></button></noscript>
    </form>
  </div>

  <div class="admin-list-summary"><h2 id="media-library-title"><?= admin_e(t('Mediebibliotek')); ?></h2><span><?= $total > 0 ? admin_e(t('common.showing', ['from' => $from, 'to' => $to, 'total' => $total])) : t('common.zero_media'); ?></span></div>

  <?php if ($items): ?>
    <div class="admin-media-grid">
      <?php foreach ($items as $item):
        $isImage = (($item['media_kind'] ?? '') === 'image');
        $title = trim((string)($item['title'] ?? '')) ?: (string)($item['original_filename'] ?? 'Medie');
        $dimensions = ($isImage && !empty($item['width']) && !empty($item['height'])) ? ((int)$item['width'] . ' × ' . (int)$item['height']) : '';
        $meta = trim(admin_media_kind_label((string)($item['media_kind'] ?? 'image')) . ($dimensions !== '' ? ' · ' . $dimensions : '') . ' · ' . admin_format_bytes((int)($item['file_size'] ?? 0)));
      ?>
        <button class="admin-media-card" type="button"
          data-media-item
          data-media-id="<?= (int)$item['id']; ?>"
          data-media-kind="<?= admin_e((string)$item['media_kind']); ?>"
          data-media-url="<?= admin_e((string)($item['public_url'] ?? $item['file_path'])); ?>"
          data-media-title="<?= admin_e((string)($item['title'] ?? '')); ?>"
          data-media-alt="<?= admin_e((string)($item['alt_text'] ?? '')); ?>"
          data-media-description="<?= admin_e((string)($item['description'] ?? '')); ?>"
          data-media-filename="<?= admin_e((string)$item['original_filename']); ?>"
          data-media-meta="<?= admin_e($meta); ?>"
          data-media-created="<?= !empty($item['created_at']) ? admin_e(date('d.m.Y H:i', strtotime((string)$item['created_at']))) : ''; ?>"
          data-media-uploader="<?= admin_e(trim((string)($item['uploader_name'] ?? ''))); ?>">
          <span class="admin-media-card__preview">
            <?php if ($isImage): ?>
              <img src="<?= admin_e(ImageVariants::preferred((string)($item['public_url'] ?? $item['file_path']), 'thumbnail')); ?>" alt="" loading="lazy">
            <?php else: ?>
              <span class="admin-media-card__video" aria-hidden="true">▶</span>
            <?php endif; ?>
          </span>
          <span class="admin-media-card__body"><strong><?= admin_e($title); ?></strong><small><?= admin_e($meta); ?></small></span>
        </button>
      <?php endforeach; ?>
    </div>
    <?php admin_render_pagination($page, $pages, static fn(int $targetPage): string => admin_media_url(['page'=>$targetPage]), 'Medier'); ?>
  <?php else: ?>
    <div class="admin-empty-state admin-empty-state--large"><?= admin_icon('image','admin-empty-state__icon'); ?><strong><?= admin_e(t('Ingen medier fundet')); ?></strong><span><?= $search !== '' || $kind !== '' ? t('ui.media_empty_search') : t('ui.media_empty_first'); ?></span></div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/upload-dialog.php'; ?>

<?php require __DIR__ . '/partials/detail-dialog.php'; ?>
<?php require __DIR__ . '/partials/delete-dialog.php'; ?>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'Medier';
require __DIR__ . '/../partials/layout.php';
