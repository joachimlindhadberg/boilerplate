<?php
/** Shared Page Builder admin rendering and payload helpers (V2.3.9). */

function admin_page_builder_blocks_from_records(array $records): array
{
    $blocks = [];
    foreach ($records as $record) {
        $type = (string)($record['block_type'] ?? '');
        if (!array_key_exists($type, PageBlocks::definitions())) {
            // Never discard persisted content from an old page on the next save.
            throw new RuntimeException(t('pages.builder.legacy_section_error'));
        }
        $blocks[] = [
            'type' => $type,
            'status' => (($record['status'] ?? 'active') === 'hidden') ? 'hidden' : 'active',
            'data' => is_array($record['data'] ?? null) ? $record['data'] : [],
        ];
    }
    return $blocks;
}

function admin_page_builder_payload(array $source): array
{
    $raw = $source['blocks'] ?? [];
    if (!is_array($raw)) $raw = [];
    return PageBlocks::normalizeSubmitted($raw, static fn(string $key, array $params): string => t($key, $params));
}

function admin_page_builder_media_field(string $prefix, array $data, string $label = 'Billede', bool $summarySource = false): string
{
    $path = (string)($data['image_path'] ?? '');
    $id = max(0, (int)($data['image_id'] ?? 0));
    $e = static fn(mixed $v): string => admin_e((string)$v);
    ob_start(); ?>
    <div class="admin-field" data-media-picker-field>
      <label><?= $e(t($label)); ?></label>
      <div class="admin-media-picker-field">
        <input type="hidden" name="<?= $e($prefix); ?>[data][image_id]" value="<?= $id; ?>" data-media-picker-id>
        <input class="admin-media-picker-field__path" name="<?= $e($prefix); ?>[data][image_path]" value="<?= $e($path); ?>" placeholder="<?= admin_e(t('Intet billede valgt')); ?>" readonly data-media-picker-path <?= $summarySource ? 'data-grid-child-summary-source' : ''; ?>>
        <button class="admin-button admin-button--secondary admin-button--compact" type="button" data-media-picker-open><?= admin_e(t('Vælg fra Medier')); ?></button>
        <button class="admin-text-action" type="button" data-media-picker-clear <?= $path === '' ? 'hidden' : ''; ?>><?= admin_e(t('Fjern')); ?></button>
      </div>
    </div>
    <?php return ob_get_clean();
}

