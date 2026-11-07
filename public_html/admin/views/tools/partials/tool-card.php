<?php
/** Shared Core Tools card presentation; data is defined by the trusted Tools view. */
$toolLabel = t((string)$toolCard['title']);
?>
<section class="admin-panel admin-tools-card" aria-label="<?= admin_e($toolLabel); ?>">
  <div class="admin-tools-card__heading">
    <span class="admin-tools-card__icon" aria-hidden="true"><?= admin_icon((string)$toolCard['icon']); ?></span>
    <div><h2><?= admin_e($toolLabel); ?></h2><p><?= admin_e(t((string)$toolCard['description'])); ?></p></div>
  </div>
  <div class="admin-tools-card__footer<?= $toolCard['title'] === 'updates.title' ? '' : ' admin-tools-card__footer--action'; ?>">
    <?php if ($toolCard['title'] === 'updates.title'): ?>
      <div class="admin-tools-card__status" data-core-update-card-status hidden role="status">
        <span class="admin-health-indicator admin-health-indicator--warning" aria-hidden="true"></span>
        <span data-core-update-card-text></span>
      </div>
    <?php endif; ?>
    <a class="admin-button admin-button--secondary" href="<?= admin_e((string)$toolCard['href']); ?>"><?= admin_e(t((string)$toolCard['action'])); ?></a>
  </div>
</section>
