<?php
/** V2.4.4z3 — Page authors + created/updated audit, no DB connection needed. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
$pass=0; $fail=0;
$check=static function(bool $ok,string $message) use (&$pass,&$fail):void {
    $ok ? ++$pass : ++$fail;
    echo ($ok?'PASS':'FAIL').': '.$message."\n";
};

/** Fake DB provides a DDL simulation and verifies prepared CRUD parameters. */
class Db {
    public array $columns=[];
    public array $constraints=[];
    public array $statements=[];
    public array $params=[];
    public ?array $adminRow=null;
    public function fetchValue(string $sql,array $params=[]):int {
        if (str_contains($sql,'INFORMATION_SCHEMA.COLUMNS')) return isset($this->columns[$params[0]]) ? 1 : 0;
        if (str_contains($sql,'INFORMATION_SCHEMA.TABLE_CONSTRAINTS')) return isset($this->constraints[$params[1]]) ? 1 : 0;
        return 0;
    }
    public function query(string $sql,array $params=[]):void {
        $this->statements[]=$sql;
        $this->params[]=$params;
        if (preg_match('/ALTER TABLE pages ADD COLUMN `([a-z_]+)`/',$sql,$m)) $this->columns[$m[1]]=true;
        if (preg_match('/ALTER TABLE pages ADD CONSTRAINT `([a-z_]+)`/',$sql,$m)) $this->constraints[$m[1]]=true;
    }
    public function fetchArray(string $sql,array $params=[]):array {
        if (str_contains($sql,'FROM pages p')) return $this->adminRow ? [$this->adminRow] : [];
        if (str_contains($sql,'SELECT id, page_slug, parent_id, page_status FROM pages')) return [];
        return [];
    }
    public function getInsertId():int { return 123; }
    public function beginTransaction():void {}
    public function commit():void {}
    public function rollback():void {}
}
class AdminOrdering {
    public function __construct(Db $db) {}
    public function prepareInsert(string $table,mixed $order,?int $parent):int { return 0; }
    public function move(string $table,int $id,mixed $order,?int $parent):int { return 0; }
}
class Redirects {
    public function __construct(Db $db) {}
    public function preparePageDestination(string $url):void {}
}
require_once $root.'/core/classes/Pages.php';

$migration = require $root.'/database/updates/20261016_000_pages_author_audit.php';
$check(is_callable($migration),'CLI migration provides callable');
$db = new Db();
$migration($db);
$check(isset($db->columns['created_by'],$db->columns['updated_by']), 'Migration adds both author columns');
$check(isset($db->constraints['pages_creator_fk'],$db->constraints['pages_editor_fk']), 'Migration adds two distinct author foreign keys');
$check(count($db->statements) === 4, 'Migration performs only two column additions and two foreign keys');
$check(count(array_filter($db->statements,static fn($s)=>str_contains($s,'ON DELETE SET NULL'))) === 2,'Foreign keys preserve page when user is deleted');
$migration($db);
$check(count($db->statements) === 4,'Migration is repeat-safe and does not alter existing columns');
$partial = new Db(); $partial->columns['created_by']=true; $partial->constraints['pages_creator_fk']=true;
$migration($partial);
$check(count($partial->statements) === 2 && isset($partial->columns['updated_by'],$partial->constraints['pages_editor_fk']),'Interrupted migration can safely resume');

$model = new Pages($db);
$data = ['page_title'=>'A','page_slug'=>'a','parent_id'=>null,'page_description'=>'','page_content'=>'', 'content_mode'=>'builder', 'page_status'=>'draft','menu_order'=>0,'show_in_menu'=>false,'meta_title'=>'','meta_description'=>''];
$created=$model->createAdmin($data,false,7);
$check($created===123,'Creation returns new page ID');
$insertIndex = array_key_last($db->statements);
$insertSql=$db->statements[$insertIndex]; $insertParams=$db->params[$insertIndex];
$check(str_contains($insertSql,'created_by, updated_by') && substr_count($insertSql,'?')===count($insertParams),'INSERT explicitly binds author columns');
$check(array_slice($insertParams,-2)===[7,7],'New page assigns creator and last editor to authenticated actor');
$db->statements=[];$db->params=[];
$model->createAdmin(array_merge($data,['page_slug'=>'b']),false,null);
$check(array_slice(end($db->params),-2)===[null,null],'No fictional creator for unattributed API writes');

$db->adminRow = ['id'=>123,'page_slug'=>'a','parent_id'=>null,'page_title'=>'A','page_status'=>'draft'];
$db->statements=[]; $db->params=[];
$updated=$model->updateAdmin(123, $data, false, 23);
$check($updated===true,'Existing page update succeeds through same model API');
$updateIndex = array_key_last($db->statements);
$updateSql=$db->statements[$updateIndex]; $updateParams=$db->params[$updateIndex];
$check(str_contains($updateSql,'updated_by = ?') && substr_count($updateSql,'?')===count($updateParams),'Update SQL binds editor ID');
$check(array_slice($updateParams,-2)===[23,123],'Only latest editor changes on UPDATE; target page ID remains final');
$check(!str_contains($updateSql,'created_by ='),'Creator ID is never overwritten on edit');

