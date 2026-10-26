<?php
/** z20e: true, server-owned Forms/Indsendelser filter-tabs, Core header, and saved workflows. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root=dirname(__DIR__);
require_once $root.'/core/classes/Autoload.php';
require_once $root.'/modules/forms/admin/controllers/FormsAdminController.php';
function t(string $key, array $params=[]):string {return str_starts_with($key,'forms:')?substr($key,6):$key;}
function admin_e(mixed $v):string {return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function admin_icon(string $name,string $class=''):string {return AdminIcons::render($name,$class);}
function admin_csrf_token():string {return 'z20e-test-token';}
function admin_render_pagination(int $p,int $pages,callable $link,string $label):void {if($pages>1)echo '<a class="test-page" href="'.admin_e($link(2)).'">2</a>';}
function admin_crud_pagination(int $total):array {
    $perPage=10; $pages=max(1,(int)ceil($total/$perPage));$page=max(1,min((int)($_GET['page']??1),$pages));$offset=($page-1)*$perPage;
    return ['page'=>$page,'pages'=>$pages,'perPage'=>$perPage,'offset'=>$offset,'from'=>$total?$offset+1:0,'to'=>min($total,$offset+$perPage)];
}
function admin_pull_notice():?array{return null;}
class Z20eFakeRepository {
    public array $all=[];
    public array $received=[];
    public function allForms():array{return $this->all;}
    public function formDeleteDependencies(int $id):array{return ['submissions'=>0,'pages'=>0];}
    public function findForm(int $id):?array {foreach($this->all as $form){if($form['id']===$id)return $form;}return null;}
    private function filtered(int $formId,string $status,string $search):array {
        return array_values(array_filter($this->received, static function(array $s)use($formId,$status,$search):bool{
            if($formId>0 && $s['form_id']!==$formId)return false;
            if($status!=='' && $s['status']!==$status)return false;
            return $search===''||stripos($s['form_name'].' '.$s['source_url'], $search)!==false;
        }));
    }
    public function countSubmissions(int $formId=0,string $status='',string $search=''):int{return count($this->filtered($formId,$status,$search));}
    public function submissions(int $formId=0,string $status='',string $search='',int $limit=100,int $offset=0):array{return array_slice($this->filtered($formId,$status,$search),$offset,$limit);}
}
class Z20eFakeContext {
    public string $path='';public array $data=[];
    public function renderAdmin(string $path,array $data):void {$this->path=$path;$this->data=$data;}
}
class Z20eAdmin {
    use FormsAdminController;
    public Z20eFakeContext $context;
    public function __construct(public Z20eFakeRepository $repositoryObj) {$this->context=new Z20eFakeContext;}
    private function requireContext():void {}
    private function repository():Z20eFakeRepository{return $this->repositoryObj;}
    private function limitText(string $v,int $size):string{return substr($v,0,$size);}
}
$pass=$fail=0;
$check=static function(bool $ok,string $name)use(&$pass,&$fail):void{echo ($ok?'PASS: ':'FAIL: ').$name."\n";$ok?$pass++:$fail++;};
$repo=new Z20eFakeRepository;
$repo->all=[
 ['id'=>1,'name'=>'Alpha & Co <tag>','status'=>'active','field_count'=>2,'updated_at'=>'2026-10-17 12:00:00'],
 ['id'=>2,'name'=>'Alpha Beta','status'=>'inactive','field_count'=>0,'updated_at'=>'2026-10-17 12:00:00'],
 ['id'=>3,'name'=>'Gamma','status'=>'active','field_count'=>3,'updated_at'=>'2026-10-17 12:00:00'],
];
$repo->received=[
 ['id'=>11,'form_id'=>1,'form_name'=>'Alpha & Co <tag>','status'=>'new','source_url'=>'/site/contact','submitted_at'=>'2026-10-17 12:00:00'],
 ['id'=>12,'form_id'=>1,'form_name'=>'Alpha & Co <tag>','status'=>'read','source_url'=>'/site/contact','submitted_at'=>'2026-10-17 12:01:00'],
 ['id'=>13,'form_id'=>2,'form_name'=>'Alpha Beta','status'=>'new','source_url'=>'/site/news','submitted_at'=>'2026-10-17 12:02:00'],
];
$admin=new Z20eAdmin($repo);
$_SERVER['REQUEST_METHOD']='GET';
$_GET=['status'=>'active','q'=>'Alpha'];$admin->adminForms([]);$data=$admin->context->data;
$check($data['status']==='active' && $data['total']===1 && array_column($data['forms'],'id')===[1], 'Forms active tab filters server-side before pagination');
$check($data['filterCounts']===[''=>2,'active'=>1,'inactive'=>1], 'Forms tab counts are search-scoped and independent of current status/page');
$_GET=['status'=>'inactive','q'=>'Alpha'];$admin->adminForms([]);$check($admin->context->data['total']===1 && $admin->context->data['forms'][0]['id']===2, 'Forms inactive tab filters the right records');
$_GET=['status'=>'unexpected','q'=>'Alpha'];$admin->adminForms([]);$check($admin->context->data['status']==='' && $admin->context->data['total']===2, 'Invalid Forms status safely becomes All');
$_GET=['status'=>'active'];$admin->adminForms([]);$data=$admin->context->data;
$check($data['total']===2 && $data['filterCounts']===[''=>3,'active'=>2,'inactive'=>1], 'Forms tab totals never derive from page-size counts');
$_GET=['status'=>'new','form_id'=>'1','q'=>'site'];$admin->adminSubmissions([]);$subs=$admin->context->data;
$check($subs['selectedFormId']===1 && $subs['selectedStatus']==='new', 'Submission deep link keeps form_id + selected status');
$check($subs['filterCounts']===[''=>2,'new'=>1,'read'=>1] && $subs['total']===1,'Submission counts use repository filters and status correctly');
$check(array_column($subs['submissions'],'id')===[11] && $subs['activeNav']==='module:forms:submissions','Submission listing/active main menu unchanged');
$_GET=['status'=>'read','form_id'=>'1','q'=>'site'];$admin->adminSubmissions([]);$check(array_column($admin->context->data['submissions'],'id')===[12],'Read submission tab selects only read item');
$_GET=['status'=>'invalid','form_id'=>'999'];$admin->adminSubmissions([]);$check($admin->context->data['selectedStatus']==='' && $admin->context->data['selectedFormId']===0 && $admin->context->data['total']===3,'Invalid status/form id fail closed to valid All filter');
$renderForms=static function(array $data)use($root):string{extract($data);ob_start();require $root.'/modules/forms/admin/views/partials/forms-list.php';return (string)ob_get_clean();};
$_GET=['status'=>'active','q'=>'Alpha'];$admin->adminForms([]);$html=$renderForms($admin->context->data);
$check(substr_count($html,'<nav class="admin-filter-tabs"')===1 && str_contains($html,'aria-label="Filtrer formularstatus"'),'Forms header uses one Core-owned filterTabs component');
$check(strpos($html,'class="admin-filter-tabs"')<strpos($html,'class="admin-list-search"'),'Forms tabs left, search right in Sider toolbar markup');
$check(substr_count($html,'class="is-active" aria-current="page"')===1 && str_contains($html,'>Aktive</span>'),'Forms active tab uniquely exposes aria-current');
$check(str_contains($html,'href="/admin/forms?status=inactive&amp;q=Alpha"') && str_contains($html,'href="/admin/forms?q=Alpha"'),'Forms status filter URLs preserve query and allow clearing status');
$check(str_contains($html,'name="status" value="active"') && str_contains($html,'name="q" value="Alpha"'),'Forms search GET preserves selected tab');
$check(!str_contains($html,'href="/admin/forms/settings" class="admin-button"') && strpos($html,'href="/admin/forms/settings"')>strpos($html,'</section>'),'Global Forms settings link is outside table header, still accessible');
$check(str_contains($html,'data-admin-live-list') && str_contains($html,'data-admin-live-search'),'Forms retains delegated Core live search and list');
$check(str_contains($html,'forms-list-delete-dialog') && str_contains($html,'data-forms-list-delete-id'),'Forms original POST delete modal and row actions remain');
$renderSubs=static function(array $data)use($root):string{extract($data);ob_start();require $root.'/modules/forms/admin/views/partials/submissions-list.php';return (string)ob_get_clean();};
$_GET=['status'=>'new','form_id'=>'1','q'=>'site'];$admin->adminSubmissions([]);$subHtml=$renderSubs($admin->context->data);
$check(strpos($subHtml,'class="admin-filter-tabs"')<strpos($subHtml,'class="admin-list-search"'),'Submissions tabs left, search right, same Core header contract');
$check(substr_count($subHtml,'<nav class="admin-filter-tabs"')===1 && str_contains($subHtml,'aria-label="Filtrer indsendelsesstatus"'),'Submissions uses exactly one Core filterTabs');
$check(substr_count($subHtml,'class="is-active" aria-current="page"')===1 && str_contains($subHtml,'>Ulæste</span>'),'Unread tab exclusively active');
$check(!str_contains($subHtml,'<select') && !str_contains($subHtml,'Alle formularer') && !str_contains($subHtml,'Alle statusser'),'Both dropdowns removed from submissions toolbar');
$check(str_contains($subHtml,'name="form_id" value="1"') && str_contains($subHtml,'name="status" value="new"'),'Submissions search preserves form deep-link and selected status');
$check(str_contains($subHtml,'href="/admin/forms/submissions?form_id=1&amp;status=read&amp;q=site"'),'Status tab links retain chosen form and search');
$check(str_contains($subHtml,'href="/admin/forms/submissions?form_id=1&amp;q=site"'),'All tab removes status but retains form and search');
$check(str_contains($subHtml,'name="return_url"') && str_contains($subHtml,'form_id=1') && str_contains($subHtml,'status=new'),'POST actions return to same filtered page');
$check(str_contains($subHtml,'data-forms-mark-submission') && str_contains($subHtml,'data-forms-delete-submission') && str_contains($subHtml,'data-forms-delete-dialog'),'Submission actions/read status/Delete Core dialog preserved');
$check(!str_contains($subHtml,'<tag>') && str_contains($subHtml,'&lt;tag&gt;'),'User-provided form name safely escaped');
$check(str_contains($subHtml,'data-admin-live-search') && str_contains($subHtml,'data-admin-live-list'),'Submission table still supports Core live search');
$lang=json_decode((string)file_get_contents($root.'/modules/forms/lang/translations.json'),true);
foreach(['da','en','de','sv','nb'] as $locale){
    $good=true;foreach(['Aktive','Inaktive','Filtrer formularstatus','Filtrer indsendelsesstatus','Globale Forms-indstillinger'] as $key){$good=$good && trim((string)($lang[$key][$locale]??''))!=='';}
    $check($good,'All new Forms filter/help labels localized in '.$locale);
}
$scss=file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');$css=file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$check($scss===$css,'Core stylesheet and SCSS remain byte-identical');
$meta=file_get_contents($root.'/public_html/incl/init.php');preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'/",(string)$meta,$version);$check(!empty($version[1]) && version_compare($version[1],'2.4.4.82','>='),'z20e minimum version >= 2.4.4.82');
echo "Forms Table Header Alignment z20e: $pass PASS / $fail FAIL\n";
exit($fail?1:0);