/** Dedicated Hero fields live in the same section JSON; not in generic grid children. */
function admin_page_builder_hero_content_editor(string $prefix, array $data): string
{
    $e = static fn(mixed $v): string => admin_e((string)$v);
    $fields = [
        ['hero_title', 'page_builder_v2.hero_title', 255],
        ['hero_subtitle', 'page_builder_v2.hero_subtitle', 255],
    ];
    ob_start(); ?>
    <div class="admin-page-row__component" data-page-hero-content>
      <div class="admin-page-row__component-header">
        <div class="admin-grid-child__identity">
          <span class="admin-grid-child__type-icon" aria-hidden="true"><?= admin_icon('type', 'admin-grid-child__type-svg'); ?></span>
          <div class="admin-grid-child__heading">
            <strong><?= $e(t('page_builder_v2.hero_content')); ?></strong>
            <span data-page-hero-summary><?= $e(trim((string)($data['hero_title'] ?? '')) ?: t('page_builder_v2.hero_title')); ?></span>
          </div>
        </div>
        <?= admin_icon_action_button('chevron-down', t('page_builder_v2.expand_block'), ['data-page-hero-toggle' => '1', 'aria-expanded' => 'false']); ?>
      </div>
      <div class="admin-page-row__fields" data-page-hero-fields hidden>
        <div class="admin-field" data-media-picker-field data-page-hero-content-media>
          <label><?= $e(t('page_builder_v2.hero_background')); ?></label>
          <div class="admin-media-picker-field">
            <input type="hidden" data-media-picker-id value="<?= max(0, (int)($data['hero_image_id'] ?? 0)); ?>">
            <input class="admin-media-picker-field__path" readonly data-media-picker-path value="<?= $e($data['hero_image_path'] ?? ''); ?>" aria-label="<?= $e(t('page_builder_v2.hero_background')); ?>" placeholder="<?= $e(t('Intet billede valgt')); ?>">
            <button type="button" class="admin-button admin-button--secondary admin-button--compact" data-media-picker-open><?= $e(t('Vælg fra Medier')); ?></button>
            <button type="button" class="admin-text-action" data-media-picker-clear <?= empty($data['hero_image_path']) ? 'hidden' : ''; ?>><?= $e(t('Fjern')); ?></button>
          </div>
        </div>
        <?php foreach ($fields as [$key, $translation, $max]): ?>
          <div class="admin-field"><label><?= $e(t($translation)); ?></label>
            <input name="<?= $e($prefix); ?>[data][<?= $key; ?>]" value="<?= $e($data[$key] ?? ''); ?>" maxlength="<?= $max; ?>" <?= $key === 'hero_title' ? 'data-builder-hero-title data-builder-summary-source' : ''; ?>>
          </div>
        <?php endforeach; ?>
        <div class="admin-field"><label><?= $e(t('page_builder_v2.hero_description')); ?></label>
          <textarea name="<?= $e($prefix); ?>[data][hero_body]" rows="3" maxlength="3000"><?= $e($data['hero_body'] ?? ''); ?></textarea>
        </div>
        <div class="admin-form-row">
          <div class="admin-field"><label><?= $e(t('page_builder_v2.hero_button_label')); ?></label>
            <input name="<?= $e($prefix); ?>[data][hero_button_label]" maxlength="80" value="<?= $e($data['hero_button_label'] ?? ''); ?>">
          </div>
          <div class="admin-field"><label><?= $e(t('page_builder_v2.hero_button_url')); ?></label>
            <input name="<?= $e($prefix); ?>[data][hero_button_url]" maxlength="500" value="<?= $e($data['hero_button_url'] ?? ''); ?>" placeholder="/kontakt">
          </div>
        </div>
      </div>
    </div>
    <?php return ob_get_clean();
}

