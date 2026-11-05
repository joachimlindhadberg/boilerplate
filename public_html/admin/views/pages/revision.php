<?php
// Presentation-only revision comparison. The immutable snapshots and restore logic remain untouched.
$pageRecord = is_array($pageRecord ?? null) ? $pageRecord : [];
$revision = is_array($revision ?? null) ? $revision : [];
$current = is_array($currentSnapshot ?? null) ? $currentSnapshot : [];
$historic = is_array($revision['data'] ?? null) ? $revision['data'] : [];
$old = is_array($historic['page'] ?? null) ? $historic['page'] : [];
$now = is_array($current['page'] ?? null) ? $current['page'] : [];
$oldBlocks = is_array($historic['blocks'] ?? null) ? array_values($historic['blocks']) : [];
$nowBlocks = is_array($current['blocks'] ?? null) ? array_values($current['blocks']) : [];
$id = (int)($pageRecord['id'] ?? 0);
$revisionId = (int)($revision['id'] ?? 0);
$number = (int)($revision['version_number'] ?? 0);
$versionLabel = t('revisions.version_label', ['number' => $number]);

// Use the same stored-field comparisons as before; display only meaningful differences initially.
$fields = [
    'page_title' => 'revisions.field_title',
    'page_slug' => 'revisions.field_url',
    'parent_id' => 'Overordnet side',
    'page_description' => 'Beskrivelse',
    'content_mode' => 'Editor',
    'page_status' => 'Status',
    'menu_order' => 'revisions.field_sort_order',
    'show_in_menu' => 'revisions.field_show_in_menu',
    'meta_title' => 'SEO-titel',
    'meta_description' => 'SEO-beskrivelse',
];
$displayValue = static function (string $field, mixed $value): string {
    if ($value === null || $value === '') return '—';
    if ($field === 'parent_id') return (int)$value > 0 ? t('revisions.parent_id', ['id' => (int)$value]) : t('Ingen');
    if ($field === 'show_in_menu') return (int)$value === 1 ? t('revisions.yes') : t('revisions.no');
    if ($field === 'content_mode') {
        return match ((string)$value) {
            'builder' => t('Page Builder'),
            'legacy' => t('Legacy HTML'),
            default => (string)$value,
        };
    }
    if ($field === 'page_status') {
        return match ((string)$value) {
            'published' => t('Publiceret'),
            'draft' => t('Kladde'),
            default => (string)$value,
        };
    }
    return is_scalar($value) ? (string)$value : '—';
};
$changedFields = [];
$unchangedFields = [];
foreach ($fields as $field => $label) {
    $row = [
        'label' => t($label),
        'before' => $displayValue($field, $old[$field] ?? null),
        'after' => $displayValue($field, $now[$field] ?? null),
    ];
    // Compare stored values, not localized labels, to avoid hiding changes under translation.
    if (($old[$field] ?? null) !== ($now[$field] ?? null)) {
        $changedFields[] = $row;
    } else {
        $unchangedFields[] = $row;
    }
}
$htmlChanged = (string)($old['page_content'] ?? '') !== (string)($now['page_content'] ?? '');
$blocksChanged = $oldBlocks !== $nowBlocks;
$changed = ($old !== $now || $oldBlocks !== $nowBlocks);

$sectionSummary = static function (?array $section): string {
    if ($section === null) return '—';
    $data = is_array($section['data'] ?? null) ? $section['data'] : [];
    $columns = is_array($data['columns'] ?? null) ? $data['columns'] : [];
    $columnCount = max(0, (int)($data['column_count'] ?? count($columns)));
    $contentCount = 0;
    foreach ($columns as $column) {
        if (is_array($column) && is_array($column['blocks'] ?? null)) $contentCount += count($column['blocks']);
    }
    $label = ($data['section_variant'] ?? '') === 'hero' ? t('revisions.hero_section') : t('revisions.standard_section');
    $label .= ' · ' . t($columnCount === 1 ? 'revisions.column_one' : 'revisions.column_many', ['count' => $columnCount]);
    $label .= ' · ' . t($contentCount === 1 ? 'revisions.block_one' : 'revisions.block_many', ['count' => $contentCount]);
    if (($section['status'] ?? 'active') === 'hidden') $label .= ' · ' . t('Skjult');
    return $label;
};

