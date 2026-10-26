<?php
/** V2.4.4z4 — Pages list Core SVG actions and modal safety. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
$pass=0;$fail=0;
$check=static function(bool $ok,string $what)use(&$pass,&$fail):void { $ok?++$pass:++$fail;echo ($ok?'PASS':'FAIL').': '.$what."\n"; };
require_once $root.'/core/classes/AdminEscaper.php';
require_once $root.'/core/classes/AdminIcons.php';
// Pages has migrated to the Core list presentation component (z10).
require_once $root.'/core/classes/AdminListUI.php';
require_once $root . '/core/classes/AdminDeleteDialog.php';
function admin_e(mixed $v):string{return AdminEscaper::text($v);}
function t(string $k,array $params=[]):string{global $catalog;$s=(string)($catalog[$k]['da']??$k);foreach($params as $k=>$v)$s=str_replace('{'.$k.'}',(string)$v,$s);return $s;}
function admin_icon(string $name,string $class=''):string{return AdminIcons::render($name,$class);}
function admin_icon_action_button(string $name,string $label,array $attributes=[],string $tone='neutral'):string{return AdminIcons::actionButton($name,$label,$attributes,$tone);}
function admin_icon_action_link(string $name,string $label,string $url,array $attributes=[],string $tone='neutral'):string{return AdminIcons::actionLink($name,$label,$url,$attributes,$tone);}
function admin_pages_url(array $options=[]):string{return '/admin/sider';}
function admin_page_public_url(array $item):string{return '/'.trim((string)($item['page_slug']??''),'/');}
function admin_page_status_class(string $s):string{return $s;}
function admin_page_status_label(string $s):string{return $s;}
function admin_render_pagination(int $page,int $pages,callable $render,string $name):void{}
function admin_csrf_token():string{return 'token<>"';}
class Pages{public static function isSystemSlug(string $s):bool{return $s==='forside';}}
class PageScheduler{public static function localLabel(?string $v):string{return $v??'';}}
$catalog=json_decode(file_get_contents($root.'/core/lang/translations.json'),true,512,JSON_THROW_ON_ERROR);
$source=file_get_contents($root.'/public_html/admin/views/pages/index.php');
$end="require __DIR__ . '/../partials/layout.php';";
$check(substr_count($source,$end)===1,'Core layout remains shared');
$temp=tempnam(sys_get_temp_dir(),'pages-list-z4-');
file_put_contents($temp,str_replace($end,'echo $bodyHtml;',$source));
$normal=['id'=>17,'page_title'=>'Om os','page_slug'=>'om-os','page_status'=>'draft','content_mode'=>'builder','has_children'=>0];
$system=['id'=>19,'page_title'=>'Forside','page_slug'=>'forside','page_status'=>'published','content_mode'=>'legacy','has_children'=>0];
$parent=['id'=>33,'page_title'=>'<img src=x onerror=alert(1)>','page_slug'=>'parent','page_status'=>'protected','content_mode'=>'builder','has_children'=>1];
$render=static function(array $items)use($temp):string{
 $pagesData=['items'=>$items,'counts'=>['all'=>count($items),'published'=>1,'draft'=>1,'protected'=>1],'total'=>count($items),'from'=>1,'to'=>count($items)];$notice=null;
 ob_start();include $temp;return (string)ob_get_clean();
};
$html=$render([$normal,$system,$parent]);
$check(substr_count($html,'class="admin-table-actions admin-table-actions--icons"')===3,'Each row has compact action group');
$check(preg_match_all('/<(?:a|button)\b[^>]*class="admin-icon-action(?:\s|")/', $html)===9,'Each row contains exactly three icon actions');
$check(str_contains($html,'href="/admin/sider?mode=view&amp;id=17"'),'Eye opens admin detail even for drafts');
$check(str_contains($html,'href="/admin/sider?mode=edit&amp;id=17"'),'Pencil opens editor');
$check(str_contains($html,'aria-label="Se side"') && str_contains($html,'aria-label="Rediger"'),'View and edit icon controls have accessible labels');
$check(str_contains($html,'title="Se side"') && str_contains($html,'title="Rediger"'),'Tooltips use localized labels');
$check(substr_count($html,'data-admin-open-delete="page-list-delete-dialog"')===3,'Delete triggers reference one shared confirmation modal');
$check(substr_count($html,'id="page-list-delete-dialog"')===1,'Only one modal per list, not one per row');
$check(substr_count($html,'data-page-list-delete-id')===1,'Shared modal has single target ID input');
$check(str_contains($html,'data-page-delete-id="17"') && str_contains($html,'data-page-delete-title="Om os"'),'Safe row delete target attached as inert data');
$check(str_contains($html,'data-page-delete-title="&lt;img src=x onerror=alert(1)&gt;"'),'Untrusted page titles escaped in HTML attributes');
$check(!str_contains($html,'<img src=x onerror=alert(1)>'),'No reflected HTML from page titles');
$check(substr_count($html,' disabled')===2,'System and parent delete controls are disabled');
$check(str_contains($html,'Systemside kan ikke slettes') && str_contains($html,'Flyt eller slet undersiderne først'),'Disabled control states explain the reason');
$check(str_contains($html,'name="csrf_token"') && str_contains($html,'name="confirm_delete" value="permanent"'),'Confirm form retains CSRF and explicit POST confirmation');
$check(str_contains($html,'method="post" action="/admin/sider?mode=delete"'),'Modal submits to server-protected delete controller');
$check(str_contains($html,'name="return_to" value="list"'),'Delete failures return to list');
$check(strpos($html,'</section>')<strpos($html,'id="page-list-delete-dialog"'),'Shared modal remains outside dynamic live list replacement');
$check(str_contains($html,'class="admin-modal__header"') && str_contains($html,'class="admin-modal__body"') && str_contains($html,'class="admin-modal__footer"'),'Core fixed modal header/body/footer used');
$check(str_contains($html,'type="button" data-modal-close'),'Confirmation can be cancelled safely');
$empty=$render([]);
$check(str_contains($empty,'id="page-list-delete-dialog"') && !str_contains($empty,'data-page-delete-id='),'Empty list still has safe shared modal without rows');
$eye=AdminIcons::render('eye');
$check(str_contains($eye,'<circle cx="12"') && str_contains($eye,'<svg') && !str_contains($eye,'<script'),'Core eye icon has hand-authored SVG shape');
$js=file_get_contents($root.'/public_html/admin/assets/js/admin.js');
$check(str_contains($js,"document.addEventListener('click', (event) => {") && str_contains($js,"event.target.closest('[data-admin-open-delete]')"),'Delegated events survive live-search rows');
$check(str_contains($js,"name.textContent = button.dataset.pageDeleteTitle || ''"),'Modal label uses textContent, not unsafe HTML');
$check(str_contains($js,"if (!/^[1-9][0-9]*$/.test(id)) return;"),'Invalid or missing row IDs cannot open delete dialog');
$check(str_contains($js,'openDialog(dialog)'),'Core modal open functionality preserved');
$controller=file_get_contents($root.'/public_html/admin/pages.php');
$check(str_contains($controller,"'list' => '/admin/sider'") && str_contains($controller,"'edit' => '/admin/sider?mode=edit&id='"),'Error returns safely to list or editor');
$check(str_contains($controller,"admin_verify_csrf(") && str_contains($controller,"'confirm_delete'") && str_contains($controller,"\$pagesModel->deleteAdmin(\$id)"),'Server revalidates CSRF, consent, child/system deletion');
$model=file_get_contents($root.'/core/classes/Pages.php');
$check(str_contains($model,'EXISTS(SELECT 1 FROM pages child WHERE child.parent_id = p.id) AS has_children'),'List query detects child pages on server');
$check(str_contains($model,'LIMIT ? OFFSET ?'),'List pagination unchanged');
$css=file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$check($css===file_get_contents($root.'/public_html/admin/assets/scss/admin.scss'),'Core stylesheet compiled and source synchronized');
$check(str_contains($css,'body.nf-admin .admin-table-actions--icons { flex-direction: row;'),'Action icons stay inline on mobile');
foreach(['pages.actions_for','pages.cannot_delete_system','pages.cannot_delete_children']as$k)$check(count(array_filter(['da','en','de','sv','nb'],static fn($locale)=>isset($GLOBALS['catalog'][$k][$locale]) && trim($GLOBALS['catalog'][$k][$locale])!==''))===5,$k.' translated in five languages');
$versionSource=(string)file_get_contents($root.'/public_html/incl/init.php');
$check((bool)preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'/",$versionSource,$match) && version_compare($match[1],'2.4.4.51','>='),'Release version 2.4.4.51 or later');
if(in_array('--fixture',$argv,true)){
 $output='<!doctype html><html lang="da"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.$css.'</style></head><body class="nf-admin"><main style="max-width:1350px;margin:12px auto;padding:12px">'.$html.'</main><script>'.file_get_contents($root.'/public_html/admin/assets/js/admin.js').'</script></body></html>';
 file_put_contents(dirname($root).'/pages-list-fixture.html',$output);
}
unlink($temp);
echo "Summary V2.4.4z4: $pass PASS / $fail FAIL\n";
exit($fail?1:0);
