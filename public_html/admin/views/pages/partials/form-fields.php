<?php
$pageRecord = is_array($pageRecord ?? null) ? $pageRecord : [];
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$parents = is_array($parents ?? null) ? $parents : [];
$builderBlocks = is_array($builderBlocks ?? null) ? $builderBlocks : [];
$isEdit = !empty($isEdit);
$isSystem = !empty($isSystem);
$pageId = (int)($pageRecord['id'] ?? 0);
$contentMode = (($form['content_mode'] ?? 'builder') === 'legacy') ? 'legacy' : 'builder';
$fieldError = static fn(string $key): string => t((string)($errors[$key] ?? ''));
$definitions = PageBlocks::definitions();
$pageTemplates = PageTemplateCatalog::all();
ob_start();
?>
<div class="admin-detail-back"><a class="admin-back-link" href="/admin/sider"><?= admin_icon('arrow-left', 'admin-back-link__icon'); ?><?= admin_e(t('Tilbage')); ?></a></div>
<div class="admin-page-titlebar admin-page-titlebar--split"><div><h1><?= admin_e(t($isEdit ? 'Rediger side' : 'Ny side')); ?></h1><p><?= admin_e(t($isEdit ? 'pages.form_edit_intro' : 'pages.form_new_intro')); ?></p></div><?php if ($isEdit): ?><div class="admin-page-actions"><a class="admin-button admin-button--secondary" href="/admin/sider?mode=history&amp;id=<?= $pageId; ?>"><?= admin_e(t('Versionshistorik')); ?></a><a class="admin-button admin-button--secondary" href="<?= admin_e(admin_page_public_url($pageRecord)); ?>" target="_blank" rel="noopener noreferrer"><?= admin_icon('external', 'admin-button__icon'); ?><?= admin_e(t('Se side')); ?></a></div><?php endif; ?></div>
<?php if (!empty($notice)): ?><div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div><?php endif; ?>
<?php if ($fieldError('form') !== ''): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$fieldError('form'))); ?></div><?php endif; ?>
<?php if ($fieldError('builder') !== ''): ?><div class="admin-notice admin-notice--error" role="alert"><strong>Page Builder:</strong> <?= admin_e(t((string)$fieldError('builder'))); ?></div><?php endif; ?>
<?php if ($isSystem): ?><div class="admin-notice" role="status"><strong><?= admin_e(t('Systemside:')); ?></strong> <?= admin_e(t('Slug, parent og permanent sletning er låst for at beskytte faste routes. Du kan stadig skifte mellem Legacy HTML og Page Builder.')); ?></div><?php endif; ?>
<form class="admin-page-form" method="post" action="<?= $isEdit ? '/admin/sider?mode=edit&amp;id='.$pageId : '/admin/sider?mode=new'; ?>" novalidate data-page-builder-form data-admin-track-changes<?= $isEdit ? ' data-page-save-scroll="' . $pageId . '"' : ''; ?>>
  <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>"><?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $pageId; ?>"><?php endif; ?>
  <!-- Legacy fields preserved for non-destructive upgrades; no longer drive the frontend navigation. -->
  <input type="hidden" name="show_in_menu" value="<?= admin_e($form['show_in_menu'] ?? '0'); ?>">
  <input type="hidden" name="menu_order" value="<?= admin_e($form['menu_order'] ?? ''); ?>">
  <div class="admin-detail-grid">
    <div class="admin-detail-content">
      <section class="admin-panel"><div class="admin-panel__header"><h2><?= admin_e(t('Grundlæggende')); ?></h2></div><div class="admin-form-section">
        <div class="admin-form-row">
          <div class="admin-field"><label for="page-title"><?= admin_e(t('Sidetitel *')); ?></label><input id="page-title" name="page_title" maxlength="255" value="<?= admin_e($form['page_title'] ?? ''); ?>" required><?php if ($fieldError('page_title')): ?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('page_title'))); ?></p><?php endif; ?></div>
          <div class="admin-field"><label for="page-slug"><?= admin_e(t('Slug')); ?></label><input id="page-slug" name="page_slug" maxlength="255" value="<?= admin_e($form['page_slug'] ?? ''); ?>" placeholder="<?= admin_e(t('ui.generated_automatically')); ?>" <?= $isSystem ? 'readonly' : ''; ?>><p class="admin-field__help"><?= admin_e(t($isSystem ? 'pages.system_locked' : 'pages.slug_auto')); ?></p><?php if ($fieldError('page_slug')): ?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('page_slug'))); ?></p><?php endif; ?></div>
        </div>
        <div class="admin-field"><label for="page-description"><?= admin_e(t('Kort beskrivelse')); ?></label><textarea id="page-description" name="page_description" rows="3"><?= admin_e($form['page_description'] ?? ''); ?></textarea><p class="admin-field__help"><?= admin_e(t('Bruges som intern/sidebeskrivelse og kan indgå i metadata afhængigt af frontend.')); ?></p></div>
      </div></section>

      <section class="admin-panel admin-page-builder-panel">
        <div class="admin-panel__header admin-panel__header--builder"><div><h2><?= admin_e(t('Page Builder')); ?></h2><p><?= admin_e(t('page_builder_v2.subtitle')); ?></p></div><button class="admin-button admin-button--secondary admin-button--compact" type="button" data-page-templates-open><?= admin_e(t('Skabeloner')); ?></button></div>
        <div class="admin-form-section">
          <div class="admin-content-mode" role="group" aria-labelledby="content-mode-label">
            <div><strong id="content-mode-label"><?= admin_e(t('Editor')); ?></strong><span><?= admin_e(t('Vælg hvordan denne sides indhold rendres.')); ?></span></div>
            <label><input type="radio" name="content_mode" value="builder" <?= $contentMode === 'builder' ? 'checked' : ''; ?> data-content-mode> <?= admin_e(t('Page Builder')); ?></label>
            <label><input type="radio" name="content_mode" value="legacy" <?= $contentMode === 'legacy' ? 'checked' : ''; ?> data-content-mode> <?= admin_e(t('Legacy HTML')); ?></label>
          </div>

          <div class="admin-builder-mode" data-builder-mode="builder" <?= $contentMode !== 'builder' ? 'hidden' : ''; ?>>
            <?php if ($isEdit && (($pageRecord['content_mode'] ?? 'legacy') === 'legacy')): ?>
              <div class="admin-notice admin-notice--info"><strong><?= admin_e(t('Sikker overgang:')); ?></strong> <?= admin_e(t('Den eksisterende HTML bliver bevaret i databasen. Page Builder overtager først frontenden, når du gemmer siden i Page Builder-mode. Du kan senere skifte tilbage til Legacy HTML.')); ?></div>
            <?php endif; ?>
            <div class="admin-builder-list" data-builder-list>
              <?php foreach ($builderBlocks as $index=>$block): ?><?= admin_page_builder_block_editor($block, (string)$index); ?><?php endforeach; ?>
            </div>
            <div class="admin-builder-empty" data-builder-empty <?= $builderBlocks ? 'hidden' : ''; ?>>
              <strong><?= admin_e(t('page_builder_v2.no_rows')); ?></strong>
              <span><?= admin_e(t('page_builder_v2.start_hint')); ?></span>
            </div>
            <button class="admin-page-add-box" type="button" data-builder-add>
              <?= admin_icon('plus', 'admin-page-add-box__plus'); ?>
              <span><?= admin_e(t('page_builder_v2.add_content')); ?></span>
            </button>
            <p class="admin-builder-note"><?= admin_e(t('page_builder_v2.compat_note')); ?></p>
          </div>

          <div class="admin-builder-mode admin-page-content-editor" data-builder-mode="legacy" <?= $contentMode !== 'legacy' ? 'hidden' : ''; ?>>
            <div class="admin-notice admin-notice--info"><strong>Legacy HTML:</strong> <?= admin_e(t('notice.legacy_html_help')); ?></div>
            <div class="admin-field"><label for="page-content"><?= admin_e(t('HTML / komponenter')); ?></label><textarea id="page-content" name="page_content" rows="24" spellcheck="false"><?= admin_e($form['page_content'] ?? ''); ?></textarea></div>
          </div>
        </div>
      </section>

      <section class="admin-panel"><div class="admin-panel__header"><h2><?= admin_e(t('SEO')); ?></h2></div><div class="admin-form-section">
        <div class="admin-field"><label for="meta-title"><?= admin_e(t('SEO-titel')); ?></label><input id="meta-title" name="meta_title" maxlength="255" value="<?= admin_e($form['meta_title'] ?? ''); ?>"></div>
        <div class="admin-field"><label for="meta-description"><?= admin_e(t('SEO-beskrivelse')); ?></label><textarea id="meta-description" name="meta_description" rows="4"><?= admin_e($form['meta_description'] ?? ''); ?></textarea></div>
      </div></section>
    </div>
    <aside class="admin-detail-sidebar">
      <section class="admin-panel"><div class="admin-panel__header"><h2><?= admin_e(t('Publicering')); ?></h2></div><div class="admin-form-section">
        <div class="admin-field"><label for="page-status"><?= admin_e(t('Status')); ?></label><select id="page-status" name="page_status"><?php foreach (['draft','published','protected'] as $value): ?><option value="<?= $value; ?>" <?= ($form['page_status'] ?? 'draft') === $value ? 'selected' : ''; ?>><?= admin_e(admin_page_status_label($value)); ?></option><?php endforeach; ?></select><?php if ($fieldError('page_status')): ?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('page_status'))); ?></p><?php endif; ?></div>
        <div class="admin-field"><label for="schedule-publish"><?= admin_e(t('Publicér automatisk')); ?></label><input id="schedule-publish" type="datetime-local" name="schedule_publish" value="<?= admin_e($form['schedule_publish'] ?? ''); ?>"><p class="admin-field__help"><?= admin_e(t('Kun for kladder. Siden forbliver skjult indtil tidspunktet.')); ?></p><?php if ($fieldError('schedule_publish')): ?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('schedule_publish'))); ?></p><?php endif; ?></div>
        <div class="admin-field"><label for="schedule-unpublish"><?= admin_e(t('Afpublicér automatisk')); ?></label><input id="schedule-unpublish" type="datetime-local" name="schedule_unpublish" value="<?= admin_e($form['schedule_unpublish'] ?? ''); ?>"><p class="admin-field__help"><?= admin_e(t('Kun for publicerede sider eller sider med en kommende publicering.')); ?></p><?php if ($fieldError('schedule_unpublish')): ?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('schedule_unpublish'))); ?></p><?php endif; ?></div>
        <p class="admin-field__help"><?= admin_e(t('pages.schedule_timezone', ['timezone' => date_default_timezone_get()])); ?></p>
        <p class="admin-field__help"><?= admin_e(t('Menupunkter og rækkefølge administreres under')); ?> <a href="/admin/navigation"><?= admin_e(t('Navigation')); ?></a>.</p>
      </div></section>
      <section class="admin-panel"><div class="admin-panel__header"><h2><?= admin_e(t('Hierarki')); ?></h2></div><div class="admin-form-section">
        <div class="admin-field"><label for="parent-id"><?= admin_e(t('Overordnet side')); ?></label><select id="parent-id" name="parent_id" <?= $isSystem ? 'disabled' : ''; ?>><option value=""><?= admin_e(t('Ingen')); ?></option><?php foreach ($parents as $parent): ?><option value="<?= (int)$parent['id']; ?>" <?= (string)($form['parent_id'] ?? '') === (string)$parent['id'] ? 'selected' : ''; ?>><?= admin_e((string)$parent['page_title']); ?> (<?= admin_e((string)$parent['page_slug']); ?>)</option><?php endforeach; ?></select><?php if ($isSystem): ?><input type="hidden" name="parent_id" value="<?= admin_e($form['parent_id'] ?? ''); ?>"><?php endif; ?><p class="admin-field__help"><?= admin_e(t($isSystem ? 'pages.system_locked' : 'pages.parent_help')); ?></p><?php if ($fieldError('parent_id')): ?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('parent_id'))); ?></p><?php endif; ?></div>
      </div></section>
      <?php if ($isEdit): require __DIR__ . '/delete-panel.php'; endif; ?>
      <div class="admin-form-actions"><a class="admin-button admin-button--secondary" href="/admin/sider"><?= admin_e(t('Annuller')); ?></a><button class="admin-button" type="submit"><?= $isEdit ? '' : admin_icon('plus', 'admin-button__icon'); ?><?= admin_e(t($isEdit ? 'Gem ændringer' : 'pages.create_page')); ?></button></div>
    </aside>
  </div>

  <?php foreach ($definitions as $type=>$definition): ?>
    <template data-builder-template="<?= admin_e($type); ?>"><?= admin_page_builder_block_editor(['type'=>$type,'status'=>'active','data'=>$type === 'grid' ? ['column_count'=>2,'preset'=>'equal','layout_version'=>'v2'] : []], '__INDEX__'); ?></template>
  <?php endforeach; ?>
  <template data-builder-template="grid-1"><?= admin_page_builder_block_editor(['type'=>'grid','status'=>'active','data'=>['column_count'=>1,'preset'=>'equal','layout_version'=>'v2']], '__INDEX__'); ?></template>
  <template data-builder-template="grid-4"><?= admin_page_builder_block_editor(['type'=>'grid','status'=>'active','data'=>['column_count'=>4,'preset'=>'equal','layout_version'=>'v2']], '__INDEX__'); ?></template>
  <template data-builder-template="grid-3"><?= admin_page_builder_block_editor(['type'=>'grid','status'=>'active','data'=>['column_count'=>3,'preset'=>'equal','layout_version'=>'v2']], '__INDEX__'); ?></template>
  <?php foreach ([5, 6] as $count): ?>
    <template data-builder-template="grid-<?= $count; ?>"><?= admin_page_builder_block_editor(['type'=>'grid','status'=>'active','data'=>['column_count'=>$count,'preset'=>'equal','layout_version'=>'v2']], '__INDEX__'); ?></template>
  <?php endforeach; ?>
  <?php foreach ($pageTemplates as $pageTemplate): ?>
    <template data-page-template-content="<?= admin_e($pageTemplate['id']); ?>">
      <?php foreach ($pageTemplate['blocks'] as $templateIndex=>$templateBlock): ?><?= admin_page_builder_block_editor($templateBlock, (string)$templateIndex); ?><?php endforeach; ?>
    </template>
  <?php endforeach; ?>
