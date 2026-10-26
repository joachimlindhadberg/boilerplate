<?php
$galleryData = is_array($galleryData ?? null) ? $galleryData : [];
$items = is_array($items ?? null) ? $items : [];
$errors = is_array($errors ?? null) ? $errors : [];
$usage = is_array($usage ?? null) ? $usage : ['count' => 0, 'page_ids' => []];
$isEdit = !empty($isEdit);
$galleryId = max(0, (int)($galleryId ?? 0));
$fieldError = static fn(string $key): string => t((string)($errors[$key] ?? ''));
$action = $isEdit ? '/admin/gallerier?screen=edit&amp;id=' . $galleryId : '/admin/gallerier?screen=new';
?>
<div class="admin-detail-back"><a class="admin-back-link" href="/admin/gallerier"><?= admin_icon('arrow-left', 'admin-back-link__icon'); ?><?= admin_e(t('gallery:Tilbage til Gallerier')); ?></a></div>
<div class="admin-page-titlebar">
  <h1><?= $isEdit ? t('gallery:Rediger galleri') : t('gallery:Nyt galleri'); ?></h1>
  <p><?= admin_e(t('gallery:Vælg billeder fra Medier, og træk dem i den rækkefølge de skal vises.')); ?></p>
</div>

<?php if (!empty($notice)): ?><div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div><?php endif; ?>
<?php if ($fieldError('form') !== ''): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$fieldError('form'))); ?></div><?php endif; ?>
<?php if ($fieldError('images') !== ''): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$fieldError('images'))); ?></div><?php endif; ?>

<form method="post" action="<?= $action; ?>" class="admin-form" data-gallery-builder data-admin-track-changes>
  <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $galleryId; ?>"><?php endif; ?>

  <div class="admin-detail-grid">
    <div class="admin-detail-content">
      <section class="admin-panel">
        <div class="admin-panel__header"><h2><?= admin_e(t('gallery:Galleri')); ?></h2></div>
        <div class="admin-form-section">
          <div class="admin-field"><label for="gallery-name"><?= admin_e(t('Navn *')); ?></label><input id="gallery-name" name="name" maxlength="191" value="<?= admin_e((string)($galleryData['name'] ?? '')); ?>" required><?php if($fieldError('name')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('name'))); ?></p><?php endif; ?></div>
          <div class="admin-field"><label for="gallery-description"><?= admin_e(t('Beskrivelse')); ?></label><textarea id="gallery-description" name="description" rows="3" maxlength="2000"><?= admin_e((string)($galleryData['description'] ?? '')); ?></textarea><p class="admin-field__help"><?= admin_e(t('gallery:Kun til intern administration i denne version.')); ?></p></div>
        </div>
      </section>

      <section class="admin-panel">
        <div class="admin-panel__header admin-panel__header--split">
          <div><h2><?= admin_e(t('gallery:Billeder')); ?></h2><p class="admin-field__help"><?= admin_e(t('gallery:Træk billeder for at ændre rækkefølgen.')); ?></p></div>
          <button class="admin-button" type="button" data-gallery-media-open><?= admin_icon('plus', 'admin-button__icon'); ?><?= admin_e(t('gallery:Tilføj billeder')); ?></button>
        </div>
        <div class="admin-form-section">
          <div class="admin-builder-list" data-gallery-items>
            <?php foreach ($items as $item): ?>
              <?php $galleryRow = $item; require __DIR__ . '/gallery-image-row.php'; ?>
            <?php endforeach; ?>
          </div>
          <template data-gallery-item-template>
            <?php $galleryRow = ['media_id' => 0, 'title' => '', 'original_filename' => '', 'public_url' => '']; require __DIR__ . '/gallery-image-row.php'; ?>
          </template>
          <span class="sr-only" role="status" aria-live="polite" data-gallery-sort-status></span>
          <div class="admin-builder-empty" data-gallery-empty <?= $items ? 'hidden' : ''; ?>><?= admin_icon('image','admin-builder-empty__icon'); ?><strong><?= admin_e(t('gallery:Ingen billeder endnu')); ?></strong><span><?= admin_e(t('gallery:Vælg billeder fra Medier for at bygge galleriet.')); ?></span></div>
        </div>
      </section>
    </div>

    <aside class="admin-detail-sidebar">
      <section class="admin-panel"><div class="admin-panel__header"><h2><?= admin_e(t('Publicering')); ?></h2></div><div class="admin-form-section">
        <div class="admin-field"><label for="gallery-status"><?= admin_e(t('Status')); ?></label><select id="gallery-status" name="status"><option value="active" <?= ($galleryData['status']??'active')==='active'?'selected':''; ?>><?= admin_e(t('Aktiv')); ?></option><option value="inactive" <?= ($galleryData['status']??'')==='inactive'?'selected':''; ?>><?= admin_e(t('Inaktiv')); ?></option></select><p class="admin-field__help"><?= admin_e(t('gallery:Kun aktive gallerier kan vælges og vises via Page Builder.')); ?></p></div>
      </div></section>
      <section class="admin-panel"><div class="admin-panel__header"><h2><?= admin_e(t('gallery:Brug på sider')); ?></h2></div><div class="admin-form-section">
        <?php if ($isEdit && ($galleryData['status']??'active') === 'active'): ?><p><?= admin_e(t('gallery:Åbn en side i Page Builder, tilføj')); ?> <strong><?= admin_e(t('gallery:Dynamisk komponent')); ?></strong> <?= admin_e(t('gallery:og vælg:')); ?></p><p><strong>Galleri · <?= admin_e((string)($galleryData['name'] ?? '')); ?></strong></p><p class="admin-field__help">Bruges aktuelt på <?= (int)($usage['count'] ?? 0); ?> <?= (int)($usage['count'] ?? 0) === 1 ? 'side' : 'sider'; ?>.</p><?php elseif ($isEdit): ?><p><?= admin_e(t('gallery:Aktivér galleriet for at gøre det tilgængeligt i Page Builder.')); ?></p><?php else: ?><p><?= admin_e(t('gallery:Gem galleriet først. Derefter bliver det tilgængeligt som dynamisk Page Builder-komponent.')); ?></p><?php endif; ?>
      </div></section>
      <div class="admin-form-actions"><a class="admin-button admin-button--secondary" href="/admin/gallerier"><?= admin_e(t('Annuller')); ?></a><button class="admin-button" type="submit" name="gallery_action" value="save"><?= admin_e(t('gallery:Gem galleri')); ?></button></div>
      <?php if ($isEdit): ?>
        <section class="admin-panel admin-danger-zone">
          <div class="admin-panel__header"><h2><?= admin_e(t('Slet')); ?></h2></div>
          <div class="admin-form-section">
            <p><?= admin_e(t('gallery:Galleriet kan kun slettes, hvis det ikke længere bruges på en side. Billederne i Medier bliver ikke slettet.')); ?></p>
            <button class="admin-button admin-button--danger" type="button" data-gallery-delete-open><?= admin_e(t('gallery:Slet galleri')); ?></button>
          </div>
        </section>
      <?php endif; ?>
    </aside>
  </div>
</form>

<?php require __DIR__ . '/media-picker-dialog.php'; ?><?php require __DIR__ . '/delete-dialog.php'; ?><?php require __DIR__ . '/gallery-builder-script.php'; ?>