<?php
$pageRecord = is_array($pageRecord ?? null) ? $pageRecord : [];
$revision = is_array($revision ?? null) ? $revision : [];
$current = is_array($currentSnapshot ?? null) ? $currentSnapshot : [];
$historic = is_array($revision['data'] ?? null) ? $revision['data'] : [];
$old = is_array($historic['page'] ?? null) ? $historic['page'] : [];
$now = is_array($current['page'] ?? null) ? $current['page'] : [];
$oldBlocks = is_array($historic['blocks'] ?? null) ? $historic['blocks'] : [];
$nowBlocks = is_array($current['blocks'] ?? null) ? $current['blocks'] : [];
$id = (int)($pageRecord['id'] ?? 0);
$revisionId = (int)($revision['id'] ?? 0);
$number = (int)($revision['version_number'] ?? 0);
$fields = [
  'page_title'=>'Sidetitel', 'page_slug'=>'Slug', 'parent_id'=>'Overordnet side (ID)',
  'page_description'=>'Beskrivelse', 'content_mode'=>'Editor',
  'page_status'=>'Status', 'menu_order'=>'Sortering',
  'meta_title'=>'SEO-titel', 'meta_description'=>'SEO-beskrivelse',
];
$displayValue = static function (mixed $value): string {
    if ($value === null || $value === '') return '—';
    return (string)$value;
};
$longDiff = static function (string $heading, string $before, string $after) use ($number): void {
    ?>
    <section class="admin-panel"><div class="admin-panel__header"><h2><?= admin_e($heading); ?> <?= $before === $after ? '' : '· ' . t('Ændret'); ?></h2></div>
    <div class="admin-revision-compare">
      <div><strong>Version <?= $number; ?></strong><pre class="admin-revision-pre"><?= admin_e($before !== '' ? $before : '—'); ?></pre></div>
      <div><strong><?= admin_e(t('Nuværende')); ?></strong><pre class="admin-revision-pre"><?= admin_e($after !== '' ? $after : '—'); ?></pre></div>
    </div></section>
    <?php
};
$oldBlocksJson = json_encode($oldBlocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
$nowBlocksJson = json_encode($nowBlocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
$changed = ($old !== $now || $oldBlocks !== $nowBlocks);
ob_start();
?>
<div class="admin-detail-back"><a class="admin-back-link" href="/admin/sider?mode=history&amp;id=<?= $id; ?>"><?= admin_icon('arrow-left', 'admin-back-link__icon'); ?><?= admin_e(t('Tilbage til versionshistorik')); ?></a></div>
<div class="admin-page-titlebar admin-page-titlebar--split">
  <div><h1>Version <?= $number; ?></h1><p><?= admin_e((string)($pageRecord['page_title'] ?? t('Side'))); ?> · <?= admin_e(date('d.m.Y H:i', strtotime((string)$revision['created_at']))); ?></p></div>
  <div class="admin-page-actions">
    <a class="admin-button admin-button--secondary" href="/admin/sider?mode=edit&amp;id=<?= $id; ?>"><?= admin_e(t('Rediger side')); ?></a>
    <?php if ($changed): ?><button type="button" class="admin-button" data-admin-open-delete="page-revision-restore"><?= admin_e(t('Gendan denne version')); ?></button><?php endif; ?>
  </div>
</div>
<?php if (!$changed): ?><div class="admin-notice" role="status"><?= admin_e(t('Denne version svarer til sidens nuværende indhold.')); ?></div><?php endif; ?>
<section class="admin-panel"><div class="admin-panel__header"><h2><?= admin_e(t('Sammenlign felter')); ?></h2></div>
  <div class="admin-table-wrap"><table class="admin-list-table"><thead><tr><th><?= admin_e(t('Felt')); ?></th><th>Version <?= $number; ?></th><th><?= admin_e(t('Nuværende')); ?></th></tr></thead><tbody>
  <?php foreach ($fields as $field=>$label): $a=$displayValue($old[$field] ?? null);$b=$displayValue($now[$field] ?? null); ?>
    <tr><td><strong><?= admin_e(t($label)); ?></strong></td><td><?= admin_e($a); ?></td><td><?= admin_e($b); ?><?= $a !== $b ? ' · Ændret' : ''; ?></td></tr>
  <?php endforeach; ?>
    <tr><td><strong><?= admin_e(t('Page Builder-sektioner')); ?></strong></td><td><?= count($oldBlocks); ?></td><td><?= count($nowBlocks); ?><?= $oldBlocks !== $nowBlocks ? ' · Ændret' : ''; ?></td></tr>
  </tbody></table></div>
</section>
<?php $longDiff('HTML-indhold', (string)($old['page_content'] ?? ''), (string)($now['page_content'] ?? '')); ?>
<?php $longDiff('Page Builder-data', (string)$oldBlocksJson, (string)$nowBlocksJson); ?>
<?php if ($changed): ?>
<dialog class="admin-modal admin-modal--sm" data-admin-modal id="page-revision-restore" aria-labelledby="revision-restore-title">
  <form method="post" class="admin-modal__surface" action="/admin/sider?mode=restore&amp;id=<?= $id; ?>">
    <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
    <input type="hidden" name="id" value="<?= $id; ?>">
    <input type="hidden" name="revision_id" value="<?= $revisionId; ?>">
    <div class="admin-modal__header"><div><h2 id="revision-restore-title">Gendan version <?= $number; ?></h2><p><?= admin_e(t('Bekræft gendannelsen.')); ?></p></div><button class="admin-modal__close" type="button" data-modal-close aria-label="<?= admin_e(t('Luk')); ?>">×</button></div>
    <div class="admin-modal__body"><p><?= admin_e(t('Siden erstattes af den valgte versions felter og alle dens Page Builder-sektioner, inklusive status og URL. Den nuværende version bevares i historikken.')); ?></p></div>
    <div class="admin-modal__footer"><button class="admin-button admin-button--secondary" type="button" data-modal-close><?= admin_e(t('Annuller')); ?></button><button class="admin-button" type="submit"><?= admin_e(t('Gendan version')); ?></button></div>
  </form>
</dialog>
<?php endif; ?>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'Version ' . $number;
require __DIR__ . '/../partials/layout.php';
