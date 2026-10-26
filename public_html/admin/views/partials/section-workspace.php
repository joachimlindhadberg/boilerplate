<?php
/**
 * Reusable secondary navigation layout. Everything stays inside #admin-main.
 * $sectionWorkspace is provided by the active module; CMS Core owns markup
 * and all presentation styles. Never output a second <main> element.
 */
$sectionWorkspace = is_array($sectionWorkspace ?? null) ? $sectionWorkspace : [];
$sectionHeading = (string)($sectionWorkspace['heading'] ?? '');
$sectionDescription = (string)($sectionWorkspace['description'] ?? '');
$sectionActive = (string)($sectionWorkspace['active'] ?? '');
$sectionItems = is_array($sectionWorkspace['items'] ?? null) ? $sectionWorkspace['items'] : [];
$sectionAction = is_array($sectionWorkspace['action'] ?? null) ? $sectionWorkspace['action'] : [];
// Core-owned variants: existing side navigation remains the default for other modules.
// Inline navigation or no navigation makes the module workspace full-width.
$sectionNavigationMode = (string)($sectionWorkspace['navigationMode'] ?? 'sidebar');
if (!in_array($sectionNavigationMode, ['sidebar', 'inline', 'none'], true)) {
    $sectionNavigationMode = 'sidebar';
}
?>
<div class="admin-page-titlebar<?= $sectionAction ? ' admin-page-titlebar--split' : ''; ?>">
  <div><h1><?= admin_e(t($sectionHeading)); ?></h1><?php if ($sectionDescription !== ''): ?><p><?= admin_e(t($sectionDescription)); ?></p><?php endif; ?></div>
  <?php if (!empty($sectionAction['href']) && !empty($sectionAction['label'])): ?>
    <a class="admin-button" href="<?= admin_e((string)$sectionAction['href']); ?>"><?= ($sectionAction['kind'] ?? '') === 'create' ? admin_icon('plus', 'admin-button__icon') : ''; ?><?= admin_e(t((string)$sectionAction['label'])); ?></a>
  <?php endif; ?>
</div>
<div class="admin-section-layout<?= $sectionNavigationMode !== 'sidebar' ? ' admin-section-layout--full' : ''; ?>">
  <?php if ($sectionNavigationMode !== 'none'): ?>
  <?php if ($sectionNavigationMode === 'sidebar'): ?>
  <aside class="admin-section-layout__sidebar" aria-label="<?= admin_e(t((string)($sectionWorkspace['label'] ?? 'Section navigation'))); ?>">
  <?php endif; ?>
    <nav class="admin-section-nav<?= $sectionNavigationMode === 'inline' ? ' admin-section-nav--inline' : ''; ?>" aria-label="<?= admin_e(t((string)($sectionWorkspace['label'] ?? 'Section navigation'))); ?>">
      <?php foreach ($sectionItems as $item):
        if (!is_array($item) || empty($item['href']) || empty($item['label'])) continue;
        $current = (string)($item['id'] ?? '') === $sectionActive;
      ?>
        <a href="<?= admin_e((string)$item['href']); ?>" class="admin-section-nav__link<?= $current ? ' is-active' : ''; ?>"<?= $current ? ' aria-current="page"' : ''; ?>><?= admin_e(t((string)$item['label'])); ?></a>
      <?php endforeach; ?>
    </nav>
  <?php if ($sectionNavigationMode === 'sidebar'): ?>
  </aside>
  <?php endif; ?>
  <?php endif; ?>
  <div class="admin-section-layout__content"><?= $bodyHtml; ?></div>
</div>
