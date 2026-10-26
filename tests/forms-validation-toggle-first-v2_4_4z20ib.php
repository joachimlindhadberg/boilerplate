<?php
/** V2.4.4z20i-b – Validering first; dynamic required status, unchanged field storage. */
declare(strict_types=1);
$root = dirname(__DIR__);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/public_html/admin/incl/functions.php';
if (!function_exists('t')) {
    function t(string $key): string { return str_starts_with($key, 'forms:') ? substr($key, 6) : $key; }
}
$pass = 0;
$fail = 0;
$check = static function(bool $result, string $name) use (&$pass, &$fail): void {
    $result ? ++$pass : ++$fail;
    echo ($result ? 'PASS: ' : 'FAIL: ') . $name . "\n";
};
$types = ['text'=>'Tekst', 'email'=>'E-mail', 'tel'=>'Telefon', 'textarea'=>'Tekstområde', 'select'=>'Dropdown', 'radio'=>'Radio', 'checkbox'=>'Checkbox'];
// Define the production field renderer through the production template (as in prior tests).
$fieldTypes = $types;
$formData = ['status'=>'active'];
$fields = []; $builderRows = []; $formTemplates = []; $errors = [];
$publishedPages = []; $notificationEmail = ''; $isEdit = false; $formId = 0;
ob_start();
require $root . '/modules/forms/admin/views/partials/form-editor.php';
ob_end_clean();

foreach ($types as $type => $label) {
    foreach ([1, 0] as $required) {
        $html = forms_admin_field_card([
            'label' => '<Navn & Test>',
            'field_type' => $type,
            'field_key' => 'stable_key',
            'is_required' => $required,
            'placeholder' => 'Skriv her',
            'help_text' => 'Hjælp',
            'options' => ['Ja','Nej'],
        ], '2', '1', $types);
        $prefix = 'rows[2][fields][1]';
        $stackStart = strpos($html, '<div class="forms-field__editor-stack">');
        $toggle = strpos($html, 'class="admin-field forms-field__validation"');
        $labelField = strpos($html, '<label>Label *');
        $placeholder = strpos($html, '<label>Placeholder');
        $help = strpos($html, '<label>Hjælpetekst');
        $options = strpos($html, '<label>Valgmuligheder');
        $testName = "$type/" . ($required ? 'required' : 'optional');
        $check($stackStart !== false && $toggle !== false && $stackStart < $toggle && $toggle < $labelField, "$testName: Validering is the FIRST field setting");
        $check($labelField !== false && $placeholder !== false && $help !== false && $options !== false
            && $labelField < $placeholder && $placeholder < $help && $help < $options, "$testName: inputs keep their vertical order");
        $check(substr_count($html, 'class="admin-field forms-field__validation"') === 1
            && substr_count($html, 'data-forms-required="1"') === 1, "$testName: exactly one Core validation switch");
        $check(str_contains($html, '<span class="admin-toggle__label">Validering</span>'), "$testName: left control label is Validering");
        $check(str_contains($html, 'data-state-on="Påkrævet" data-state-off="Valgfrit"'), "$testName: status definitions unchanged");
        $check(str_contains($html, $required ? '>Påkrævet</span>' : '>Valgfrit</span>'), "$testName: status matches checked state");
        $check(str_contains($html, 'name="' . $prefix . '[is_required]" value="1"')
            && str_contains($html, 'role="switch"' . ($required ? ' checked' : '')), "$testName: original POST key and value, native switch");
        $check(str_contains($html, 'name="' . $prefix . '[field_key]" value="stable_key"')
            && str_contains($html, 'name="' . $prefix . '[label]"'), "$testName: field key and data payload unchanged");
        $check(str_contains($html, '&lt;Navn &amp; Test&gt;') && !str_contains($html, '<Navn & Test>'), "$testName: user label safely escaped");
    }
}
$source = (string)file_get_contents($root . '/modules/forms/admin/views/partials/form-editor.php');
$js = (string)file_get_contents($root . '/public_html/admin/assets/js/admin.js');
$css = (string)file_get_contents($root . '/public_html/admin/assets/css/admin.css');
$scss = (string)file_get_contents($root . '/public_html/admin/assets/scss/admin.scss');
$check(str_contains($source, 'required.id = id') && str_contains($source, 'label.htmlFor = id'), 'Moved/new field toggle IDs still sync on reindex');
$check(str_contains($js, "document.addEventListener('change'") && str_contains($js, 'data-admin-toggle-state'), 'Core live status handler remains functional');
$check(str_contains($css, '.forms-field__editor-stack { display:grid; grid-template-columns:minmax(0,1fr); gap:16px;'), 'Core vertical layout and 16px inter-field spacing kept');
$check($css === $scss, 'Global Core admin SCSS and compiled CSS byte-identical');
$check((bool)preg_match("/BOILERPLATE_VERSION\', \'([0-9.]+)\'/", (string)file_get_contents($root . '/public_html/incl/init.php'), $versionMatch) && version_compare($versionMatch[1], '2.4.4.88', '>='), 'z20ib minimum version compatibility');
$translations = json_decode((string)file_get_contents($root . '/modules/forms/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
foreach (['da', 'en', 'de', 'sv', 'nb'] as $lang) {
    $check(trim((string)($translations['Validering'][$lang] ?? '')) !== ''
        && trim((string)($translations['Påkrævet'][$lang] ?? '')) !== ''
        && trim((string)($translations['Valgfrit'][$lang] ?? '')) !== '', "All three toggle labels available for $lang");
}
echo "Forms Validation Toggle First z20ib: $pass PASS / $fail FAIL\n";
exit($fail ? 1 : 0);