// Stable section IDs allow trustworthy add/remove/order descriptions. Legacy snapshots
// without IDs are compared by position and do not claim to identify moved sections.
$allStableIds = static function (array $blocks): bool {
    $used = [];
    foreach ($blocks as $section) {
        $data = is_array($section['data'] ?? null) ? $section['data'] : [];
        $identifier = $data['section_id'] ?? null;
        if (!is_string($identifier) || !preg_match('/^section-[0-9]{6}$/D', $identifier) || isset($used[$identifier])) return false;
        $used[$identifier] = true;
    }
    return true;
};
$stableIds = $allStableIds($oldBlocks) && $allStableIds($nowBlocks);
$sectionChanges = [];
if ($blocksChanged) {
    if ($stableIds) {
        $older = [];
        $newer = [];
        foreach ($oldBlocks as $index => $section) $older[(string)$section['data']['section_id']] = ['position' => $index, 'section' => $section];
        foreach ($nowBlocks as $index => $section) $newer[(string)$section['data']['section_id']] = ['position' => $index, 'section' => $section];
        foreach ($newer as $key => $new) {
            $oldSection = $older[$key] ?? null;
            $difference = [];
            if ($oldSection === null) {
                $difference[] = t('revisions.added');
            } else {
                if ($oldSection['position'] !== $new['position']) $difference[] = t('revisions.moved');
                if (($oldSection['section']['status'] ?? null) !== ($new['section']['status'] ?? null)) $difference[] = t('revisions.visibility_changed');
                if (($oldSection['section']['type'] ?? null) !== ($new['section']['type'] ?? null)
                    || ($oldSection['section']['data'] ?? []) !== ($new['section']['data'] ?? [])) $difference[] = t('revisions.content_or_settings_changed');
            }
            if ($difference) $sectionChanges[] = [
                'name' => t('revisions.section_number', ['number' => $new['position'] + 1]),
                'before' => $oldSection !== null ? $sectionSummary($oldSection['section']) : '—',
                'after' => $sectionSummary($new['section']),
                'difference' => implode(' · ', $difference),
            ];
        }
        foreach ($older as $key => $oldSection) {
            if (!isset($newer[$key])) $sectionChanges[] = [
                'name' => t('revisions.section_number', ['number' => $oldSection['position'] + 1]),
                'before' => $sectionSummary($oldSection['section']),
                'after' => '—',
                'difference' => t('revisions.removed'),
            ];
        }
    } else {
        for ($index = 0, $end = max(count($oldBlocks), count($nowBlocks)); $index < $end; $index++) {
            $before = $oldBlocks[$index] ?? null;
            $after = $nowBlocks[$index] ?? null;
            if ($before === $after) continue;
            $sectionChanges[] = [
                'name' => t('revisions.position_number', ['number' => $index + 1]),
                'before' => $sectionSummary($before),
                'after' => $sectionSummary($after),
                'difference' => t('revisions.position_changed'),
            ];
        }
    }
}
$oldBlocksJson = json_encode($oldBlocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
$nowBlocksJson = json_encode($nowBlocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
$canEditPage = LegacyHtmlPolicy::canEditPage($pageRecord, (string)($user['role'] ?? ''));
$canRestore = LegacyHtmlPolicy::canRestore($now, $old, (string)($user['role'] ?? ''));
$createdAt = strtotime((string)($revision['created_at'] ?? ''));
ob_start();
?>
<div class="admin-detail-back"><a class="admin-back-link" href="/admin/sider?mode=history&amp;id=<?= $id; ?>"><?= admin_icon('arrow-left', 'admin-back-link__icon'); ?><?= admin_e(t('Tilbage til versionshistorik')); ?></a></div>
<div class="admin-page-titlebar admin-page-titlebar--split">
  <div><h1><?= admin_e($versionLabel); ?></h1><p><?= admin_e((string)($pageRecord['page_title'] ?? t('Side'))); ?><?= $createdAt ? ' · ' . admin_e(date('d.m.Y H:i', $createdAt)) : ''; ?></p></div>
  <div class="admin-page-actions">
    <?php if ($canEditPage): ?><a class="admin-button admin-button--secondary" href="/admin/sider?mode=edit&amp;id=<?= $id; ?>"><?= admin_e(t('Rediger side')); ?></a><?php endif; ?>
    <?php if ($changed && $canRestore): ?><button type="button" class="admin-button" data-admin-open-delete="page-revision-restore"><?= admin_e(t('Gendan denne version')); ?></button><?php endif; ?>
  </div>
</div>
<?php if (!$changed): ?><div class="admin-notice" role="status"><?= admin_e(t('Denne version svarer til sidens nuværende indhold.')); ?></div><?php endif; ?>
<div class="admin-detail-content">
<section class="admin-panel">
  <div class="admin-panel__header"><h2><?= admin_e(t('revisions.summary_title')); ?></h2></div>
  <div class="admin-revision-compare">
    <div><strong><?= admin_e(t('revisions.page_fields')); ?></strong><?= admin_e(t(count($changedFields) === 1 ? 'revisions.changed_field_one' : 'revisions.changed_field_many', ['count' => count($changedFields)])); ?><?= $htmlChanged ? ' · ' . admin_e(t('revisions.html_changed')) : ''; ?></div>
    <div><strong><?= admin_e(t('Page Builder-sektioner')); ?></strong><?= admin_e(t('revisions.sections_count', ['before' => count($oldBlocks), 'after' => count($nowBlocks)])); ?> · <?= admin_e($blocksChanged ? t('Ændret') : t('revisions.unchanged')); ?></div>
  </div>
</section>
<section class="admin-panel">
  <div class="admin-panel__header"><h2><?= admin_e(t('revisions.changed_fields_title')); ?></h2></div>
  <?php if ($changedFields): ?>
  <div class="admin-table-wrap"><table class="admin-list-table"><thead><tr><th><?= admin_e(t('Felt')); ?></th><th><?= admin_e($versionLabel); ?></th><th><?= admin_e(t('Nuværende')); ?></th></tr></thead><tbody>
  <?php foreach ($changedFields as $row): ?><tr><td><strong><?= admin_e($row['label']); ?></strong></td><td><?= admin_e($row['before']); ?></td><td><?= admin_e($row['after']); ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php else: ?><div class="admin-revision-compare"><div><?= admin_e(t('revisions.no_field_changes')); ?></div></div><?php endif; ?>
  <?php if ($unchangedFields): ?>
  <details><summary class="admin-list-summary"><span class="admin-text-action"><?= admin_e(t('revisions.show_unchanged_fields')); ?></span></summary><div class="admin-table-wrap"><table class="admin-list-table"><thead><tr><th><?= admin_e(t('Felt')); ?></th><th><?= admin_e($versionLabel); ?></th><th><?= admin_e(t('Nuværende')); ?></th></tr></thead><tbody>
    <?php foreach ($unchangedFields as $row): ?><tr><td><strong><?= admin_e($row['label']); ?></strong></td><td><?= admin_e($row['before']); ?></td><td><?= admin_e($row['after']); ?></td></tr><?php endforeach; ?>
    </tbody></table></div></details>
  <?php endif; ?>
</section>
<section class="admin-panel">
  <div class="admin-panel__header"><h2><?= admin_e(t('revisions.section_changes_title')); ?></h2></div>
  <?php if ($sectionChanges): ?>
  <div class="admin-table-wrap"><table class="admin-list-table"><thead><tr><th><?= admin_e(t('revisions.section')); ?></th><th><?= admin_e($versionLabel); ?></th><th><?= admin_e(t('Nuværende')); ?></th><th><?= admin_e(t('revisions.difference')); ?></th></tr></thead><tbody>
  <?php foreach ($sectionChanges as $row): ?><tr><td><strong><?= admin_e($row['name']); ?></strong></td><td><?= admin_e($row['before']); ?></td><td><?= admin_e($row['after']); ?></td><td><?= admin_e($row['difference']); ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php if (!$stableIds): ?><div class="admin-revision-compare"><div><?= admin_e(t('revisions.position_note')); ?></div></div><?php endif; ?>
  <?php else: ?><div class="admin-revision-compare"><div><?= admin_e(t('revisions.no_section_changes')); ?></div></div><?php endif; ?>
</section>
<section class="admin-panel">
  <div class="admin-panel__header"><h2><?= admin_e(t('revisions.technical_details')); ?></h2></div>
  <details>
    <summary class="admin-list-summary"><span class="admin-text-action"><?= admin_e(t('revisions.show_html')); ?></span></summary>
    <div class="admin-revision-compare">
      <div><strong><?= admin_e($versionLabel); ?></strong><pre class="admin-revision-pre"><?= admin_e((string)($old['page_content'] ?? '') !== '' ? (string)$old['page_content'] : '—'); ?></pre></div>
      <div><strong><?= admin_e(t('Nuværende')); ?></strong><pre class="admin-revision-pre"><?= admin_e((string)($now['page_content'] ?? '') !== '' ? (string)$now['page_content'] : '—'); ?></pre></div>
    </div>
  </details>
  <details>
    <summary class="admin-list-summary"><span class="admin-text-action"><?= admin_e(t('revisions.show_json')); ?></span></summary>
    <div class="admin-revision-compare">
      <div><strong><?= admin_e($versionLabel); ?></strong><pre class="admin-revision-pre"><?= admin_e((string)$oldBlocksJson); ?></pre></div>
      <div><strong><?= admin_e(t('Nuværende')); ?></strong><pre class="admin-revision-pre"><?= admin_e((string)$nowBlocksJson); ?></pre></div>
    </div>
  </details>
</section>
</div>
<?php if ($changed && $canRestore): ?>
<dialog class="admin-modal admin-modal--sm" data-admin-modal id="page-revision-restore" aria-labelledby="revision-restore-title">
  <form method="post" class="admin-modal__surface" action="/admin/sider?mode=restore&amp;id=<?= $id; ?>">
    <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
    <input type="hidden" name="id" value="<?= $id; ?>">
    <input type="hidden" name="revision_id" value="<?= $revisionId; ?>">
    <div class="admin-modal__header"><div><h2 id="revision-restore-title"><?= admin_e(t('revisions.restore_title', ['number' => $number])); ?></h2><p><?= admin_e(t('Bekræft gendannelsen.')); ?></p></div><button class="admin-modal__close" type="button" data-modal-close aria-label="<?= admin_e(t('Luk')); ?>"><?= admin_icon('close'); ?></button></div>
    <div class="admin-modal__body"><p><?= admin_e(t('Siden erstattes af den valgte versions felter og alle dens Page Builder-sektioner, inklusive status og URL. Den nuværende version bevares i historikken.')); ?></p></div>
    <div class="admin-modal__footer"><button class="admin-button admin-button--secondary" type="button" data-modal-close><?= admin_e(t('Annuller')); ?></button><button class="admin-button" type="submit"><?= admin_e(t('Gendan version')); ?></button></div>
  </form>
</dialog>
<?php endif; ?>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = $versionLabel;
require __DIR__ . '/../partials/layout.php';