function admin_page_builder_block_editor(array $block, string|int $index): string
{
    $type = (string)($block['type'] ?? 'grid');
    $definitions = PageBlocks::definitions();
    if (!isset($definitions[$type])) throw new InvalidArgumentException('Unsupported top-level section.');
    $definition = $definitions[$type];
    $status = (($block['status'] ?? 'active') === 'hidden') ? 'hidden' : 'active';
    $data = is_array($block['data'] ?? null) ? $block['data'] : [];
    $prefix = 'blocks[' . $index . ']';
    $isHero = $type === 'grid' && ($data['section_variant'] ?? 'standard') === 'hero';
    $dedicatedHero = $isHero && ($data['hero_layout'] ?? '') === 'dedicated';
    $v2Grid = $type === 'grid' && !$dedicatedHero && ($data['layout_version'] ?? '') === 'v2' && ($data['preset'] ?? 'equal') === 'equal';
    $count = $type === 'grid' ? max($v2Grid ? 1 : 2, min(6, (int)($data['column_count'] ?? 1))) : 1;
    $layoutLabel = ($type !== 'grid' ? 1 : $count) . ' ' . t(($type !== 'grid' || $count === 1) ? 'Kolonne' : 'page_builder_v2.columns');
    $e = static fn(mixed $v): string => admin_e((string)$v);
    $value = static fn(string $key, string $fallback = ''): string => (string)($data[$key] ?? $fallback);

    ob_start();
    ?>
    <article class="admin-builder-block admin-page-row<?= $type === 'grid' ? ' admin-builder-block--grid' : ''; ?><?= $v2Grid ? ' admin-page-row--v2-grid' : ''; ?><?= $dedicatedHero ? ' admin-page-row--dedicated-hero' : ''; ?>" data-builder-block data-block-type="<?= $e($type); ?>" draggable="false">
      <input type="hidden" name="<?= $e($prefix); ?>[type]" value="<?= $e($type); ?>" data-builder-name="type">
      <input type="hidden" name="<?= $e($prefix); ?>[data][section_id]" value="<?= $e($data['section_id'] ?? ''); ?>" data-builder-section-id>
      <input type="hidden" name="<?= $e($prefix); ?>[data][theme]" value="<?= $e(array_key_exists((string)($data['theme'] ?? ($type === 'hero' ? 'dark' : 'light')), PageBlocks::themes()) ? ($data['theme'] ?? ($type === 'hero' ? 'dark' : 'light')) : 'light'); ?>" data-builder-section-theme>
      <input type="hidden" name="<?= $e($prefix); ?>[data][container_width]" value="<?= $e(PageBlocks::normalContainerWidth((string)($data['container_width'] ?? 'standard'))); ?>" data-builder-section-width>
      <input type="hidden" name="<?= $e($prefix); ?>[data][section_variant]" value="<?= $isHero ? 'hero' : 'standard'; ?>" data-builder-section-variant>
      <?php if ($dedicatedHero): ?><input type="hidden" name="<?= $e($prefix); ?>[data][hero_layout]" value="dedicated" data-builder-hero-layout><input type="hidden" name="<?= $e($prefix); ?>[data][column_count]" value="1"><?php endif; ?>
      <input type="hidden" name="<?= $e($prefix); ?>[data][hero_image_id]" value="<?= $isHero ? max(0, (int)($data['hero_image_id'] ?? 0)) : 0; ?>" data-builder-hero-image-id <?= $isHero ? '' : 'disabled'; ?>>
      <input type="hidden" name="<?= $e($prefix); ?>[data][hero_image_path]" value="<?= $isHero ? $e($data['hero_image_path'] ?? '') : ''; ?>" data-builder-hero-image-path <?= $isHero ? '' : 'disabled'; ?>>
      <input type="hidden" name="<?= $e($prefix); ?>[data][hero_height]" value="<?= $e($data['hero_height'] ?? 'large'); ?>" data-builder-hero-height <?= $isHero ? '' : 'disabled'; ?>>
      <input type="hidden" name="<?= $e($prefix); ?>[data][hero_overlay]" value="<?= $e($data['hero_overlay'] ?? '1'); ?>" data-builder-hero-overlay <?= $isHero ? '' : 'disabled'; ?>>
      <input type="hidden" name="<?= $e($prefix); ?>[data][hero_overlay_opacity]" value="<?= max(0, min(80, (int)($data['hero_overlay_opacity'] ?? 40))); ?>" data-builder-hero-opacity <?= $isHero ? '' : 'disabled'; ?>>
      <input type="hidden" name="<?= $e($prefix); ?>[data][hero_parallax]" value="<?= $e($data['hero_parallax'] ?? '0'); ?>" data-builder-hero-parallax <?= $isHero ? '' : 'disabled'; ?>>
      <?php if ($type === 'grid' && ($data['layout_version'] ?? '') === 'v2'): ?><input type="hidden" name="<?= $e($prefix); ?>[data][layout_version]" value="v2"><?php endif; ?>
      <input type="hidden" name="<?= $e($prefix); ?>[status]" value="<?= $e($status); ?>" data-builder-name="status" data-builder-section-status>
      <header class="admin-builder-block__header">
        <button class="admin-builder-drag" type="button" data-builder-drag draggable="true" aria-label="<?= admin_e(t('page_builder_v2.move_row')); ?>" title="<?= admin_e(t('Træk for at flytte')); ?>"><?= admin_icon('grip', 'admin-builder-drag__icon'); ?></button>
        <div class="admin-page-row__heading"><strong><?= admin_e(t($isHero ? 'page_builder_v2.hero_section' : 'page_builder_v2.section_heading')); ?></strong><?php if (!$dedicatedHero): ?><span class="admin-page-row__layout" data-page-layout-label><?= $e($layoutLabel); ?></span><?php endif; ?><span class="admin-page-row__hidden-label" data-page-section-hidden-label <?= $status === 'hidden' ? '' : 'hidden'; ?>><?= admin_e(t('Skjult')); ?></span></div>
        <div class="admin-builder-block__actions admin-page-row__actions">
          <button type="button" data-builder-up class="admin-page-row__keyboard-sort" aria-label="<?= admin_e(t('Flyt op')); ?>" title="<?= admin_e(t('Flyt op')); ?>">↑</button><button type="button" data-builder-down class="admin-page-row__keyboard-sort" aria-label="<?= admin_e(t('Flyt ned')); ?>" title="<?= admin_e(t('Flyt ned')); ?>">↓</button>
          <?= admin_icon_action_button('settings', t('page_builder_v2.section_settings'), ['data-page-section-settings' => '1']); ?>
          <?= admin_icon_action_button('delete', t('page_builder_v2.delete_section'), ['data-builder-remove' => '1'], 'danger'); ?>
        </div>
      </header>
      <div class="admin-builder-block__body">
        <?php if ($dedicatedHero): ?>
          <?= admin_page_builder_hero_content_editor($prefix, $data); ?>
        <?php else: ?>
          <div class="admin-page-row__grid-summary" data-builder-summary><?= $e(admin_page_builder_summary($type, $data)); ?></div>
          <?= admin_page_builder_grid_editor($prefix, $data); ?>
        <?php endif; ?>
      </div>
      <!-- Contextual insertion stays inside the row, preserving drag-sort siblings. -->
      <div class="admin-page-row__insert-wrap">
        <button type="button" class="admin-page-row__insert-after" data-builder-insert-after aria-label="<?= admin_e(t('page_builder_v2.insert_after')); ?>" title="<?= admin_e(t('page_builder_v2.insert_after')); ?>">
          <?= admin_icon('plus', 'admin-page-row__insert-icon'); ?>
          <span><?= admin_e(t('page_builder_v2.add_section')); ?></span>
        </button>
      </div>
    </article>
    <?php
    return ob_get_clean();
}

