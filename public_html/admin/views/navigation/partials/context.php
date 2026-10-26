<?php
/** Navigation: shared view state and menu-item renderer for list/new/edit/delete modes. */
$mode = (string)($mode ?? 'list');
$menus = is_array($menus ?? null) ? $menus : [];
$selectedMenu = is_array($selectedMenu ?? null) ? $selectedMenu : null;
$items = is_array($items ?? null) ? $items : [];
$pages = is_array($pages ?? null) ? $pages : [];
$menuId = (int)($selectedMenu['id'] ?? 0);
$editUrl = '/admin/navigation?mode=edit&id=' . $menuId;
$token = admin_csrf_token();
$placement = (string)($placement ?? 'none');
// Post values are shown on validation errors so the user can correct without losing entered data.
$postItems = $mode === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' && is_array($_POST['items'] ?? null) ? $_POST['items'] : null;
if ($postItems !== null) {
    $itemsById = [];
    foreach ($items as $entry) $itemsById[(string)$entry['id']] = $entry;
    $revised = [];
    foreach ($postItems as $key => $entry) {
        if (!is_array($entry) || !preg_match('/^(?:[1-9][0-9]*|new_[a-zA-Z0-9_]+)$/D', (string)$key)) continue;
        $original = $itemsById[(string)$key] ?? [];
        $revised[] = array_merge($original, $entry, [
            'id' => (string)$key,
            'parent_id' => (string)($entry['parent_id'] ?? 0),
            'target' => !empty($entry['new_tab']) ? '_blank' : '_self',
        ]);
    }
    $items = $revised;
}
$rootItems = array_values(array_filter($items, static fn(array $item): bool => (string)($item['parent_id'] ?? '0') === '0' && empty($item['deleted'])));
// Sorted root/children are rendered consecutively so their display order matches the saved order.
$children = [];
foreach ($items as $item) {
    if (empty($item['deleted'])) $children[(string)($item['parent_id'] ?? '0')][] = $item;
}
$flatten = static function (string $parent, int $depth) use (&$flatten, $children): array {
    if ($depth > 2) return [];
    $result = [];
    foreach ($children[$parent] ?? [] as $item) {
        $result[] = $item;
        foreach ($flatten((string)$item['id'], $depth + 1) as $child) $result[] = $child;
    }
    return $result;
};
$renderedItems = $postItems === null ? $flatten('0', 1) : $items;
$renderItem = static function (array $item) use ($pages, $rootItems): void {
    $id = (string)$item['id'];
    $name = 'items[' . $id . ']';
    $type = (string)($item['type'] ?? 'page');
    $pageId = (int)($item['page_id'] ?? 0);
    $parentId = (string)($item['parent_id'] ?? '0');
    $label = trim((string)($item['label'] ?? ''));
    $pageTitle = trim((string)($item['page_title'] ?? ''));
    foreach ($pages as $p) if ((int)$p['id'] === $pageId) $pageTitle = (string)$p['page_title'];
    $displayLabel = $label ?: ($pageTitle ?: t('nav.new_item'));
    $isDeleted = !empty($item['deleted']);
    ?>
    <li class="admin-nav-editor__node<?= $parentId !== '0' ? ' is-child' : ''; ?>" data-nav-item="<?= admin_e($id); ?>" data-parent="<?= admin_e($parentId); ?>" <?= $isDeleted ? 'hidden' : ''; ?>>
      <input type="hidden" name="<?= admin_e($name); ?>[deleted]" value="<?= $isDeleted ? '1' : '0'; ?>" data-nav-deleted>
      <div class="admin-nav-editor__row">
        <button type="button" class="admin-nav-editor__grip" data-nav-grip draggable="true" aria-label="<?= admin_e(t('nav.editor_drag_keyboard')); ?>" title="<?= admin_e(t('nav.editor_drag_keyboard')); ?>"><?= admin_icon('grip', 'admin-nav-editor__grip-icon'); ?></button>
        <span class="admin-nav-editor__info"><strong data-nav-preview><?= admin_e($displayLabel); ?></strong><small data-nav-subtitle><?= $type === 'page' ? admin_e(t('nav.page_type')) . ' · ' . admin_e($pageTitle ?: t('Vælg side')) : admin_e(t('nav.link_type')) . ' · ' . admin_e((string)($item['url'] ?? '')); ?></small></span>
        <span class="admin-nav-editor__hidden-label" data-nav-hidden-label <?= !empty($item['is_visible']) ? 'hidden' : ''; ?>><?= admin_e(t('Skjult')); ?></span>
        <div class="admin-nav-editor__actions">
          <?= admin_icon_action_button('chevron-down', t('Rediger'), ['data-nav-toggle' => '', 'aria-expanded' => 'false', 'aria-controls' => 'nav-fields-' . $id]); ?>
          <?= admin_icon_action_button('delete', t('Fjern menupunkt'), ['data-nav-remove' => ''], 'danger'); ?>
        </div>
      </div>
      <div class="admin-nav-editor__fields" id="nav-fields-<?= admin_e($id); ?>" data-nav-fields hidden>
        <div class="admin-nav-editor__field-grid">
          <div class="admin-field"><label><?= admin_e(t('Linktype')); ?></label><select name="<?= admin_e($name); ?>[type]" data-nav-type><option value="page" <?= $type === 'page' ? 'selected' : ''; ?>><?= admin_e(t('Eksisterende side')); ?></option><option value="url" <?= $type === 'url' ? 'selected' : ''; ?>><?= admin_e(t('Eget link')); ?></option></select></div>
          <div class="admin-field" data-nav-page-field><label><?= admin_e(t('Vælg side')); ?></label><select name="<?= admin_e($name); ?>[page_id]" data-nav-page><option value=""><?= admin_e(t('Vælg side…')); ?></option><?php foreach ($pages as $page): ?><option value="<?= (int)$page['id']; ?>" <?= $pageId === (int)$page['id'] ? 'selected' : ''; ?>><?= admin_e((string)$page['page_title']); ?><?= ($page['page_status'] ?? '') !== 'published' ? ' (' . admin_e(t('nav.not_published')) . ')' : ''; ?></option><?php endforeach; ?></select></div>
          <div class="admin-field" data-nav-url-field><label><?= admin_e(t('Linkadresse')); ?></label><input name="<?= admin_e($name); ?>[url]" value="<?= admin_e((string)($item['url'] ?? '')); ?>" placeholder="/kontakt eller https://example.com" maxlength="2048" data-nav-url></div>
          <div class="admin-field"><label><?= admin_e(t('Menutekst')); ?></label><input name="<?= admin_e($name); ?>[label]" value="<?= admin_e($label); ?>" placeholder="<?= admin_e(t('ui.use_page_title')); ?>" maxlength="120" data-nav-label></div>
          <div class="admin-field"><label><?= admin_e(t('Placering i menuen')); ?></label><select name="<?= admin_e($name); ?>[parent_id]" data-nav-parent><option value="0"><?= admin_e(t('Hovedniveau')); ?></option><?php foreach ($rootItems as $root): $rootId = (string)$root['id']; if ($rootId === $id) continue; ?><option value="<?= admin_e($rootId); ?>" <?= $parentId === $rootId ? 'selected' : ''; ?>><?= admin_e(t('nav.under')); ?> <?= admin_e(trim((string)($root['label'] ?? '')) ?: (string)($root['page_title'] ?? 'Menupunkt')); ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="admin-nav-editor__checks"><label class="admin-checkbox"><input name="<?= admin_e($name); ?>[is_visible]" type="checkbox" value="1" <?= !empty($item['is_visible']) ? 'checked' : ''; ?> data-nav-visible> <?= admin_e(t('Vis i menuen')); ?></label><label class="admin-checkbox"><input name="<?= admin_e($name); ?>[new_tab]" type="checkbox" value="1" <?= ($item['target'] ?? '') === '_blank' ? 'checked' : ''; ?>> <?= admin_e(t('Åbn i nyt vindue')); ?></label></div>
      </div>
    </li>
    <?php
};

