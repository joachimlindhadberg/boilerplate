<?php
/** F3d.4a: private official release staging with fully isolated fixtures. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);

// In minimal build containers without ext-zip, use a fixture-only adapter.
// macOS PHP with ext-zip always exercises native ZipArchive instead.
$syntheticZip = !class_exists('ZipArchive');
if ($syntheticZip) {
    class ZipArchive {
        public const RDONLY = 1, CREATE = 2, OVERWRITE = 4, OPSYS_UNIX = 3;
        public int $numFiles = 0;
        private array $members = [];
        private string $filename = '';
        private bool $writing = false;
        public function open(string $path, int $flags = 0): bool {
            $this->filename=$path;
            $this->writing=(bool)($flags & self::CREATE);
            if ($this->writing) { $this->members=[]; $this->numFiles=0; return true; }
            $raw=@file_get_contents($path);
            $decoded=is_string($raw)?json_decode($raw,true):null;
            if (!is_array($decoded) || ($decoded['fixture_type']??'')!=='test-only-zip'
                || !is_array($decoded['members']??null)) return false;
            $this->members=$decoded['members']; $this->numFiles=count($this->members);
            return true;
        }
        public function addFromString(string $name, string $value): bool {
            $this->members[$name]=['value'=>base64_encode($value),'attrs'=>(0100644 << 16)];
            return true;
        }
        public function setExternalAttributesName(string $name, int $opsys, int $attrs): bool {
            if (!isset($this->members[$name])) return false;
            $this->members[$name]['attrs']=$attrs; return true;
        }
        public function statIndex(int $i): array|false {
            $key=array_keys($this->members)[$i]??null;
            if (!is_string($key)) return false;
            $value=base64_decode($this->members[$key]['value']??'',true);
            if (!is_string($value)) return false;
            return ['name'=>$key,'size'=>strlen($value),'comp_size'=>strlen($value),'encryption_method'=>0];
        }
        public function getExternalAttributesIndex(int $i, int &$opsys, int &$attrs): bool {
            $key=array_keys($this->members)[$i]??null;
            if (!is_string($key)) return false;
            $opsys=self::OPSYS_UNIX; $attrs=(int)$this->members[$key]['attrs'];return true;
        }
        public function getFromIndex(int $i, int $len=0): string|false {
            $key=array_keys($this->members)[$i]??null;
            if (!is_string($key)) return false;
            $raw=base64_decode($this->members[$key]['value']??'',true);
            return $len>0?substr($raw,0,$len):$raw;
        }
        public function close(): bool {
            if ($this->writing) {
                $json=json_encode(['fixture_type'=>'test-only-zip','members'=>$this->members],JSON_THROW_ON_ERROR);
                if (file_put_contents($this->filename,$json)===false) return false;
            }
            return true;
        }
    }
}
require_once dirname(__DIR__) . '/core/classes/Autoload.php';
$pass=0; $fail=0;
$test=static function(bool $ok,string $label) use (&$pass,&$fail):void {
    echo ($ok?'PASS: ':'FAIL: ').$label."\n";
    $ok?$pass++:$fail++;
};
$root=sys_get_temp_dir().'/boilerplate-official-staging-'.bin2hex(random_bytes(9));
if (!mkdir($root.'/public_html/incl',0700,true)) throw new RuntimeException('Fixture unavailable');
file_put_contents($root.'/public_html/incl/init.php',"<?php define('BOILERPLATE_VERSION', '2.5.0');\n");
$tmp=$root.'/test-release.zip';
$zip=new ZipArchive();
if ($zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Test archive unavailable');
$members=[
    'public_html/incl/init.php'=>"<?php define('BOILERPLATE_VERSION', '2.5.1');\n",
    'public_html/index.php'=>'<?php', 'public_html/admin/index.php'=>'<?php',
    'public_html/.htaccess'=>'', 'public_html/admin/.htaccess'=>'',
    'public_html/uploads/.htaccess'=>'',
    'core/classes/MigrationRunner.php'=>'<?php',
    'docs/deployment/UPDATING.md'=>'Fixture',
    'database/updates/20261013_000_core_schema.php'=>'<?php',
];
foreach ($members as $name=>$body) {
    $name='boilerplate/'.$name;
    $zip->addFromString($name,$body);
    $zip->setExternalAttributesName($name,ZipArchive::OPSYS_UNIX,(0100644<<16));
}
if (!$zip->close()) throw new RuntimeException('Cannot finalize test archive');
$blob=file_get_contents($tmp);
if (!is_string($blob)) throw new RuntimeException('Bad test archive');
$digest=hash('sha256',$blob);
$asset=['id'=>98765,'name'=>'Boilerplate-CMS-v2.5.1.zip','state'=>'uploaded',
    'size'=>strlen($blob),'digest'=>'sha256:'.$digest];
$release=static fn(array $assets):string=>json_encode([
    'draft'=>false,'prerelease'=>false,'tag_name'=>'v2.5.1','assets'=>$assets,
],JSON_THROW_ON_ERROR);
$meta=static fn(string $url):array=>['status'=>200,'body'=>$release([$asset])];
$binary=static function(string $url,string $dest,int $max) use ($blob):array {
    if ($url!=='https://api.github.com/repos/joachimlindhadberg/boilerplate/releases/assets/98765')
        throw new RuntimeException('Bad destination');
    file_put_contents($dest,$blob);
    return ['status'=>200];
};
$make=static fn(?callable $m=null,?callable $b=null):UpdateOfficialReleasePackage=>new UpdateOfficialReleasePackage($root,$m??$meta,$b??$binary);
$vault=$root.'/storage/update-recovery';
$stage=$vault.'/.official-release-'.$digest.'.zip';
$manifest=$vault.'/.official-stage.json';
try {
    $test($make()->preparedStatus()['status']==='not_prepared','No prepared release before authenticated action');
    $inspected=$make()->reviewLatest();
    $test($inspected['status']==='verified_for_review_only','Original review still works without staging');
    $test(!file_exists($stage) && !file_exists($manifest),'Original review retains no release ZIP or manifest');
    $test($inspected['install_ready']===false,'Original review cannot authorize installation');
    $result=$make()->prepareLatest();
    $test($result['status']==='prepared_for_review_only','Verified official release prepared');
    $test($result['version']==='2.5.1' && $result['sha256']===$digest,'Version and digest bound to prepared result');
    $test($result['files']===count($members),'ZIP review entry count preserved');
    $test($result['install_ready']===false,'Prepared release explicitly not install-ready');
    $test(is_file($stage) && !is_link($stage),'Verified artifact retained outside webroot');
    $test(file_get_contents($stage)===$blob,'Staged bytes exactly match verified asset');
    $test(is_file($manifest) && !is_link($manifest),'Atomic private identity manifest exists');
    $state=json_decode((string)file_get_contents($manifest),true,8,JSON_THROW_ON_ERROR);
    $test($state['schema']===1 && $state['repository']==='joachimlindhadberg/boilerplate','Manifest pins official publisher/repo');
    $test($state['size']===strlen($blob) && $state['sha256']===$digest,'Manifest binds archive length and digest');
    $test($state['install_ready']===false,'Manifest never authorizes installer');
    $test(($state['version']??null)==='2.5.1' && $state['files']===count($members),'Manifest versions and inventory accurate');
    $test((fileperms($vault)&0077)===0,'Staging directory is owner-only');
    $test((fileperms($stage)&0077)===0 && (fileperms($manifest)&0077)===0,'Staged release and manifest are owner-only');
    $test((fileperms($vault.'/.official-stage.lock')&0077)===0,'Staging lock is private');
    $test(!glob($vault.'/.github-release-*'),'No temporary GitHub downloads remain');
    $status=$make()->preparedStatus();
    $test($status['status']==='prepared_for_review_only','Prepared status can be read without another GitHub request');
    $test($status['version']==='2.5.1' && $status['sha256']===$digest,'Prepared status references actual disk bytes');
    $test($status['install_ready']===false,'Read-only prepared status cannot authorize installation');
    $test($make()->prepareLatest()['status']==='prepared_for_review_only','Re-preparing same release is idempotent');
    $test(file_get_contents($stage)===$blob,'Repeated preparation does not change artifact');
    $test(!glob($vault.'/.github-release-*'),'Repeated staging cleans temporary downloads');
    file_put_contents($stage,substr($blob,0,-1).'!');
    $test($make()->preparedStatus()['status']==='not_prepared','Same-size altered archive rejected');
    $bad=$make()->prepareLatest();
    $test($bad['status']==='download_or_review_failed','Corrupt pre-existing staged archive blocks replacement');
    $test(!glob($vault.'/.github-release-*'),'Failed prepare leaves no private temporary downloads');
    file_put_contents($stage,$blob);
    $test($make()->preparedStatus()['status']==='prepared_for_review_only','Restored fixture validates again');
    $good=file_get_contents($manifest);
    file_put_contents($manifest,'not-json');
    $test($make()->preparedStatus()['status']==='not_prepared','Unparseable stage identity refused');
    file_put_contents($manifest,$good);
    $tampered=json_decode($good,true);
    $tampered['install_ready']=true;
    file_put_contents($manifest,json_encode($tampered));
    $test($make()->preparedStatus()['status']==='not_prepared','Forged install authorization rejected');
    $tampered['install_ready']=false; $tampered['repository']='attacker/other';
    file_put_contents($manifest,json_encode($tampered));
    $test($make()->preparedStatus()['status']==='not_prepared','Incorrect publisher/repository rejected');
    file_put_contents($manifest,$good);
    unlink($stage); symlink($tmp,$stage);
    $test($make()->preparedStatus()['status']==='not_prepared','Linked staged archive rejected');
    $test($make()->prepareLatest()['status']==='download_or_review_failed','Linked target cannot be overwritten');
    unlink($stage); file_put_contents($stage,$blob); chmod($stage,0600);
    $metaSame=static fn(string $url):array=>['status'=>200,'body'=>json_encode([
       'draft'=>false,'prerelease'=>false,'tag_name'=>'v2.5.0','assets'=>[$asset],
    ],JSON_THROW_ON_ERROR)];
    $test($make($metaSame)->prepareLatest()['status']==='no_new_version','Same version cannot be prepared');
    $badBytes=static function(string $url,string $dest,int $limit):array {file_put_contents($dest,'bad bytes');return ['status'=>200];};
    $test($make(null,$badBytes)->prepareLatest()['status']==='download_or_review_failed','Wrong download length blocked');
    $test($make()->preparedStatus()['status']==='prepared_for_review_only','Failed new prepare retains prior valid staged release');
    $test(!glob($vault.'/.github-release-*'),'Bad downloads always removed');
    file_put_contents($root.'/public_html/incl/init.php',"<?php define('BOILERPLATE_VERSION', '2.5.1');\n");
    $test($make()->preparedStatus()['status']==='not_prepared','Already installed version invalidates stale prepared release');
    $test($make()->prepareLatest()['status']==='no_new_version','Installed latest cannot be staged again');
    $controller=file_get_contents(dirname(__DIR__).'/public_html/admin/tools.php');
    $view=file_get_contents(dirname(__DIR__).'/public_html/admin/views/tools/update.php');
    $translations=json_decode((string)file_get_contents(dirname(__DIR__).'/core/lang/translations.json'),true,512,JSON_THROW_ON_ERROR);
    $test(str_contains($controller,"define('ADMIN_REQUIRED_CAPABILITY', 'tools.manage')"),'Preparation route retains Core admin capability');
    $test(str_contains($controller,"admin_verify_csrf(\$_POST['csrf_token'] ?? null)"),'Preparation route includes CSRF guard');
    $test(str_contains($controller, 'prepareSelected($expected, $kind)') && str_contains($controller, 'new UpdateReleaseChoices()'), 'Selected preparation uses pinned GitHub releases');
    $test(str_contains($view,'install_official_update') && str_contains($view,'admin-button admin-button--primary'), 'Updated admin uses the Core Update now action');
    $test(str_contains($view, 'name="release_selection"') && str_contains($view, 'updates.update_selected'), 'Explicit selected release action required');
    $test(!str_contains($view,'->apply(') && !str_contains($view,'type="file"'),'No ZIP upload or arbitrary migration action in UI');
    foreach (['en','da','de','sv','nb'] as $language) {
        $missing=array_filter(['updates.prepare_button','updates.prepared_title','updates.prepared_description','updates.prepare_failed'],
            static fn($key)=>empty($translations[$key][$language]));
        $test(!$missing,'Preparation messages localized: '.$language);
    }
} finally {
    $remove=static function(string $path) use (&$remove):void {
        if (!is_dir($path) || is_link($path)) {@unlink($path); return;}
        foreach(scandir($path)?:[] as $n) if($n!=='.'&&$n!=='..')$remove($path.'/'.$n);
        @rmdir($path);
    };
    $remove($root);
}
echo 'Official Release Staging QA1: '.$pass.' PASS / '.$fail.' FAIL'
   .($syntheticZip?' (synthetic ZIP adapter; rerun with native PHP zip)':' (native PHP ZipArchive)')."\n";
exit($fail ? 1 : 0);