/** Presentation belongs to each Page Builder placement, not to the Gallery record. */
function admin_page_builder_gallery_fields(string $prefix, array $data): string
{
    $e = static fn(mixed $v): string => admin_e((string)$v);
    $isGallery = preg_match('/^gallery:gallery-[1-9][0-9]*$/D', (string)($data['component'] ?? '')) === 1;
    $mode = ($data['gallery_mode'] ?? $data['gallery_layout'] ?? 'lightbox') === 'slider' ? 'slider' : 'lightbox';
    $columns = max(2, min(6, (int)($data['gallery_columns'] ?? 3)));
    $fullscreen = (string)($data['gallery_fullscreen'] ?? '1') !== '0';
    $autoplay = (string)($data['gallery_autoplay'] ?? '0') === '1';
    $interval = max(3, min(10, (int)($data['gallery_interval'] ?? 5)));
    $legacy = !isset($data['gallery_mode']);
    ob_start(); ?>
    <div class="admin-form-section admin-gallery-settings" data-builder-gallery-settings data-gallery-legacy="<?= $legacy ? '1' : '0'; ?>" <?= $isGallery ? '' : 'hidden'; ?>>
      <?php if ($legacy): ?>
      <?php if (isset($data['gallery_layout'])): ?><input type="hidden" data-gallery-legacy-input name="<?= $e($prefix); ?>[data][gallery_layout]" value="<?= $e((string)$data['gallery_layout']); ?>"><?php endif; ?>
      <?php if (isset($data['gallery_lightbox'])): ?><input type="hidden" data-gallery-legacy-input name="<?= $e($prefix); ?>[data][gallery_lightbox]" value="<?= $e((string)$data['gallery_lightbox']); ?>"><?php endif; ?>
      <?php endif; ?>
      <div class="admin-form-row"><div class="admin-field">
        <label><?= $e(t('gallery:Display type')); ?></label>
        <select data-gallery-new-field name="<?= $e($prefix); ?>[data][gallery_mode]" data-gallery-mode>
          <option value="lightbox" <?= $mode === 'lightbox' ? 'selected' : ''; ?>><?= $e(t('gallery:Grid + Lightbox')); ?></option>
          <option value="slider" <?= $mode === 'slider' ? 'selected' : ''; ?>><?= $e(t('gallery:Slider')); ?></option>
        </select>
      </div></div>
      <div class="admin-gallery-settings__options" data-gallery-grid-settings <?= $mode === 'slider' ? 'hidden' : ''; ?>>
        <div class="admin-form-row"><div class="admin-field">
          <label><?= $e(t('gallery:Images per row')); ?></label>
          <select data-gallery-new-field name="<?= $e($prefix); ?>[data][gallery_columns]">
            <?php for ($count = 2; $count <= 6; $count++): ?>
              <option value="<?= $count; ?>" <?= $count === $columns ? 'selected' : ''; ?>><?= $count; ?></option>
            <?php endfor; ?>
          </select>
        </div></div>
        <input type="hidden" data-gallery-new-field name="<?= $e($prefix); ?>[data][gallery_fullscreen]" value="0">
        <?= AdminToggle::render('gallery-fullscreen-' . substr(hash('sha256', $prefix), 0, 16), t('gallery:Fullscreen Lightbox'), $fullscreen, '', [
          'data-gallery-new-field' => '1', 'name' => $prefix . '[data][gallery_fullscreen]', 'value' => '1'
        ]); ?>
      </div>
      <div class="admin-gallery-settings__options" data-gallery-slider-settings <?= $mode !== 'slider' ? 'hidden' : ''; ?>>
        <input type="hidden" data-gallery-new-field name="<?= $e($prefix); ?>[data][gallery_autoplay]" value="0">
        <?= AdminToggle::render('gallery-autoplay-' . substr(hash('sha256', $prefix), 0, 16), t('gallery:Autoplay'), $autoplay, '', [
          'data-gallery-new-field' => '1', 'data-gallery-autoplay' => '1',
          'name' => $prefix . '[data][gallery_autoplay]', 'value' => '1'
        ]); ?>
        <div class="admin-form-row" data-gallery-interval-settings <?= !$autoplay ? 'hidden' : ''; ?>><div class="admin-field">
          <label><?= $e(t('gallery:Time per slide (seconds)')); ?></label>
          <select data-gallery-new-field name="<?= $e($prefix); ?>[data][gallery_interval]">
            <?php foreach ([3,4,5,7,10] as $duration): ?>
            <option value="<?= $duration; ?>" <?= $duration === $interval ? 'selected' : ''; ?>><?= $duration; ?></option>
            <?php endforeach; ?>
          </select>
        </div></div>
      </div>
    </div>
    <?php return ob_get_clean();
}