$pages = file_get_contents($root.'/core/classes/Pages.php');
$controller = file_get_contents($root.'/public_html/admin/pages.php');
$revisions = file_get_contents($root.'/core/classes/PageRevisions.php');
$scheduler = file_get_contents($root.'/core/classes/PageScheduler.php');
$check(str_contains($pages,'LEFT JOIN users creator ON creator.id = p.created_by') && str_contains($pages,'LEFT JOIN users editor ON editor.id = p.updated_by'),'Detail view resolves both names via LEFT JOINs');
$check(str_contains($pages,'CONCAT_WS') && str_contains($pages,'created_by_name') && str_contains($pages,'updated_by_name'),'Name lookup does not assume email or invent unknown names');
$check(str_contains($pages,'updated = NOW(), updated_by = ? WHERE id = ?'),'Page update explicitly tracks last author with parameter');
$check(str_contains($pages,'? $actorId : null, $id') && substr_count($pages,'$actorId !== null && $actorId > 0 ? $actorId : null')===3, 'Update and create SQL never interpolate actor IDs');
$check(str_contains($controller,"createAdmin(\$payload['data'], false, (int)(\$auth->user()['id'] ?? 0))"),'Create uses authenticated user');
$check(str_contains($controller,"updateAdmin(\$id, \$payload['data'], false, (int)(\$auth->user()['id'] ?? 0))"),'Save uses authenticated user');
$check(str_contains($revisions,"updateAdmin(\$pageId, \$desired['page'], false, \$userId)"),'Revision restore attributes restoring user');
$check(str_contains($scheduler,'updated = NOW(), updated_by = NULL'),'Automatic scheduler never falsely claims prior human author');

// Render actual PHP view with an isolated minimal renderer, including XSS coverage.
$catalog = json_decode((string)file_get_contents($root.'/core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
function t(string $key,array $vars=[]):string { global $catalog;$s=(string)($catalog[$key]['da']??$key);foreach($vars as $k=>$v)$s=str_replace('{'.$k.'}',(string)$v,$s);return $s; }
function admin_e(mixed $v):string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function admin_icon(string $name,string $class=''):string { return ''; }
function admin_page_public_url(array $p):string { return '/om-os'; }
function admin_page_status_class(string $s):string { return $s; }
function admin_page_status_label(string $s):string { return $s; }
function admin_page_builder_summary(string $type,array $data):string { return ''; }
class PageScheduler { public static function localLabel(?string $d):string { return (string)$d; } }
class PageBlocks { public static function definitions():array { return []; } }
$src = file_get_contents($root.'/public_html/admin/views/pages/view.php');
$end="require __DIR__ . '/../partials/layout.php';";
$check(substr_count($src,$end)===1,'Existing Pages view and layout contract retained');
$tmp=tempnam(sys_get_temp_dir(),'pages-audit-');
file_put_contents($tmp,str_replace($end,'echo $bodyHtml;',$src));
$render=static function(array $page) use ($tmp):string {
    $pageRecord=$page;$blocks=[];$childCount=0;$revisionCount=0;$schedule=[];$notice=null;
    ob_start(); include $tmp; return (string)ob_get_clean();
};
$base = ['id'=>17,'page_slug'=>'om-os','page_title'=>'Om os','page_status'=>'draft','content_mode'=>'builder','page_content'=>'','created'=>'2026-10-15 09:25:00','updated'=>'2026-10-16 10:10:00','created_by_name'=>'Anna Jensen','updated_by_name'=>'Peter Nielsen'];
$html = $render($base);
$check(str_contains($html,'15.10.2026 – 09:25') && str_contains($html,'16.10.2026 – 10:10'),'All date fields include date AND time');
$check(str_contains($html,'Anna Jensen') && str_contains($html,'Peter Nielsen'),'Creator and editor names rendered independently');
$check(str_contains($html,'Oprettet af') && str_contains($html,'Opdateret af'),'Both user labels present');
$check(str_contains($html,'admin-page-overview-info__audit'),'Four fields grouped in sidebar audit grid');
$legacy=$render(array_merge($base,['created_by_name'=>null,'updated_by_name'=>null]));
$check(substr_count($legacy,'Ukendt')===2,'Preexisting unattributed pages show Unknown without guessing');
$malicious=$render(array_merge($base,['created_by_name'=>'<script>alert(1)</script>','updated_by_name'=>'<img src=x onerror=alert(1)>']));
$check(!str_contains($malicious,'<script>alert(1)</script>') && !str_contains($malicious,'<img src=x onerror=alert(1)>') && str_contains($malicious,'&lt;script&gt;'),'Creator/editor names HTML escaped');
$check(!str_contains($html,'Slet side'),'View remains read-only, no delete button');
unlink($tmp);
foreach(['pages_detail.created_by','pages_detail.updated_by'] as $key) {
    $check(count(array_filter(['da','en','de','sv','nb'],static fn($locale)=>!empty($catalog[$key][$locale])))===5,"$key in 5 languages");
}
$css=file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$scss=file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
$check($css===$scss,'Core SCSS/CSS synchronized');
$check(str_contains($css,'.admin-page-overview-info__audit'),'Core-owned audit grid styling');
$check((bool)preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'/",file_get_contents($root.'/public_html/incl/init.php'),$version) && version_compare($version[1]??'0', '2.4.4.50', '>='),'V2.4.4z3 version baseline preserved');
if (in_array('--fixture', $argv, true)) {
    $html='<!doctype html><html lang="da"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.$css.'</style></head><body class="nf-admin"><main style="max-width:1230px;margin:24px auto;padding:16px">'.$html.'</main></body></html>';
    file_put_contents(dirname($root).'/page-author-fixture.html',$html);
}

echo "Summary V2.4.4z3: $pass PASS / $fail FAIL\n";
exit($fail?1:0);
