<?php
$health = is_array($health ?? null) ? $health : ['counts'=>[], 'overall'=>'warning'];
ob_start();
?>
<div class="admin-page-titlebar">
  <h1><?= admin_e(t('Værktøjer')); ?></h1>
  <p><?= admin_e(t('tools.overview_description')); ?></p>
</div>
<?php if (!empty($notice)): ?><div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div><?php endif; ?>
<div class="admin-tools-grid admin-tools-grid--overview">
  <?php require __DIR__ . '/partials/health.php'; ?>
  <?php
    $toolCards = [
      ['icon'=>'arrow-left-right','title'=>'Redirects','description'=>'tools.redirects_description','href'=>'/admin/tools/redirects','action'=>'tools.manage'],
      ['icon'=>'history','title'=>'activity.title','description'=>'activity.tools_description','href'=>'/admin/tools/activity','action'=>'activity.view_all'],
      ['icon'=>'refresh-cw','title'=>'updates.title','description'=>'tools.updates_description','href'=>'/admin/tools/update','action'=>'updates.open'],
      ['icon'=>'database','title'=>'tools.data_title','description'=>'tools.data_description','href'=>'/admin/tools/data','action'=>'tools.manage_data'],
    ];
    foreach ($toolCards as $toolCard) require __DIR__ . '/partials/tool-card.php';
  ?>
</div>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'Værktøjer';
require __DIR__ . '/../partials/layout.php';