</form>
<?php if ($isEdit): $id = $pageId; $deleteReturnMode = 'edit'; require __DIR__ . '/delete-modal.php'; endif; ?>
<script src="<?= admin_e(admin_asset('/admin/assets/js/admin-page-workflow.js')); ?>" defer></script>

<dialog class="admin-modal admin-modal--sm" data-admin-modal data-page-section-settings-dialog aria-labelledby="page-section-settings-title">
  <div class="admin-modal__surface">
    <div class="admin-modal__header"><div><h2 id="page-section-settings-title"><?= admin_e(t('page_builder_v2.section_settings')); ?></h2><p><?= admin_e(t('page_builder_v2.section_settings_help')); ?></p></div><button class="admin-modal__close" type="button" data-modal-close aria-label="<?= admin_e(t('Luk')); ?>">×</button></div>
    <div class="admin-modal__body admin-page-section-settings-fields">
      <div class="admin-page-section-visibility">
        <?= admin_toggle_switch('page-section-visible', t('Synlighed'), true, t('page_builder_v2.visibility_help'), ['data-page-section-visibility' => '1'], ['on'=>t('Vises'), 'off'=>t('Skjult')]); ?>
      </div>
      <fieldset class="admin-page-row-layout-picker" data-page-row-layout-field hidden>
        <legend><?= admin_e(t('page_builder_v2.layout_columns')); ?></legend>
        <div class="admin-page-row-layout-picker__options">
          <?php foreach (range(1, 6) as $number): $label = $number . ' ' . t($number === 1 ? 'Kolonne' : 'page_builder_v2.columns'); ?>
          <label class="admin-page-row-layout-choice"><input type="radio" name="page_row_layout" value="<?= $number; ?>" data-page-row-layout-choice><span class="admin-page-row-layout-choice__graphic" aria-hidden="true"><?php for ($i=0;$i<$number;$i++): ?><span></span><?php endfor; ?></span><span><?= admin_e($label); ?></span></label>
          <?php endforeach; ?>
        </div>
        <p class="admin-field__help"><?= admin_e(t('page_builder_v2.layout_change_help')); ?></p>
        <p class="admin-field-error" role="alert" data-page-layout-warning hidden></p>
      </fieldset>
      <div class="admin-field"><label for="page-section-width"><?= admin_e(t('page_builder_v2.container_width')); ?></label><select id="page-section-width" data-page-section-width><?php foreach (PageBlocks::containerWidths() as $width=>$name): ?><option value="<?= admin_e($width); ?>"><?= admin_e(t('page_builder_v2.width_' . $width)); ?></option><?php endforeach; ?></select><p class="admin-field__help"><?= admin_e(t('page_builder_v2.container_width_help')); ?></p></div>
      <div class="admin-field" data-page-section-gap-field hidden><label for="page-section-gap"><?= admin_e(t('Afstand')); ?></label><select id="page-section-gap" data-page-section-gap><?php foreach (PageBlocks::gridGaps() as $gap=>$name): ?><option value="<?= admin_e($gap); ?>"><?= admin_e(t($name)); ?></option><?php endforeach; ?></select></div>

      <div class="admin-field"><label for="page-section-theme"><?= admin_e(t('page_builder_v2.color_theme')); ?></label><select id="page-section-theme" data-page-section-theme><?php foreach (PageBlocks::themes() as $theme=>$name): ?><option value="<?= admin_e($theme); ?>"><?= admin_e(t('page_builder_v2.theme_' . $theme)); ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="admin-modal__footer"><button class="admin-button admin-button--secondary" type="button" data-modal-close><?= admin_e(t('Annuller')); ?></button><button class="admin-button" type="button" data-page-section-save><?= admin_e(t('page_builder_v2.save_settings')); ?></button></div>
  </div>
