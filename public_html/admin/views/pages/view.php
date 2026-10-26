<?php
/** V2.4.4z2 — read-only Pages detail overview; editing/destructive actions live elsewhere. */
$pageRecord = is_array($pageRecord ?? null) ? $pageRecord : [];
$id = (int)($pageRecord['id'] ?? 0);
$isSystem = Pages::isSystemSlug((string)($pageRecord['page_slug'] ?? ''));
$status = (string)($pageRecord['page_status'] ?? 'draft');
$contentMode = (($pageRecord['content_mode'] ?? 'legacy') === 'builder') ? 'builder' : 'legacy';
$blocks = is_array($blocks ?? null) ? $blocks : [];
$url = admin_page_public_url($pageRecord);
$title = (string)($pageRecord['page_title'] ?? t('Side'));
$description = trim((string)($pageRecord['page_description'] ?? ''));
$metaTitle = trim((string)($pageRecord['meta_title'] ?? ''));
$metaDescription = trim((string)($pageRecord['meta_description'] ?? ''));
$revisionCount = max(0, (int)($revisionCount ?? 0));
$hiddenCount = count(array_filter($blocks, static fn($block): bool => is_array($block) && ($block['status'] ?? 'active') === 'hidden'));
$createdTimestamp = !empty($pageRecord['created']) ? strtotime((string)$pageRecord['created']) : false;
$updatedTimestamp = !empty($pageRecord['updated']) ? strtotime((string)$pageRecord['updated']) : false;
$creatorName = trim((string)($pageRecord['created_by_name'] ?? ''));
$editorName = trim((string)($pageRecord['updated_by_name'] ?? ''));
$publishLabel = PageScheduler::localLabel($schedule['publish_at_utc'] ?? null);
$unpublishLabel = PageScheduler::localLabel($schedule['unpublish_at_utc'] ?? null);
$hasSchedule = !empty($schedule['publish_at_utc']) || !empty($schedule['unpublish_at_utc']);
ob_start();
?>
<div class="admin-detail-back"><a class="admin-back-link" href="/admin/sider"><?= admin_icon('arrow-left', 'admin-back-link__icon'); ?><?= admin_e(t('Tilbage til sider')); ?></a></div>
<header class="admin-page-overview-heading">
  <div class="admin-page-overview-heading__main">
    <div class="admin-page-overview-heading__title">
      <h1><?= admin_e($title); ?></h1>
      <span class="admin-status-label admin-status-label--<?= admin_e(admin_page_status_class($status)); ?>"><?= admin_e(admin_page_status_label($status)); ?></span>
      <?php if ($isSystem): ?><span class="admin-page-overview-heading__system"><?= admin_e(t('pages.system_page')); ?></span><?php endif; ?>
    </div>
    <a class="admin-page-overview-heading__url" href="<?= admin_e($url); ?>" target="_blank" rel="noopener noreferrer"><?= admin_icon('external', 'admin-page-overview-heading__url-icon'); ?><span><?= admin_e($url); ?></span></a>
  </div>
  <div class="admin-page-actions">
    <a class="admin-button admin-button--secondary" href="<?= admin_e($url); ?>" target="_blank" rel="noopener noreferrer"><?= admin_icon('external', 'admin-button__icon'); ?><?= admin_e(t('Se side')); ?></a>
    <a class="admin-button" href="/admin/sider?mode=edit&amp;id=<?= $id; ?>"><?= admin_icon('edit', 'admin-button__icon'); ?><?= admin_e(t('Rediger')); ?></a>
  </div>
