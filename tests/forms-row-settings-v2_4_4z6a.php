<?php
/** V2.4.4z6a – Form Row settings + safe 1/2-column changes. Read-only. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__);
$view = (string)file_get_contents($root . '/modules/forms/admin/views/partials/form-editor.php');
$coreIcons = (string)file_get_contents($root . '/core/classes/AdminIcons.php');
$translations = json_decode((string)file_get_contents($root . '/modules/forms/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
$passes = 0;
$fails = 0;
$check = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    $condition ? $passes++ : $fails++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
};
foreach (['data-forms-row-settings-dialog','data-forms-row-settings','data-forms-save-row-settings','data-forms-row-layout-choice',
          'data-forms-layout-warning','data-forms-row-layout-label'] as $hook) {
    $check(str_contains($view, $hook), 'Forms Row settings hook: ' . $hook);
}
$check(str_contains($view,"admin_icon_action_button('settings'"), 'Core gear action is used');
$check(str_contains($coreIcons,"'settings' =>"), 'Gear comes from the Core registry');
$check(str_contains($view,"'100' => [1, '100 %'], '50-50' => [2, '50/50']"), 'Only supported layouts are selectable');
$check(str_contains($view,"columns[1].querySelector('[data-forms-field]')"), 'Never shrink a populated second column');
$check(str_contains($view,"const second = document.querySelector('[data-forms-row-template=\"50-50\"]')"), 'Second column uses the existing reusable markup');
$check(str_contains($view,'container.appendChild(second);') && str_contains($view,'columns[1].remove();'), 'Add/remove only the column that changes');
$check(str_contains($view,'layoutInput.value = chosen;') && str_contains($view,'reindex();'), 'Persist the chosen layout using existing Form POST fields');
$check(!str_contains($view,'settingsRow.innerHTML ='), 'Existing field DOM is not reconstructed by settings');
$check(str_contains($view,"settingsReturnFocus.focus({preventScroll: true})"), 'Focus restored on dialog close');
$check(str_contains($view,"if (rowLayoutWarning) {"), 'Blocked change leaves dialog open and displays a warning');
foreach (['da','en','de','sv','nb'] as $locale) {
    foreach (['Row-indstillinger','Vælg antal kolonner for rækken.','Kolonnelayout',
              'Felter i kolonne 2 skal fjernes, før du kan vælge én kolonne.',
              'Kolonne 2 indeholder et felt. Fjern feltet, før du skifter til én kolonne.',
              'Gem indstillinger'] as $key) {
        $check(isset($translations[$key][$locale]) && trim((string)$translations[$key][$locale]) !== '', "$locale translation: $key");
    }
}
$check(file_get_contents($root.'/public_html/admin/assets/scss/admin.scss') === file_get_contents($root.'/public_html/admin/assets/css/admin.css'), 'Shared Core SCSS/CSS remains synchronized');
$check(version_compare((string)(preg_match("/BOILERPLATE_VERSION', '([^']+)'/", (string)file_get_contents($root.'/public_html/incl/init.php'), $matches) ? $matches[1] : ''), '2.4.4.56', '>='), 'Internal release version >= 2.4.4.56');
echo "Forms Row Settings V2.4.4z6a: $passes PASS / $fails FAIL\n";
exit($fails > 0 ? 1 : 0);