</dialog>
<dialog class="admin-modal" data-admin-modal data-page-layout-dialog aria-labelledby="page-layout-title">
  <div class="admin-modal__surface">
    <div class="admin-modal__header"><div><h2 id="page-layout-title"><?= admin_e(t('page_builder_v2.add_dialog_title')); ?></h2><p><?= admin_e(t('page_builder_v2.add_dialog_help')); ?></p></div><button class="admin-modal__close" type="button" data-modal-close aria-label="<?= admin_e(t('Luk')); ?>">×</button></div>
    <div class="admin-modal__body admin-page-add-dialog-body">
      <div class="admin-page-choice-group">
        <h3><?= admin_e(t('page_builder_v2.sections_group')); ?></h3>
        <div class="admin-page-choice-list admin-page-choice-list--sections">
          <?php foreach (PageBlocks::definitions() as $sectionType=>$section): if ($sectionType === 'grid') continue; ?>
            <button type="button" class="admin-page-choice" data-page-add-section="<?= admin_e($sectionType); ?>"><strong><?= admin_e(t((string)$section['label'])); ?></strong><span><?= admin_e(t((string)$section['description'])); ?></span></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="admin-page-choice-group">
        <h3><?= admin_e(t('page_builder_v2.layouts_group')); ?></h3>
        <div class="admin-page-choice-list admin-page-choice-list--layouts">
          <button type="button" class="admin-page-choice" data-page-add-layout="100"><strong>1 <?= admin_e(t('Kolonne')); ?></strong><span><?= admin_e(t('page_builder_v2.full_width_help')); ?></span></button>
          <button type="button" class="admin-page-choice" data-page-add-layout="50"><strong>2 <?= admin_e(t('page_builder_v2.columns')); ?></strong><span><?= admin_e(t('page_builder_v2.half_width_help')); ?></span></button>
          <button type="button" class="admin-page-choice" data-page-add-layout="33"><strong>3 <?= admin_e(t('page_builder_v2.columns')); ?></strong><span><?= admin_e(t('page_builder_v2.third_width_help')); ?></span></button>
          <button type="button" class="admin-page-choice" data-page-add-layout="25"><strong>4 <?= admin_e(t('page_builder_v2.columns')); ?></strong><span><?= admin_e(t('page_builder_v2.fourth_width_help')); ?></span></button>
          <?php foreach ([5 => '20', 6 => '16'] as $count=>$key): ?>
          <button type="button" class="admin-page-choice" data-page-add-layout="<?= $key; ?>"><strong><?= $count; ?> <?= admin_e(t('page_builder_v2.columns')); ?></strong><span><?= admin_e(t('page_builder_v2.equal_width_help')); ?></span></button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="admin-modal__footer"><button type="button" class="admin-button admin-button--secondary" data-modal-close><?= admin_e(t('Annuller')); ?></button></div>
  </div>
