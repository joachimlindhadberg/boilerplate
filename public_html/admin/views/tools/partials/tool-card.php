<?php
/** Shared Core Tools card presentation; data is defined by the trusted Tools view. */
$toolLabel = t((string)$toolCard['title']);
?>
<section class="admin-panel admin-tools-card" aria-label="<?= admin_e($toolLabel); ?>">
  <div class="admin-tools-card__heading">
    <span class="admin-tools-card__icon" aria-hidden="true"><?= admin_icon((string)$toolCard['icon']); ?></span>
    <div><h2><?= admin_e($toolLabel); ?></h2><p><?= admin_e(t((string)$toolCard['description'])); ?></p></div>
  </div>
  <div class="admin-tools-card__footer admin-tools-card__footer--action">
    <a class="admin-button admin-button--secondary" href="<?= admin_e((string)$toolCard['href']); ?>"><?= admin_e(t((string)$toolCard['action'])); ?></a>
  </div>
</section>