function admin_page_builder_grid_editor(string $prefix, array $data): string
{
    $e = static fn(mixed $v): string => admin_e((string)$v);
    $v2Grid = ($data['layout_version'] ?? '') === 'v2' && ($data['preset'] ?? 'equal') === 'equal';
    $count = max($v2Grid ? 1 : 2, min(6, (int)($data['column_count'] ?? 1)));
    $preset = (string)($data['preset'] ?? 'equal');
    $gap = (string)($data['gap'] ?? 'normal');
    $theme = (string)($data['theme'] ?? 'light');
    $columns = is_array($data['columns'] ?? null) ? array_values($data['columns']) : [];
    while (count($columns) < $count) $columns[] = ['blocks'=>[]];
    $columns = array_slice($columns, 0, $count);

    ob_start(); ?>
    <div class="admin-form-row admin-grid-settings" <?= ($data['layout_version'] ?? '') === 'v2' ? 'data-page-grid-settings hidden' : ''; ?>>
      <div class="admin-field"><label><?= admin_e(t('Antal kolonner')); ?></label><select name="<?= $e($prefix); ?>[data][column_count]" data-grid-column-count><?php for ($i=$v2Grid ? 1 : 2;$i<=6;$i++): ?><option value="<?= $i; ?>" <?= $count === $i ? 'selected' : ''; ?>><?= $i; ?> <?= $e(t($i === 1 ? 'Kolonne' : 'page_builder_v2.columns')); ?></option><?php endfor; ?></select></div>
      <div class="admin-field"><label><?= admin_e(t('Fordeling')); ?></label><select name="<?= $e($prefix); ?>[data][preset]" data-grid-preset><?php foreach (PageBlocks::gridPresets($count) as $value=>$label): ?><option value="<?= $e($value); ?>" <?= $preset === $value ? 'selected' : ''; ?>><?= $e(t($label)); ?></option><?php endforeach; ?></select></div>
      <div class="admin-field"><label><?= admin_e(t('Afstand')); ?></label><select name="<?= $e($prefix); ?>[data][gap]" data-grid-gap><?php foreach (PageBlocks::gridGaps() as $value=>$label): ?><option value="<?= $e($value); ?>" <?= $gap === $value ? 'selected' : ''; ?>><?= $e(t($label)); ?></option><?php endforeach; ?></select></div>
    </div>
    <p class="admin-field__help admin-grid-help"><?= admin_e(t('Desktop viser 2–6 kolonner. På tablet bliver brede grids automatisk færre kolonner, og på mobil stables de i én kolonne.')); ?></p>

    <div class="admin-grid-editor" data-grid-editor data-grid-count="<?= $count; ?>">
      <?php foreach ($columns as $columnIndex=>$column):
        $children = is_array($column['blocks'] ?? null) ? array_values($column['blocks']) : [];
      ?>
        <section class="admin-grid-column" data-grid-column data-grid-column-index="<?= $columnIndex; ?>">
          <header class="admin-grid-column__header"><strong data-grid-column-label><?= admin_e(t('Kolonne')); ?> <?= $columnIndex + 1; ?></strong><span><?= admin_e(count($children) . ' ' . t(count($children) === 1 ? 'blok' : 'blokke')); ?></span></header>
          <div class="admin-grid-child-list" data-grid-child-list>
            <?php foreach ($children as $childIndex=>$child): ?><?= admin_page_builder_grid_child_editor($child, $prefix . '[data][columns]['.$columnIndex.'][blocks]['.$childIndex.']', $v2Grid); ?><?php endforeach; ?>
          </div>
          <?php if (!$v2Grid): ?><div class="admin-grid-column__empty" data-grid-column-empty <?= $children ? 'hidden' : ''; ?>><?= admin_e(t('Træk indhold hertil eller tilføj en blok.')); ?></div><?php endif; ?>
          <div class="admin-grid-column__add"><select class="sr-only" data-grid-child-type aria-label="<?= admin_e(t('Bloktype')); ?>"><?php foreach (PageBlocks::gridChildDefinitions() as $childType=>$definition): ?><option value="<?= $e($childType); ?>"><?= $e(t($definition['label'])); ?></option><?php endforeach; ?></select><button class="<?= $v2Grid ? 'admin-grid-add-block' : 'admin-button admin-button--secondary admin-button--compact'; ?>" type="button" data-grid-child-add><?= $v2Grid ? admin_icon('plus', 'admin-grid-add-block__icon') : ''; ?><?= admin_e(t('page_builder_v2.add_content_inside')); ?></button></div>
        </section>
      <?php endforeach; ?>
    </div>
    <?php foreach (PageBlocks::gridChildDefinitions() as $childType=>$definition): ?>
      <template data-grid-child-template="<?= $e($childType); ?>"><?= admin_page_builder_grid_child_editor(['type'=>$childType,'status'=>'active','data'=>[]], 'blocks[__TOP_INDEX__][data][columns][__COLUMN_INDEX__][blocks][__CHILD_INDEX__]', $v2Grid); ?></template>
    <?php endforeach; ?>
    <?php return ob_get_clean();
}