</header>
<?php if (!empty($notice)): ?><div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div><?php endif; ?>
<?php if ($description !== ''): ?><p class="admin-page-overview-description"><?= nl2br(admin_e($description)); ?></p><?php endif; ?>
<div class="admin-detail-grid admin-page-overview">
  <div class="admin-detail-content">
    <section class="admin-panel" aria-labelledby="page-structure-title">
      <div class="admin-panel__header admin-page-overview-panel-heading">
        <div><h2 id="page-structure-title"><?= admin_e(t('pages_detail.structure')); ?></h2><p><?= $contentMode === 'builder' ? admin_e(t('pages_detail.section_count', ['count' => count($blocks)])) . ($hiddenCount ? ' · ' . admin_e(t('pages_detail.hidden_count', ['count' => $hiddenCount])) : '') : admin_e(t('Legacy HTML')); ?></p></div>
        <span class="admin-detail-value-tag"><?= admin_e($contentMode === 'builder' ? t('Page Builder') : t('Legacy HTML')); ?></span>
      </div>
      <?php if ($contentMode === 'builder'): ?>
        <?php if ($blocks): ?>
          <ol class="admin-page-overview-sections">
          <?php foreach ($blocks as $position => $block):
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $type = (string)($block['block_type'] ?? '');
            $def = PageBlocks::definitions()[$type] ?? ['label' => $type];
            $label = (string)($def['label'] ?? $type);
            $summary = admin_page_builder_summary($type, $data);
            if ($type === 'grid' && ($data['layout_version'] ?? '') === 'v2') {
                $count = max(1, min(4, (int)($data['column_count'] ?? 1)));
                $summary = t('pages_detail.row_columns', ['count' => $count]);
            }
            $hidden = ($block['status'] ?? 'active') === 'hidden';
            $icon = match ($type) { 'grid' => 'cube', 'image' => 'image', 'component' => 'settings', default => 'file' };
          ?>
            <li class="admin-page-overview-section<?= $hidden ? ' is-hidden' : ''; ?>">
              <span class="admin-page-overview-section__number"><?= str_pad((string)($position + 1), 2, '0', STR_PAD_LEFT); ?></span>
              <?= admin_icon($icon, 'admin-page-overview-section__icon'); ?>
              <span class="admin-page-overview-section__content"><strong><?= admin_e($label); ?></strong><span><?= admin_e($summary); ?></span></span>
              <?php if ($hidden): ?><span class="admin-page-overview-section__hidden"><?= admin_e(t('Skjult')); ?></span><?php endif; ?>
            </li>
          <?php endforeach; ?>
          </ol>
        <?php else: ?><div class="admin-empty-state"><strong><?= admin_e(t('Ingen sektioner')); ?></strong><span><?= admin_e(t('Rediger siden for at tilføje Page Builder-sektioner.')); ?></span></div><?php endif; ?>
      <?php else: ?>
        <div class="admin-page-overview-legacy">
          <p><?= admin_e(t('pages_detail.legacy_description')); ?></p>
          <details class="admin-page-overview-source">
            <summary><?= admin_e(t('pages_detail.show_html')); ?><span><?= number_format(strlen((string)($pageRecord['page_content'] ?? '')), 0, ',', '.'); ?> <?= admin_e(t('pages_detail.characters')); ?></span><?= admin_icon('chevron-down', 'admin-page-overview-source__chevron'); ?></summary>
            <pre class="admin-code-preview"><?= admin_e((string)($pageRecord['page_content'] ?? '')); ?></pre>
          </details>
        </div>
      <?php endif; ?>
    </section>

    <section class="admin-panel" aria-labelledby="page-seo-title">
      <div class="admin-panel__header admin-page-overview-panel-heading"><div><h2 id="page-seo-title"><?= admin_e(t('SEO')); ?></h2><p><?= admin_e(t('pages_detail.seo_preview_help')); ?></p></div></div>
      <div class="admin-page-overview-seo">
        <div class="admin-page-overview-seo__url"><?= admin_e($url); ?></div>
        <?php if ($metaTitle !== ''): ?>
          <div class="admin-page-overview-seo__title"><?= admin_e($metaTitle); ?></div>
        <?php else: ?>
          <div class="admin-page-overview-seo__missing"><?= admin_e(t('pages_detail.seo_title_missing')); ?></div>
        <?php endif; ?>
        <?php if ($metaDescription !== ''): ?>
          <p class="admin-page-overview-seo__description"><?= admin_e($metaDescription); ?></p>
        <?php else: ?>
          <p class="admin-page-overview-seo__missing"><?= admin_e(t('pages_detail.seo_description_missing')); ?></p>
        <?php endif; ?>
      </div>
    </section>
  </div>
  <aside class="admin-detail-sidebar" aria-label="<?= admin_e(t('pages_detail.sidebar')); ?>">
    <section class="admin-panel" aria-labelledby="page-information-title">
      <div class="admin-panel__header"><h2 id="page-information-title"><?= admin_e(t('pages_detail.information')); ?></h2></div>
      <dl class="admin-page-overview-info">
        <div><dt><?= admin_e(t('Editor')); ?></dt><dd><?= admin_e($contentMode === 'builder' ? t('Page Builder') : t('Legacy HTML')); ?></dd></div>
        <div><dt><?= admin_e(t('Overordnet')); ?></dt><dd><?= !empty($pageRecord['parent_title']) ? admin_e((string)$pageRecord['parent_title']) : admin_e(t('Ingen')); ?></dd></div>
        <div><dt><?= admin_e(t('Undersider')); ?></dt><dd><?= (int)($childCount ?? 0); ?></dd></div>
        <div class="admin-page-overview-info__audit">
          <div><dt><?= admin_e(t('Oprettet')); ?></dt><dd><?= $createdTimestamp !== false ? admin_e(date('d.m.Y – H:i', $createdTimestamp)) : '—'; ?></dd></div>
          <div><dt><?= admin_e(t('pages_detail.created_by')); ?></dt><dd><?= admin_e($creatorName !== '' ? $creatorName : t('Ukendt')); ?></dd></div>
          <div><dt><?= admin_e(t('Opdateret')); ?></dt><dd><?= $updatedTimestamp !== false ? admin_e(date('d.m.Y – H:i', $updatedTimestamp)) : '—'; ?></dd></div>
          <div><dt><?= admin_e(t('pages_detail.updated_by')); ?></dt><dd><?= admin_e($editorName !== '' ? $editorName : t('Ukendt')); ?></dd></div>
        </div>
        <div><dt><?= admin_e(t('pages_detail.schedule')); ?></dt><dd>
          <?php if ($hasSchedule): ?>
            <?php if (!empty($schedule['publish_at_utc'])): ?><span class="admin-page-overview-info__schedule"><?= admin_e(t('Planlagt publicering')); ?>: <?= admin_e($publishLabel); ?></span><?php endif; ?>
            <?php if (!empty($schedule['unpublish_at_utc'])): ?><span class="admin-page-overview-info__schedule"><?= admin_e(t('Planlagt afpublicering')); ?>: <?= admin_e($unpublishLabel); ?></span><?php endif; ?>
          <?php else: ?><?= admin_e(t('pages_detail.no_schedule')); ?><?php endif; ?>
        </dd></div>
        <div><dt><?= admin_e(t('Navigation')); ?></dt><dd><a href="/admin/navigation"><?= admin_e(t('Administrer menuer')); ?></a></dd></div>
      </dl>
    </section>
    <section class="admin-panel" aria-labelledby="page-revisions-title">
      <div class="admin-panel__header"><h2 id="page-revisions-title"><?= admin_e(t('Versionshistorik')); ?></h2></div>
      <div class="admin-page-overview-revisions">
        <p><?= admin_e(t('pages_detail.revision_count', ['count' => $revisionCount])); ?></p>
        <a href="/admin/sider?mode=history&amp;id=<?= $id; ?>"><?= admin_e(t('pages_detail.view_revisions')); ?> <?= admin_icon('chevron', 'admin-page-overview-revisions__chevron'); ?></a>
      </div>
    </section>
  </aside>
</div>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = $title;
require __DIR__ . '/../partials/layout.php';
