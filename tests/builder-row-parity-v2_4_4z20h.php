<?php
declare(strict_types=1);
/** V2.4.4z20h: shared Row semantics and non-destructive row/field drag. Read-only. */
$root = dirname(__DIR__);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/public_html/admin/incl/functions.php';
if (!function_exists('t')) {
    function t(string $key): string {
        return match ($key) {
            'page_builder_v2.columns' => 'kolonner',
            default => str_starts_with($key, 'forms:') ? substr($key, 6) : $key,
        };
    }
}
require_once $root . '/public_html/admin/incl/page-builder-functions.php';
$passes = 0; $fails = 0;
$check = static function (bool $ok, string $description) use (&$passes, &$fails): void {
    $ok ? ++$passes : ++$fails;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $description . "\n";
};
foreach ([1, 2, 3, 4] as $count) {
    $html = admin_page_builder_block_editor(['type'=>'grid', 'status'=>'active','data'=>[
        'layout_version'=>'v2','column_count'=>$count,'preset'=>'equal',
    ]], (string)$count);
    $label = $count . ' ' . ($count === 1 ? t('Kolonne') : t('page_builder_v2.columns'));
    $check(str_contains($html, 'data-page-layout-label>' . $label), "Page Row count {$count} is text, not percent");
    $check(!str_contains($html, 'data-page-layout-label>50/50') && !str_contains($html, 'data-page-layout-label>100 %'), "Page Row {$count} no percent badge");
    $check(str_contains($html, 'data-builder-drag draggable="true"') && str_contains($html, 'data-builder-block'), "Page Row {$count} retains grip and draggable container");
}
$standalone=admin_page_builder_block_editor(['type'=>'hero','data'=>[]], 'h');
$check(str_contains($standalone,'data-page-layout-label>1 Kolonne'), 'Standalone Page section reports one column');
$check(!str_contains($standalone, 'Grid / kolonner'), 'Page row subtitle no longer shows redundant Grid label');
$check(str_contains($standalone,'data-page-section-hidden-label'), 'Page hidden state marker retained');

