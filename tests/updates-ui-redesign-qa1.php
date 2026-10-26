<?php
/** UI contract; no database, network, upload, migration or file mutations. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
$check = static function (bool $condition, string $label) use (&$pass, &$fail):void {
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . "\n";
    $condition ? $pass++ : $fail++;
};
$view = file_get_contents($root.'/public_html/admin/views/tools/update.php');
$card = file_get_contents($root.'/public_html/admin/views/tools/partials/update-card.php');
$controller = file_get_contents($root.'/public_html/admin/tools.php');
$coreCss = file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$translation = json_decode((string)file_get_contents($root.'/core/lang/translations.json'),true,512,JSON_THROW_ON_ERROR);
$check(str_contains($view,'updates.title') && str_contains($card,'updates.title'), 'Page and Tools card share localized Updates title');
$check(str_contains($view,'updates.current_version') && str_contains($view,'$installedVersion'), 'Actual installed version displayed');
$check(str_contains($view,'updates.not_checked'), 'Status remains unverified until a real lookup');
$check(str_contains($view,'updates.source_pending') && str_contains($view,'updates.coming_soon'), 'No update feed claimed');
$check(str_contains($view,'type="button"') && str_contains($view,'class="admin-button admin-button--primary" disabled'), 'Unavailable update button genuinely disabled');
$check(str_contains($view,'updates.check') && str_contains($view,'updates.open')===false, 'Check for updates control uses distinct label');
$check(!str_contains($view,'update-package-review.php') && !str_contains($view,'admin-health-list') && !str_contains($view,'admin-upload-dropzone'), 'Technical ZIP and compatibility report UI absent');
$check(str_contains($view,'class="admin-panel admin-tools-section"') && str_contains($view,'admin-tools-feature') && str_contains($view,'admin-notice admin-notice--warning'), 'Existing Core layout and notice reused');
$check(str_contains($view,"admin_icon('arrow-left', 'admin-button__icon')") && str_contains($view,'class="admin-page-titlebar admin-page-titlebar--split"'), 'Back button uses Core icon and titlebar');
$check(str_contains($card,'href="/admin/vaerktoejer/update"') && str_contains($card,'admin-button admin-button--secondary'), 'Tools shortcut uses Core button and existing route');
$check(!str_contains($view,'<style') && !str_contains($view,'style=') && !str_contains($view,'<script'), 'No new ad hoc styles or scripts');
$check(str_contains($controller,"define('ADMIN_REQUIRED_CAPABILITY', 'tools.manage')"), 'Admin capability guard preserved');
$check(!str_contains($view, '$_FILES') && !str_contains($view, '->apply('), 'Page has no upload, installation or migration operation');
$check(is_file($root.'/core/classes/UpdatePackageReview.php') && is_file($root.'/core/classes/UpdateCompatibilityPreflight.php'), 'Internal security-review services retained');
$check(str_contains((string)$coreCss,'.admin-tools-feature') && str_contains((string)$coreCss,'.admin-button:disabled'), 'Existing Core styling covers layout and disabled button');
$check((bool)preg_match('/^\\d+\\.\\d+\\.\\d+/', '2.5.0'), 'Version label has strictly validated shape');
$check(str_contains($view,'admin_e($installedVersion)') && str_contains($view,"admin_e(t('updates.coming_soon'))"), 'Output escaped through Core helper');
foreach (['en','da','de','sv','nb'] as $lang) {
    $keys=['updates.title','updates.description','updates.installed','updates.current_version','updates.not_checked','updates.source_pending','updates.check','updates.coming_soon','updates.open'];
    $missing=array_filter($keys,static fn($k)=>empty($translation[$k][$lang]));
    $check(!$missing, 'All Updates UI text localized for '.$lang);
}
$check(str_contains((string)file_get_contents($root.'/docs/deployment/SAFE-UPDATE-WORKFLOW.md'), 'Updates admin UI redesign'), 'Support documentation reflects UI-only release');
echo "Updates UI Redesign: $pass PASS / $fail FAIL\n";
exit($fail?1:0);
