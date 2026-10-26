<div class="admin-page-titlebar admin-page-titlebar--split">
  <div><h1><?= $mode === 'list' ? t('Navigation') : ($mode === 'new' ? t('Opret menu') : ($mode === 'delete' ? t('Slet menu') : t('Rediger menu'))); ?></h1><p><?= $mode === 'list' ? t('nav.overview_subtitle') : ($mode === 'edit' && $selectedMenu ? admin_e((string)$selectedMenu['name']) : t('nav.manage_subtitle')); ?></p></div>
  <?php if ($mode === 'list'): ?><a class="admin-button" href="/admin/navigation?mode=new"><?= admin_icon('plus', 'admin-button__icon'); ?><?= admin_e(t('Opret menu')); ?></a><?php else: ?><a class="admin-button admin-button--secondary" href="/admin/navigation"><?= admin_icon('arrow-left', 'admin-button__icon'); ?><?= admin_e(t('Tilbage til oversigten')); ?></a><?php endif; ?>
</div>
<?php if (!empty($notice)): ?><div class="admin-notice admin-notice--<?= admin_e($notice['type'] ?? 'success'); ?>" role="status"><?= admin_e($notice['message'] ?? ''); ?></div><?php endif; ?>
<?php if (!empty($errors['form'])): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$errors['form'])); ?></div><?php endif; ?>