// Render real Forms row/field partials after loading the view helpers (no DB).
$fieldTypes=['text'=>'Tekst','email'=>'E-mail'];
$formData=['name'=>'Kontakt','submit_label'=>'Send','status'=>'active'];
$fields=[]; $builderRows=[]; $errors=$publishedPages=$formTemplates=[];
$notificationEmail=''; $isEdit=false; $formId=0;
ob_start();
require $root . '/modules/forms/admin/views/partials/form-editor.php';
$editor=(string)ob_get_clean();
foreach (['100'=>1,'50-50'=>2] as $layout=>$count) {
    $row = forms_admin_builder_row(['layout'=>$layout, 'fields'=>[]], (string)$count, $fieldTypes);
    $label = $count . ' ' . ($count === 1 ? t('Kolonne') : t('page_builder_v2.columns'));
    $check(str_contains($row,'data-forms-row-layout-label>'.$label), "Forms Row {$layout} shows localized column count");
    $check(str_contains($row,'class="admin-builder-block admin-page-row forms-row"'), "Forms Row {$layout} consumes shared Page Row class");
    $check(str_contains($row,'data-forms-row data-row-index=') && str_contains($row,'draggable="true"'), "Forms Row {$layout} draggable like Page Row");
    $check(str_contains($row,'admin-page-row__actions') && str_contains($row,'data-forms-row-settings'), "Forms Row {$layout} shares Core settings/action geometry");
    $check(substr_count($row,'data-forms-column data-column-index=')===$count, "Forms Row {$layout} has unchanged persistence slots");
    $check(!str_contains($row,'100%') && !str_contains($row,'50/50'), "Forms Row {$layout} has no percent badge");
}
$field=forms_admin_field_card(['label'=>'<script>alert(1)</script>', 'field_type'=>'email','field_key'=>'stable_key'], '0', '0', $fieldTypes);
$check(str_contains($field,'data-forms-field draggable="true"'), 'Forms field card is draggable');
$check(str_contains($field,'data-forms-field-drag draggable="true"'), 'Forms field has Page-like drag handle');
$check(str_contains($field,'value="stable_key"') && str_contains($field,'[field_key]'), 'Forms field keeps stable technical key');
$check(!str_contains($field,'<script>alert(1)</script>'), 'Untrusted field names remain escaped');
$check(str_contains($editor,'data-forms-row-template="100"') && str_contains($editor,'data-forms-row-template="50-50"'), 'Existing Forms 1- and 2-column templates preserved');
$check(str_contains($editor,'event.dataTransfer.setDragImage(draggedRow,'), 'Forms row uses full-section drag preview');
$check(str_contains($editor,'event.dataTransfer.setDragImage(draggedField,'), 'Forms field uses full-card drag preview');
$check(str_contains($editor,"rows.insertBefore(draggedRow, next)") && str_contains($editor,'rows.appendChild(draggedRow)'), 'Forms row uses Page midpoint placement logic');
$check(str_contains($editor,"dest.querySelector('[data-forms-field]')"), 'Keyboard move rejects occupied destinations');
$check(str_contains($editor,"existing && existing !== draggedField"), 'Pointer drop rejects occupied destinations');
$check(str_contains($editor,"if (oldEmpty) oldEmpty.hidden = false") && str_contains($editor,"if (newEmpty) newEmpty.hidden = true"), 'Moving field restores both column empty states');
$check(str_contains($editor,'reindex(); // Preserve field_key'), 'Field drag preserves storage keys through reindex');
$check(str_contains($editor,"['ArrowLeft', 'ArrowRight']") && str_contains($editor,'event.altKey'), 'Keyboard alternative for field movement retained');
$check(str_contains($editor,"['ArrowUp', 'ArrowDown']") && str_contains($editor,'event.altKey'), 'Keyboard alternative for row movement retained');
$check(str_contains($editor,"if (event.target.closest('[data-forms-row]')) event.preventDefault()"), 'Row cannot hijack drag selection of nested inputs');
$check(str_contains($editor,'data-forms-row-layout-label') && str_contains($editor,'uiText.twoColumns : uiText.oneColumn'), 'Row settings refreshes count label dynamically');
$pageJs=(string)file_get_contents($root.'/public_html/admin/assets/js/admin.js');
$check(str_contains($pageJs,"window.cmsTranslate('Kolonne')") && str_contains($pageJs,"translatePage('page_builder_v2.columns')"), 'Page JS uses localized singular/plural column labels');
$check(str_contains($pageJs,'data-page-layout-label') && str_contains($pageJs,'rowLayoutLabel(count)'), 'Page layout changes refresh header label');
$scss=(string)file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
$css=(string)file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$check($scss===$css, 'Core SCSS/CSS byte-identical');
$check(str_contains($css,'.admin-page-row__layout { display:block;') && !str_contains($css,'.admin-page-row__layout { display:inline-flex;'), 'Shared Row subtitle is plain text, no badge');
$check(str_contains($css,'forms-field .admin-grid-child__drag'), 'Forms field grip layout is Core-owned');
$check(str_contains($css,'.admin-page-row__heading { display:grid') && str_contains($css,'.admin-page-row__layout { display:block'), 'Shared row heading vertically stacked');
$versionSource = (string) file_get_contents($root . '/public_html/incl/init.php');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9.]+)'/", $versionSource, $versionMatch) === 1 && version_compare($versionMatch[1], '2.4.4.85', '>='), 'z20h minimum compatible version');
$check(!is_file($root.'/.env') || is_file($root.'/public_html/incl/init.php'), 'No configuration required by test');
echo "Builder Row Parity z20h: {$passes} PASS / {$fails} FAIL\n";
exit($fails ? 1 : 0);
