<?php

declare(strict_types=1);

/**
 * Non-DB smoke/regression checks for Forms Builder V2 markup and contracts.
 * php tests/forms-builder-v2_4_4u.php
 */
function t(string $key): string { return str_starts_with($key, 'forms:') ? substr($key, 6) : $key; }
function admin_e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_csrf_token(): string { return 'test-csrf-token'; }
require_once dirname(__DIR__) . '/core/classes/Autoload.php';
function admin_icon(string $name, string $class = ''): string { return AdminIcons::render($name, $class); }
function admin_icon_action_button(string $name, string $label, array $attributes = [], string $tone = 'neutral'): string { return AdminIcons::actionButton($name, $label, $attributes, $tone); }

$root = dirname(__DIR__);
$fieldTypes = ['text' => 'Tekst', 'email' => 'E-mail', 'tel' => 'Telefon', 'textarea' => 'Tekstområde', 'select' => 'Dropdown', 'radio' => 'Radio-knapper', 'checkbox' => 'Checkbox'];
$formData = ['name' => 'Test', 'submit_label' => 'Send', 'status' => 'active'];
$fields = [['row_order' => 0, 'column_index' => 0, 'row_layout' => '50-50', 'label' => 'Fornavn', 'field_type' => 'text', 'field_key' => 'fornavn', 'is_required' => 1]];
$builderRows = [['row_order' => 0, 'layout' => '50-50'], ['row_order' => 1, 'layout' => '50-50']];
$errors = $publishedPages = [];
$notificationEmail = '';
$isEdit = false;
$formId = 0;
$deleteDependencies = ['submissions' => 0, 'pages' => 0];
ob_start();
require $root . '/modules/forms/admin/views/partials/form-editor.php';
$html = (string)ob_get_clean();
$tests = [
    'Form Builder header' => str_contains($html, 'Form Builder</h2>'),
    'Header templates trigger' => str_contains($html, 'data-forms-open-templates'),
    'Persistent bottom add box' => str_contains($html, 'data-forms-add-element'),
    'Add box after row-list' => strpos($html, 'data-forms-rows') < strpos($html, 'data-forms-add-element'),
    'Choice dialog' => str_contains($html, 'data-forms-choice-dialog'),
    'Type dialog' => str_contains($html, 'data-forms-type-dialog'),
    'Templates selection dialog' => str_contains($html, 'data-forms-templates-dialog'),
    'No old row select' => !str_contains($html, 'data-forms-new-row-layout'),
    'No old field type dropdown' => !str_contains($html, 'data-forms-new-field-type'),
    'Seven supported type choices' => substr_count($html, 'data-forms-choose-type=') === 7,
    'Empty row persists in markup' => substr_count($html, 'data-row-index="0"') === 1 && substr_count($html, 'data-row-index="1"') === 1,
    'Saved technical field key kept' => str_contains($html, 'value="fornavn" data-forms-field-key'),
    'One populated plus one empty 50/50 row' => (static function () use ($fields, $builderRows) { $grouped = forms_admin_group_rows($fields, $builderRows); return count($grouped) === 2 && count($grouped[0]['fields']) === 1 && count($grouped[1]['fields']) === 0 && $grouped[1]['layout'] === '50-50'; })(),
    'Type selection creates full-width only on confirmation' => str_contains($html, "if (pendingFullWidth) column = addRow('100')"),
    'Existing reorder preserved' => str_contains($html, 'data-forms-row-drag'),
    'Collapsed editor expandable' => str_contains($html, 'data-forms-field-editor hidden'),
    'Native invalid field opens editor' => str_contains($html, "root.addEventListener('invalid'"),
    'Translation keys present for all five languages' => (static function () use ($root) {
        $catalog = json_decode((string)file_get_contents($root . '/modules/forms/lang/translations.json'), true);
        foreach (['Form Builder', 'Skabeloner', 'Vælg felttype', 'Form Row', 'Klik her for at tilføje et formularfelt'] as $key) {
            foreach (['da','en','de','sv','nb'] as $locale) {
                if (trim((string)($catalog[$key][$locale] ?? '')) === '') return false;
            }
        }
        return true;
    })(),
    'Admin SCSS and compiled CSS match' => file_get_contents($root . '/public_html/admin/assets/scss/admin.scss') === file_get_contents($root . '/public_html/admin/assets/css/admin.css'),
    'CMS version metadata' => preg_match("~BOILERPLATE_VERSION', '([0-9.]+)'~", (string)file_get_contents($root . '/public_html/incl/init.php'), $cmsVersionMatch) === 1 && version_compare($cmsVersionMatch[1], '2.4.4.31', '>='),
];
$failures = 0;
foreach ($tests as $label => $pass) {
    echo ($pass ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    if (!$pass) $failures++;
}
echo sprintf("Forms Builder V2 smoke: %d PASS / %d FAIL\n", count($tests) - $failures, $failures);
exit($failures ? 1 : 0);
