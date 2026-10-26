<?php
/** Read-only fake database tests. Never accesses the installed CMS database. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';

final class UpdatePreflightFakeDb extends Db
{
    public array $writes = [];
    public bool $registry = true;
    public bool $badBase = false;
    public bool $dbError = false;
    public array $applied = [];

    public function __construct() { }
    public function query(string $sql, array $params = []): void { $this->writes[] = $sql; throw new RuntimeException('WRITE DISALLOWED'); }
    public function fetchValue(string $sql, array $params = [])
    {
        if ($this->dbError) throw new RuntimeException('SECRET test-db-password');
        if (str_contains($sql, "TABLE_NAME = 'schema_migrations'")) return $this->registry ? 1 : 0;
        throw new RuntimeException('Unexpected SELECT: ' . $sql);
    }
    public function fetchArray(string $sql, array $params = []): array
    {
        if ($this->dbError) throw new RuntimeException('SECRET test-db-password');
        if (str_contains($sql, 'INFORMATION_SCHEMA.TABLES')) {
            $names = ['users', 'pages', 'page_blocks', 'settings', 'security_rate_limits'];
            if ($this->badBase) array_pop($names);
            return array_map(static fn(string $name):array=>['TABLE_NAME'=>$name], $names);
        }
        if (str_contains($sql, 'FROM schema_migrations')) {
            return array_map(static fn(string $id,string $hash):array=>['migration_id'=>$id,'checksum_sha256'=>$hash],
                array_keys($this->applied), array_values($this->applied));
        }
        throw new RuntimeException('Unexpected SELECT: ' . $sql);
    }
}
$pass=0;$fail=0;
$check=static function(bool $condition,string $label) use (&$pass,&$fail):void {
    echo ($condition?'PASS: ':'FAIL: ').$label."\n"; $condition?$pass++:$fail++;
};
$db=new UpdatePreflightFakeDb();
$files=(new MigrationRunner($db,$root))->discover();
foreach($files as $id=>$file){$db->applied[$id]=$file['sha256'];}
$run=static fn():array=>(new UpdateCompatibilityPreflight($db,$root))->inspect();
$get=static function(array $result,string $key):array {
    foreach($result['checks'] as $entry) if($entry['key']===$key)return $entry;
    return [];
};
$good=$run();
$check(in_array($good['overall'],['review','blocked'],true) && $get($good,'target_package')['status']==='review','Even an up-to-date installed CMS is not automatically approved for an unknown update');
$check($get($good,'migrations')['status']==='good','Matching checksums recognized');
$check($get($good,'target_package')['status']==='review' && $get($good,'backup_verified')['status']==='review','Target version and backup always require separate verification');
$check(count($db->writes)===0,'Readiness inspect makes no DB writes');
$check(count($files)>0 && $good['migration_counts']['applied']===count($files),'Actual packaged migration IDs inspected');
$first=array_key_first($files);
$db->applied[$first]=str_repeat('0',64);
$mismatch=$run();
$check($mismatch['overall']==='blocked' && $get($mismatch,'migrations')['status']==='blocked','Checksum mismatch blocks');
$db->applied[$first]=$files[$first]['sha256'];
unset($db->applied[$first]);
$pending=$run();
$check($get($pending,'migrations')['status']==='review' && $pending['migration_counts']['pending']>=1,'Pending migrations require explicit review, not automatic execution');
$db->applied[$first]=$files[$first]['sha256'];
$db->applied['core:20000101_missing']=str_repeat('f',64);
$missing=$run();
$check($missing['overall']==='blocked' && $get($missing,'migrations')['status']==='blocked','Missing migration file blocks');
unset($db->applied['core:20000101_missing']);
$db->registry=false;
$noRegistry=$run();
$check($get($noRegistry,'registry')['status']==='blocked','Missing registry blocks');
$db->registry=true;
$db->badBase=true;
$legacy=$run();
$check($get($legacy,'base_schema')['status']==='blocked','Unsupported old schema blocks');
$db->badBase=false;
$db->dbError=true;
$error=$run();
$check($get($error,'database_check')['status']==='blocked' && !str_contains(json_encode($error), 'SECRET'),'Database exception safely redacted');
$check(count($db->writes)===0,'No write queries across all failure scenarios');
$controller=(string)file_get_contents($root.'/public_html/admin/tools.php');
$view=(string)file_get_contents($root.'/public_html/admin/views/tools/update.php');
$rewrite=(string)file_get_contents($root.'/public_html/.htaccess');
$check(str_contains($controller, "if (\$mode === 'update')") && str_contains($controller, 'REQUEST_METHOD') && str_contains($controller, "admin_render('tools/update'") && str_contains($rewrite, '^admin/vaerktoejer/update/'), 'Authorized Tools GET route with no write action');
// The public webroot AND admin directory both have .htaccess rules. A route
// present in only one is insufficient: the admin fallback can turn it into 404.
$adminRewrite = (string)file_get_contents($root.'/public_html/admin/.htaccess');
$expectedAdminRule = 'RewriteRule ^vaerktoejer/update/?$ tools.php?mode=update [L]';
$expectedRootRule = 'RewriteRule ^admin/vaerktoejer/update/?$ admin/tools.php?mode=update [L]';
$check(str_contains($rewrite, $expectedRootRule), 'Update URL registered in public webroot .htaccess');
$check(str_contains($adminRewrite, $expectedAdminRule), 'Update URL registered in admin/.htaccess');
$adminUpdatePos = strpos($adminRewrite, $expectedAdminRule);
$adminFallbackPos = strpos($adminRewrite, 'RewriteRule ^(.+?)/?$ index.php?legacy_path=$1');
$check($adminUpdatePos !== false && $adminFallbackPos !== false && $adminUpdatePos < $adminFallbackPos,
    'Nested update rewrite must precede admin 404 fallback');
$rootUpdatePos = strpos($rewrite, $expectedRootRule);
$rootFallbackPos = strpos($rewrite, 'RewriteRule ^admin/(.+?)/?$ admin/index.php?legacy_path=$1');
$check($rootUpdatePos !== false && $rootFallbackPos !== false && $rootUpdatePos < $rootFallbackPos,
    'Root update rewrite must precede root admin 404 fallback');
$check(str_contains((string)file_get_contents($root.'/public_html/admin/views/tools/partials/update-card.php'), 'href="/admin/vaerktoejer/update"'),
    'Tools navigation points at the checked update route');
$check(str_contains($view, 'class="admin-page-titlebar admin-page-titlebar--split"')
    && str_contains($view, 'class="admin-button admin-button--secondary" href="/admin/vaerktoejer"')
    && str_contains($view, "admin_icon('arrow-left', 'admin-button__icon')")
    && !str_contains($view, "admin_icon('arrow-left');")
    && str_contains((string)file_get_contents($root.'/public_html/admin/assets/scss/admin.scss'), '.admin-button__icon {')
    && str_contains((string)file_get_contents($root.'/public_html/admin/assets/css/admin.css'), '.admin-button__icon {'),
    'Update back navigation reuses Core split titlebar, standard button and sized SVG class');
$check(str_contains($view, 'admin_e(') && str_contains($view, 'updates.title') && !str_contains($view, 'update-package-review.php') && !str_contains($view, 'name="update_package"') && !str_contains($view, 'admin_verify_csrf'), 'Updates UI shows a localized, simple page without technical package forms');
$check(str_contains((string)file_get_contents($root.'/public_html/admin/tools.php'), "define('ADMIN_REQUIRED_CAPABILITY', 'tools.manage')") && str_contains((string)file_get_contents($root.'/public_html/admin/incl/module-bootstrap.php'), 'can($requiredCapability)'), 'Tools controller retains centralized guard');
$translation=json_decode((string)file_get_contents($root.'/core/lang/translations.json'),true,512,JSON_THROW_ON_ERROR);
$check(isset($translation['update_preflight.title']['en'],$translation['update_preflight.title']['da']),'Admin translation keys present');

echo "Update Compatibility Preflight: {$pass} PASS / {$fail} FAIL\n";
exit($fail>0?1:0);
