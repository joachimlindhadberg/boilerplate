<?php
/**
 * ZIP metadata scenarios without installing the PHP zip extension. When the
 * extension exists, this file checks the real PHP zip API contract and skips
 * the mock scenarios; normal review still needs a browser smoke on staging.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
if (!class_exists('ZipArchive')) {
    final class ZipArchive
    {
        public const RDONLY = 16;
        public const OPSYS_UNIX = 3;
        public static array $mockMembers = [];
        public int $numFiles = 0;
        public function open(string $file, int $flags = 0): bool { $this->numFiles = count(self::$mockMembers); return is_file($file); }
        public function close(): bool { return true; }
        public function statIndex(int $index): array|false
        {
            $entry = self::$mockMembers[$index] ?? null;
            if ($entry === null) return false;
            return ['name'=>$entry[0], 'size'=>strlen($entry[1]), 'comp_size'=>strlen($entry[1])];
        }
        public function getFromIndex(int $index): string|false { return self::$mockMembers[$index][1] ?? false; }
        public function getExternalAttributesIndex(int $index, int &$opsys, int &$attributes): bool
        {
            if (!isset(self::$mockMembers[$index])) return false;
            $opsys = self::OPSYS_UNIX;
            $type = self::$mockMembers[$index][2] ?? (str_ends_with(self::$mockMembers[$index][0], '/') ? 0040755 : 0100644);
            $attributes = $type << 16;
            return true;
        }
    }
    $mock = true;
} else {
    $mock = false;
}
$root = dirname(__DIR__);
require_once $root.'/core/classes/Autoload.php';
$pass=0; $fail=0;
$assert=static function(bool $ok,string $name) use (&$pass,&$fail):void { echo ($ok?'PASS: ':'FAIL: ').$name."\n"; $ok?$pass++:$fail++;};
$assert($mock || class_exists('ZipArchive'), 'ZipArchive present or mock test available');
if (!$mock) {
    echo 'NOTE: PHP zip available; mock ZIP tests skipped. Test actual ZIP upload on staging.\n';
    exit(0);
}
$paths = [
    'public_html/incl/init.php', 'public_html/index.php', 'public_html/admin/index.php',
    'public_html/.htaccess', 'public_html/.user.ini', 'public_html/admin/.htaccess', 'public_html/uploads/.htaccess',
    'core/classes/MigrationRunner.php', 'docs/deployment/UPDATING.md',
    'database/updates/20261013_000_core_schema.php',
];
$base=[];
foreach ($paths as $path) {
    $content=(string)file_get_contents($root.'/'.$path);
    if ($path==='public_html/incl/init.php') $content=str_replace("define('BOILERPLATE_VERSION', '2.5.0')", "define('BOILERPLATE_VERSION', '2.5.1')", $content);
    $base[]=['boilerplate/'.$path,$content];
}
foreach (glob($root.'/database/updates/*.php') ?: [] as $path) {
    $name='boilerplate/'.substr($path,strlen($root)+1);
    if (!in_array($name,array_column($base,0),true)) $base[]=[$name,(string)file_get_contents($path)];
}
foreach (glob($root.'/modules/*/migrations/*.php') ?: [] as $path) {
    $name='boilerplate/'.substr($path,strlen($root)+1);
    $base[]=[$name,(string)file_get_contents($path)];
}
$tmp=tempnam(sys_get_temp_dir(),'zip-review-fixture');
if ($tmp===false) exit(2);
file_put_contents($tmp,'standalone test payload, never CMS data');
try {
    $run=static function(array $members) use ($root,$tmp):array {
        ZipArchive::$mockMembers=$members;
        return (new UpdatePackageReview($root))->inspect($tmp,filesize($tmp));
    };
    $good=$run($base);
    $assert($good['status']==='review' && $good['target_version']==='2.5.1','Valid package remains review-only, even after structure/migration scan');
    $assert($good['new_migrations']===0,'Unchanged historical migrations recognized');
    $assert((bool)array_filter($good['checks'], static fn($check) => $check['label']==='PHP hosting configuration' && $check['status']==='good'), 'Official public_html/.user.ini accepted when unchanged');
    $changedIni=$base;
    foreach ($changedIni as &$row) {
        if ($row[0]==='boilerplate/public_html/.user.ini') $row[1].="\nmax_execution_time = 500\n";
    }
    unset($row);
    $iniReport=$run($changedIni);
    $assert($iniReport['status']==='review' && (bool)array_filter($iniReport['checks'], static fn($check) => $check['label']==='PHP hosting configuration' && $check['status']==='review'), 'Changed PHP host settings require manual review');
    $assert(count(array_filter($good['checks'],static fn($x)=>$x['status']==='blocked'))===0,'No structural blockers for controlled fixture');
    $new=$base; $new[]=['boilerplate/database/updates/20261101_000_sample.php','<?php return static function () {};'];
    $newReport=$run($new);
    $assert($newReport['status']==='review' && $newReport['new_migrations']===1,'New migrations require separate staging review');
    $old=$base;
    foreach ($old as &$row) {
        if ($row[0]==='boilerplate/public_html/incl/init.php') $row[1]=str_replace("'2.5.1'","'2.5.0'",$row[1]);
    }
    unset($row);
    $assert($run($old)['status']==='blocked','Equal version blocked');
    $edited=$base;
    foreach ($edited as &$row) {
        if ($row[0]==='boilerplate/database/updates/20261013_000_core_schema.php') $row[1].='// changed historical migration';
    }
    unset($row);
    $assert($run($edited)['status']==='blocked','Changed historical migration blocked');
    $missing=$base;
    $missing=array_values(array_filter($missing,static fn($row)=>$row[0]!=='boilerplate/public_html/.htaccess'));
    $missingReport=$run($missing);
    $assert($missingReport['status']==='blocked','Missing mandatory runtime policy blocked');
    $assert((bool)array_filter($missingReport['checks'],static fn($check)=>$check['label']==='Production package' && str_contains($check['detail'],'boilerplate/public_html/.htaccess')),'Missing file diagnostics identify the exact required path');
    $duplicate=$base; $duplicate[]=$base[0];
    $duplicateReport=$run($duplicate);
    $assert($duplicateReport['status']==='blocked','Duplicate member blocked');
    $assert((bool)array_filter($duplicateReport['checks'],static fn($check)=>$check['label']==='ZIP paths' && str_contains($check['detail'],'boilerplate/public_html/incl/init.php')),'Duplicate path diagnostics identify offending entry');
    $traversal=$base; $traversal[]=['boilerplate/public_html/../secrets.php','evil'];
    $traversalReport=$run($traversal);
    $assert($traversalReport['status']==='blocked','Traversal blocked');
    $assert((bool)array_filter($traversalReport['checks'],static fn($check)=>$check['label']==='ZIP paths' && str_contains($check['detail'],'boilerplate/public_html/../secrets.php')),'Traversal diagnostics identify rejected archive member');
    $dev=$base; $dev[]=['boilerplate/tests/unsafe.php','<?php'];
    $devReport=$run($dev);
    $assert($devReport['status']==='blocked' && (bool)array_filter($devReport['checks'],static fn($check)=>str_contains($check['detail'],'boilerplate/tests/unsafe.php')),'Development-only ZIP file identified without allowing it');
    $hotfix=$base; $hotfix[]=['INSTALL-AND-TEST.txt','instructions'];
    $hotfixReport=$run($hotfix);
    $assert($hotfixReport['status']==='blocked' && (bool)array_filter($hotfixReport['checks'],static fn($check)=>str_contains($check['detail'],'INSTALL-AND-TEST.txt')),'Hotfix archive root member identified without allowing it');
    $malformed=$base; $malformed[]=["boilerplate/public_html/bad<file\n.txt",'unsafe'];
    $malformedReport=$run($malformed);
    $assert($malformedReport['status']==='blocked' && (bool)array_filter($malformedReport['checks'],static fn($check)=>str_contains($check['detail'],'bad<file?.txt') && !str_contains($check['detail'],"\n")),'Control bytes normalized in rejected-path diagnostic');
    $link=$base; $link[0][2]=0120777;
    $assert($run($link)['status']==='blocked','Symlink metadata blocked');
    $media=$base; $media[]=['boilerplate/public_html/uploads/photo.jpg','private'];
    $assert($run($media)['status']==='blocked','User-media member rejected');
    $assert($good['sha256']===hash_file('sha256',$tmp),'SHA-256 displayed for manual independent comparison');
} finally {
    @unlink($tmp);
}
echo "Safe Update ZIP scenarios: {$pass} PASS / {$fail} FAIL\n";
exit($fail?1:0);
