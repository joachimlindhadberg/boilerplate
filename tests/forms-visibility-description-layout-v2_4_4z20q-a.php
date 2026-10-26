<?php
/** V2.4.4z20q-a: Forms toggle help text lives between the visibility card and save checkbox. */
declare(strict_types=1);
$root = dirname(__DIR__);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/public_html/admin/incl/functions.php';
require_once $root . '/public_html/admin/incl/page-builder-functions.php';
if (!function_exists('t')) { function t(string $key, array $params = []): string { return Translator::get($key, $params); } }

$pass = 0; $fail = 0;
$check = static function (bool $ok, string $name) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . "\n";
    $ok ? ++$pass : ++$fail;
};

$source = (string)file_get_contents($root . '/modules/forms/admin/views/partials/form-editor.php');
$check(str_contains($source, "AdminToggle::render('forms-visibility'"), 'Forms uses shared Core toggle');
$check(str_contains($source, "'inactive', '', ['data-forms-visibility-switch'"), 'Forms does not inject description inside toggle');
$check(str_contains($source, '<span class="admin-toggle__description">'), 'One external help span is rendered with Core class');
$check(str_contains($source, "admin_e(t('forms:Kun aktive formularer"), 'Description is translated and escaped');
$check(str_contains($source, "formsStatus.value = formsVisibility.checked ? 'active' : 'inactive'"), 'Status POST behavior remains unchanged');
$check(str_contains($source, "root.addEventListener('submit', syncFormsVisibility)"), 'Submit synchronizes existing hidden status');

$sidebarStart = strpos($source, '    <aside class="admin-detail-sidebar">');
$sectionStart = strpos($source, '      <section class="admin-panel">', $sidebarStart);
$sectionEnd = strpos($source, "      <section class=\"admin-panel\"><div class=\"admin-panel__header\"><h2><?= admin_e(t('forms:Brug på sider'))", $sectionStart);
if ($sidebarStart === false || $sectionStart === false || $sectionEnd === false) { throw new RuntimeException('Cannot isolate real Forms status panel'); }
$sectionTemplate = substr($source, $sectionStart, $sectionEnd - $sectionStart);
$render = static function (string $locale, string $status, int $save) use ($root, $sectionTemplate): string {
    Translator::boot($locale);
    Translator::registerModule('forms', $root . '/modules/forms');
    $formData = ['status' => $status, 'save_submissions' => $save];
    ob_start();
    try { eval('?>' . $sectionTemplate); return (string) ob_get_clean(); }
    catch (Throwable $e) { ob_end_clean(); throw $e; }
};

$catalog = json_decode((string)file_get_contents($root.'/modules/forms/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
$key = 'Kun aktive formularer kan vælges i Page Builder og sendes fra frontend.';
foreach (['da','en','de','sv','nb'] as $locale) {
    foreach (['active','inactive'] as $status) {
        $save = $status === 'active' ? 1 : 0;
        $html = $render($locale, $status, $save);
        $expected = htmlspecialchars($catalog[$key][$locale], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $check(substr_count($html, '<span class="admin-toggle__description">') === 1, "$locale/$status: exactly one standalone description");
        $check(str_contains($html, '<span class="admin-toggle__description">'.$expected.'</span>'), "$locale/$status: existing translation preserved");
        $check((bool)preg_match('~<label class="admin-toggle" for="forms-visibility">(.*?)</label>\s*<span class="admin-toggle__description">(.*?)</span>\s*<label class="admin-checkbox">~s', $html, $matches), "$locale/$status: exact element order");
        $check(isset($matches[1]) && !str_contains($matches[1], 'admin-toggle__description'), "$locale/$status: help is outside clickable label");
        $check(str_contains($html, 'name="status" value="'.$status.'" data-forms-status-value'), "$locale/$status: original status payload retained");
        $check((bool)preg_match('~id="forms-visibility" type="checkbox" role="switch"'.($status==='active'?' checked':'').'~', $html), "$locale/$status: switch state matches stored value");
        $check(str_contains($html, 'data-admin-toggle-state') && str_contains($html, 'data-forms-visibility-switch="1"'), "$locale/$status: live state and change hook retained");
        $check(str_contains($html, 'name="save_submissions" value="1"'.($save?' checked':'')), "$locale/$status: independent checkbox payload retained");
    }
}
$scss=(string)file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
$css=(string)file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$check($scss===$css,'Core SCSS/CSS byte-identical');
$check(str_contains($css,'.admin-form-section {') && str_contains($css,'  display: grid;'), 'Core form section stacks individual elements');
$check(str_contains($css,'.admin-toggle__description { font-size:12px;'), 'Existing Core description style reused');
$check(!str_contains($source, 'aria-describedby="forms-visibility-description"'), 'No invalid override of protected Core toggle attributes');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", (string)file_get_contents($root.'/public_html/incl/init.php'), $v) === 1 && version_compare($v[1], '2.4.4.106', '>='),'Hotfix remains available since 2.4.4.106');
printf("Forms Visibility Description Layout z20q-a: %d PASS / %d FAIL\n",$pass,$fail);
exit($fail ? 1 : 0);