</dialog>
<dialog class="admin-modal" data-admin-modal data-page-block-dialog aria-labelledby="page-block-title">
  <div class="admin-modal__surface">
    <div class="admin-modal__header"><div><h2 id="page-block-title"><?= admin_e(t('page_builder_v2.choose_block')); ?></h2><p><?= admin_e(t('page_builder_v2.choose_block_help')); ?></p></div><button class="admin-modal__close" type="button" data-modal-close aria-label="<?= admin_e(t('Luk')); ?>">×</button></div>
    <div class="admin-modal__body"><div class="admin-page-choice-list" data-page-block-choices="top">
      <?php foreach (PageBlocks::definitions() as $blockType=>$definition): if ($blockType === 'grid') continue; ?>
        <button type="button" class="admin-page-choice" data-page-block-choice="<?= admin_e($blockType); ?>"><strong><?= admin_e(t((string)$definition['label'])); ?></strong><span><?= admin_e(t((string)$definition['description'])); ?></span></button>
      <?php endforeach; ?>
    </div><div class="admin-page-choice-list" data-page-block-choices="child" hidden>
      <?php foreach (PageBlocks::gridChildDefinitions() as $blockType=>$definition): ?>
        <button type="button" class="admin-page-choice" data-page-block-choice="<?= admin_e($blockType); ?>"><strong><?= admin_e(t((string)$definition['label'])); ?></strong><span><?= admin_e(t((string)$definition['description'])); ?></span></button>
      <?php endforeach; ?>
    </div></div>
    <div class="admin-modal__footer"><button type="button" class="admin-button admin-button--secondary" data-modal-close><?= admin_e(t('Annuller')); ?></button></div>
  </div>
