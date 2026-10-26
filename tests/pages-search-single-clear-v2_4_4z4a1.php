<?php
/** V2.4.4z4a1: prevent duplicate clear X in Core-enhanced live-search. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
$pass=0;$fail=0;
$check=static function(bool $ok,string $what)use(&$pass,&$fail):void{
  $ok?++$pass:++$fail;echo ($ok?'PASS':'FAIL').': '.$what."\n";
};
$js = file_get_contents($root.'/public_html/admin/assets/js/admin-live-search.js');
$css = file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$scss = file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
$pages = file_get_contents($root.'/public_html/admin/views/pages/index.php');
$init = file_get_contents($root.'/public_html/incl/init.php');
$check(str_contains($pages,'data-admin-live-search') && str_contains($pages,'type="search" name="q"'),'Pages retains semantic search input and Core enhancement');
$check(str_contains($js,"clear.className = 'admin-list-search__clear'"),'Existing Core clear is retained');
$check(str_contains($js,"clear.setAttribute('aria-label', translate('Nulstil'))"),'Core clear keeps localized accessible label');
$check(str_contains($js,"clear.title = translate('Nulstil')"),'Core clear keeps tooltip');
$check(str_contains($js,"clear.hidden = input.value.length === 0"),'Empty search starts with clear hidden');
$check(str_contains($js,"searchLabel.after(clear)"),'Core clear remains outside label');
$check(str_contains($js,"if (!searchLabel) return"),'Enhancement stops when no label exists');
$check(strpos($js,"searchLabel.after(clear)")<strpos($js,"form.classList.add('admin-list-search--enhanced')"),'Enhanced class set only after clear attached');
$check(str_contains($css,'.admin-list-search--enhanced input[type="search"]::-webkit-search-cancel-button'),'Hide native clear only in enhanced Core live-search forms');
$check(str_contains($css,"-webkit-appearance: none;") && str_contains($css,"display: none;"),'Suppress Chromium native search cancel affordance');
$check(!str_contains($css,'.admin-list-search input[type="search"]::-webkit-search-cancel-button'),'Non-enhanced searches retain native clear');
$check(str_contains($js,"clear.addEventListener('click'"),'Clear click event retained');
$check(str_contains($js,"input.value = '';") && str_contains($js,'runSearch();') && str_contains($js,'input.focus();'),'Clearing still runs live search and restores focus');
$check(str_contains($js,"form.addEventListener('submit'") && str_contains($js,"input.addEventListener('input'"),'GET form and live query behavior retained');
$check(str_contains($js,"history.replaceState(") && str_contains($js,"window.addEventListener('popstate'"),'History and back navigation retained');
$check(str_contains($js,"replaceListContents(fresh)"),'Dynamic result updates retained');
$check($css === $scss, 'Admin SCSS and CSS synchronized');
$check(is_file($root.'/database/updates/20261016_000_pages_author_audit.php'),'Latest approved database migration retained');
$check(is_file($root.'/public_html/admin/views/pages/partials/delete-modal.php'),'Existing Page deletion flow retained');
$check(
    (str_contains($pages, "admin_icon_action_link('eye'") && str_contains($pages, "admin_icon_action_link('edit'") && str_contains($pages, "admin_icon_action_button('delete'"))
    || (str_contains($pages, 'AdminListUI::actionGroup(') && str_contains($pages, "'icon' => 'eye'") && str_contains($pages, "'icon' => 'edit'") && str_contains($pages, "'icon' => 'delete'")),
    'Approved Pages row icon actions preserved via existing or Core list helper'
);
$check(preg_match("/BOILERPLATE_VERSION', '([0-9.]+)'/",$init,$versionMatches)===1 && version_compare($versionMatches[1], '2.4.4.53', '>='),'Internal version remains at least 2.4.4.53');
printf('Summary V2.4.4z4a1: %d PASS / %d FAIL'."\n",$pass,$fail);
exit($fail ? 1 : 0);