function admin_page_builder_grid_child_editor(array $block, string $prefix, bool $v2Row = false): string
{
    $definitions = PageBlocks::gridChildDefinitions();
    $type = (string)($block['type'] ?? 'text');
    if (!isset($definitions[$type])) $type = 'text';
    $definition = $definitions[$type];
    // BP-MULTI-BLOCKS-QA1: every icon is rendered through the Core registry.
    $childIcon = match ($type) {
        'text' => 'type', 'image' => 'image', 'text_image' => 'layers',
        'cta' => 'link', 'component' => 'puzzle', default => 'file',
    };
    $status = (($block['status'] ?? 'active') === 'hidden') ? 'hidden' : 'active';
    $data = is_array($block['data'] ?? null) ? $block['data'] : [];
    $e = static fn(mixed $v): string => admin_e((string)$v);
    $value = static fn(string $key, string $fallback = ''): string => (string)($data[$key] ?? $fallback);

    ob_start(); ?>
    <article class="admin-grid-child" data-grid-child data-grid-child-type="<?= $e($type); ?>" draggable="false">
      <input type="hidden" name="<?= $e($prefix); ?>[type]" value="<?= $e($type); ?>">
      <header class="admin-grid-child__header">
        <button class="admin-grid-child__drag" type="button" data-grid-child-drag draggable="true" aria-label="<?= admin_e(t('Flyt blok')); ?>"><?= admin_icon('grip', 'admin-grid-child__drag-icon'); ?></button>
        <div class="admin-grid-child__identity"><span class="admin-grid-child__type-icon" aria-hidden="true"><?= admin_icon($childIcon, 'admin-grid-child__type-svg'); ?></span><div class="admin-grid-child__heading"><strong><?= $e(t($definition['label'])); ?></strong><span data-grid-child-summary><?= $e(admin_page_builder_grid_child_summary($type, $data)); ?></span></div></div>

        <div class="admin-grid-child__actions"><?= admin_icon_action_button('chevron-down', t('page_builder_v2.expand_block'), ['data-page-child-toggle' => '1', 'aria-expanded' => 'false']); ?><button type="button" data-grid-child-up aria-label="<?= admin_e(t('Flyt op')); ?>"><?= admin_icon('arrow-up'); ?></button><button type="button" data-grid-child-down aria-label="<?= admin_e(t('Flyt ned')); ?>"><?= admin_icon('arrow-down'); ?></button><?= admin_icon_action_button('delete', t('page_builder_v2.delete_block'), ['data-grid-child-remove' => '1'], 'danger'); ?></div>
      </header>
      <div class="admin-grid-child__body" data-page-child-fields hidden>
        <input type="hidden" name="<?= $e($prefix); ?>[status]" value="<?= $e($status); ?>" data-page-child-status>
        <?= AdminToggle::render('page-child-visibility-' . substr(hash('sha256', $prefix), 0, 16), t('Synlighed'), $status !== 'hidden', '', ['data-page-child-visibility' => '1'], ['on'=>t('Vises'), 'off'=>t('Skjult')]); ?>
        <?php if ($type === 'text'): ?>
          <div class="admin-field"><label><?= admin_e(t('Overskrift')); ?></label><input data-grid-child-summary-source name="<?= $e($prefix); ?>[data][title]" value="<?= $e($value('title')); ?>"></div>
          <div class="admin-field"><label><?= admin_e(t('Kicker')); ?></label><input name="<?= $e($prefix); ?>[data][kicker]" value="<?= $e($value('kicker')); ?>"></div>
          <div class="admin-field"><label><?= admin_e(t('Tekst')); ?></label><textarea name="<?= $e($prefix); ?>[data][body]" rows="5"><?= $e($value('body')); ?></textarea></div>
        <?php elseif ($type === 'image'): ?>
          <?= admin_page_builder_media_field($prefix, $data, 'Billede', true); ?>
          <div class="admin-field"><label><?= admin_e(t('Alt-tekst')); ?></label><input name="<?= $e($prefix); ?>[data][image_alt]" value="<?= $e($value('image_alt')); ?>" data-media-picker-alt></div>
          <div class="admin-field"><label><?= admin_e(t('Billedtekst')); ?></label><input name="<?= $e($prefix); ?>[data][caption]" value="<?= $e($value('caption')); ?>"></div>
        <?php elseif ($type === 'text_image'): ?>
          <div class="admin-field"><label><?= admin_e(t('Overskrift')); ?></label><input data-grid-child-summary-source name="<?= $e($prefix); ?>[data][title]" value="<?= $e($value('title')); ?>"></div>
          <div class="admin-field"><label><?= admin_e(t('Kicker')); ?></label><input name="<?= $e($prefix); ?>[data][kicker]" value="<?= $e($value('kicker')); ?>"></div>
          <div class="admin-field"><label><?= admin_e(t('Tekst')); ?></label><textarea name="<?= $e($prefix); ?>[data][body]" rows="4"><?= $e($value('body')); ?></textarea></div>
          <div class="admin-form-row"><?= admin_page_builder_media_field($prefix, $data, 'Billede'); ?><div class="admin-field"><label><?= admin_e(t('Alt-tekst')); ?></label><input name="<?= $e($prefix); ?>[data][image_alt]" value="<?= $e($value('image_alt')); ?>" data-media-picker-alt></div></div>
          <div class="admin-field"><label><?= admin_e(t('Billedplacering')); ?></label><select name="<?= $e($prefix); ?>[data][image_position]"><option value="top" <?= $value('image_position','top') === 'top' ? 'selected' : ''; ?>><?= admin_e(t('Øverst')); ?></option><option value="bottom" <?= $value('image_position') === 'bottom' ? 'selected' : ''; ?>><?= admin_e(t('Nederst')); ?></option></select></div>
        <?php elseif ($type === 'cta'): ?>
          <div class="admin-field"><label><?= admin_e(t('Overskrift')); ?></label><input data-grid-child-summary-source name="<?= $e($prefix); ?>[data][title]" value="<?= $e($value('title')); ?>"></div>
          <div class="admin-field"><label><?= admin_e(t('Kicker')); ?></label><input name="<?= $e($prefix); ?>[data][kicker]" value="<?= $e($value('kicker')); ?>"></div>
          <div class="admin-field"><label><?= admin_e(t('Kort tekst')); ?></label><textarea name="<?= $e($prefix); ?>[data][body]" rows="3"><?= $e($value('body')); ?></textarea></div>
          <div class="admin-form-row"><div class="admin-field"><label><?= admin_e(t('Knaptekst')); ?></label><input name="<?= $e($prefix); ?>[data][button_label]" value="<?= $e($value('button_label')); ?>"></div><div class="admin-field"><label><?= admin_e(t('Knap-URL')); ?></label><input name="<?= $e($prefix); ?>[data][button_url]" value="<?= $e($value('button_url')); ?>"></div></div>
        <?php elseif ($type === 'component'): ?>
          <div class="admin-field"><label><?= admin_e(t('Overskrift')); ?></label><input data-grid-child-summary-source name="<?= $e($prefix); ?>[data][title]" value="<?= $e($value('title')); ?>"></div>
          <div class="admin-field"><label><?= admin_e(t('Kicker')); ?></label><input name="<?= $e($prefix); ?>[data][kicker]" value="<?= $e($value('kicker')); ?>"></div>
          <div class="admin-field"><label><?= admin_e(t('dynamisk komponent')); ?></label><select name="<?= $e($prefix); ?>[data][component]" data-grid-child-component><?php foreach (PageBlocks::componentOptions() as $component=>$label): ?><option value="<?= $e($component); ?>" <?= $value('component') === $component ? 'selected' : ''; ?>><?= $e($label); ?></option><?php endforeach; ?></select><p class="admin-field__help"><?= admin_e(t('Komplekse komponenter kan fylde mere end én smal kolonne. Brug med omtanke i 4–6 kolonner.')); ?></p></div>
          <?= admin_page_builder_gallery_fields($prefix, $data); ?>
        <?php endif; ?>
      </div>
    </article>
    <?php return ob_get_clean();
}