</dialog>
<dialog class="admin-modal admin-modal--lg" data-admin-modal data-page-templates-dialog aria-labelledby="page-templates-title">
  <div class="admin-modal__surface">
    <div class="admin-modal__header"><div><h2 id="page-templates-title"><?= admin_e(t('page_templates.modal_title')); ?></h2><p><?= admin_e(t('page_templates.modal_help')); ?></p></div><button type="button" class="admin-modal__close" data-modal-close aria-label="<?= admin_e(t('Luk')); ?>">×</button></div>
    <div class="admin-modal__body">
      <div class="admin-page-templates">
        <div class="admin-page-templates__list" role="group" aria-label="<?= admin_e(t('page_templates.choose')); ?>">
          <h3><?= admin_e(t('page_templates.list_label')); ?></h3>
          <?php foreach ($pageTemplates as $templateIndex=>$pageTemplate): ?>
            <button class="admin-page-template-choice<?= $templateIndex === 0 ? ' is-selected' : ''; ?>" type="button" data-page-template-choice="<?= admin_e($pageTemplate['id']); ?>" aria-pressed="<?= $templateIndex === 0 ? 'true' : 'false'; ?>" aria-controls="page-template-preview-<?= admin_e($pageTemplate['id']); ?>">
              <span class="admin-page-template-choice__name"><?= admin_e($pageTemplate['title']); ?></span>
              <span class="admin-page-template-choice__count"><?= admin_e(t('page_templates.sections_count', ['count'=>count($pageTemplate['blocks'])])); ?></span>
              <span class="admin-page-template-choice__check" aria-hidden="true"><?= admin_icon('check'); ?></span>
            </button>
          <?php endforeach; ?>
        </div>
        <div class="admin-page-templates__preview">
          <h3><?= admin_e(t('page_templates.preview_label')); ?></h3>
          <?php foreach ($pageTemplates as $templateIndex=>$pageTemplate): ?>
            <section id="page-template-preview-<?= admin_e($pageTemplate['id']); ?>" class="admin-page-template-detail" data-page-template-preview="<?= admin_e($pageTemplate['id']); ?>" <?= $templateIndex !== 0 ? 'hidden' : ''; ?>>
              <div class="admin-page-template-wireframe" aria-label="<?= admin_e($pageTemplate['title']); ?>">
                <?php foreach ($pageTemplate['preview_blocks'] as $previewBlock): ?>
                  <?php $previewType = $previewBlock['type']; ?>
                  <div class="admin-page-template-wireframe__section admin-page-template-wireframe__section--<?= admin_e($previewType); ?>">
                    <?php if ($previewType === 'grid'): ?>
                      <span class="admin-page-template-wireframe__kind"><?= admin_e($previewBlock['label']); ?> · <?= count($previewBlock['columns']); ?> <?= admin_e(t('page_builder_v2.columns')); ?></span>
                      <div class="admin-page-template-wireframe__columns">
                        <?php foreach ($previewBlock['columns'] as $columnLabel): ?>
                          <div class="admin-page-template-wireframe__column"><span><?= admin_e($columnLabel); ?></span></div>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <span class="admin-page-template-wireframe__kind"><?= admin_e($previewBlock['label']); ?></span>
                      <?php if ($previewType === 'text_image'): ?><span class="admin-page-template-wireframe__image" aria-hidden="true"><?= admin_icon('image'); ?></span><?php endif; ?>
                      <strong><?= admin_e($previewBlock['title'] !== '' ? $previewBlock['title'] : $previewBlock['label']); ?></strong>
                      <?php if ($previewType === 'hero' || $previewType === 'intro' || $previewType === 'text'): ?><span class="admin-page-template-wireframe__line" aria-hidden="true"></span><?php endif; ?>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="admin-page-template-detail__description"><strong><?= admin_e($pageTemplate['title']); ?></strong><p><?= admin_e($pageTemplate['description']); ?></p></div>
            </section>
          <?php endforeach; ?>
          <p class="admin-field__help"><?= admin_e(t('page_templates.preview_note')); ?></p>
        </div>
      </div>
      <p class="admin-field__help admin-page-templates__note"><?= admin_e(t('page_templates.note')); ?></p>
      <p class="admin-field__help admin-page-templates__note"><?= admin_e(t('page_templates.replace_notice')); ?></p>
    </div>
    <div class="admin-modal__footer"><button type="button" class="admin-button admin-button--secondary" data-modal-close><?= admin_e(t('Annuller')); ?></button><button type="button" class="admin-button" data-page-template-apply><?= admin_e(t('page_templates.use')); ?></button></div>
  </div>
</dialog>
<dialog class="admin-modal admin-modal--lg" data-admin-modal data-media-picker-dialog aria-labelledby="media-picker-title">
  <div class="admin-modal__surface admin-modal__surface--clip">
    <div class="admin-modal__header">
      <div><h2 id="media-picker-title"><?= admin_e(t('Vælg billede')); ?></h2><p><?= admin_e(t('Vælg et billede fra Medier. Alt-tekst udfyldes automatisk, hvis den findes.')); ?></p></div>
      <button type="button" class="admin-modal__close" data-modal-close aria-label="<?= admin_e(t('Luk')); ?>">×</button>
    </div>
    <div class="admin-media-picker-toolbar">
      <label><span class="sr-only"><?= admin_e(t('Søg billeder')); ?></span><?= admin_icon('search','admin-list-search__icon'); ?><input type="search" placeholder="<?= admin_e(t('Søg billeder…')); ?>" autocomplete="off" data-media-picker-search></label>
    </div>
    <div class="admin-modal__body">
      <div class="admin-media-picker-grid" data-media-picker-grid></div>
      <div class="admin-empty-state" data-media-picker-empty hidden><?= admin_icon('image','admin-empty-state__icon'); ?><strong><?= admin_e(t('Ingen billeder fundet')); ?></strong><span><?= admin_e(t('Upload billeder under Medier eller prøv en anden søgning.')); ?></span></div>
      <div class="admin-media-picker-loading" data-media-picker-loading><?= admin_e(t('Indlæser billeder…')); ?></div>
    </div>
  </div>
</dialog>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = t($isEdit ? 'Rediger side' : 'Ny side');