function admin_page_builder_grid_child_summary(string $type, array $data): string
{
    if ($type === 'component') {
        $component = (string)($data['component'] ?? '');
        return PageBlocks::componentOptions()[$component] ?? t('Vælg komponent');
    }
    if ($type === 'image') {
        $path = trim((string)($data['image_path'] ?? ''));
        return $path !== '' ? basename($path) : t('Intet billede valgt');
    }
    $title = trim((string)($data['title'] ?? ''));
    $kicker = trim((string)($data['kicker'] ?? ''));
    return $title !== '' ? $title : ($kicker !== '' ? $kicker : t('pages.builder.no_heading'));
}

function admin_page_builder_summary(string $type, array $data): string
{
    $title = trim((string)($data['title'] ?? ''));
    $kicker = trim((string)($data['kicker'] ?? ''));
    if ($type === 'component') {
        $component = (string)($data['component'] ?? '');
        return PageBlocks::componentOptions()[$component] ?? t('Vælg komponent');
    }
    if ($type === 'grid') {
        $count = max(1, min(6, (int)($data['column_count'] ?? 1)));
        $preset = (string)($data['preset'] ?? 'equal');
        $presetLabel = PageBlocks::gridPresets($count)[$preset] ?? PageBlocks::gridPresets($count)['equal'];
        return t($count === 1 ? 'pages_detail.row_column_singular' : 'pages_detail.row_columns', ['count' => $count]) . ' · ' . t($presetLabel);
    }
    return $title !== '' ? $title : ($kicker !== '' ? $kicker : t('pages.builder.no_heading'));
}

