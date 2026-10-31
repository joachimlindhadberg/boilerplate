<?php
/** Isolated newest-Stable-and-Beta choice tests (mock GitHub API). */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
require_once dirname(__DIR__) . '/core/classes/Autoload.php';
$pass=0; $fail=0;
$ok=static function(bool $yes,string $label)use(&$pass,&$fail):void{
    echo ($yes?'PASS: ':'FAIL: ').$label.PHP_EOL;
    $yes?$pass++:$fail++;
};
$rel=static fn(string $tag,bool $preview=false,bool $draft=false):array=>
    ['tag_name'=>$tag,'draft'=>$draft,'prerelease'=>$preview,'assets'=>[]];
$make=static function(?array $stable,array $catalog,int $stableStatus=200,int $betaStatus=200):UpdateReleaseChoices{
    $lookup=new UpdateReleaseLookup(null,static function(string $url)use($stable,$catalog,$stableStatus,$betaStatus):array{
        $preview=str_contains($url,'?per_page=');
        return ['status'=>$preview?$betaStatus:$stableStatus,'body'=>json_encode($preview?$catalog:$stable)];
    });
    return new UpdateReleaseChoices($lookup);
};
$r=$make($rel('v2.5.2'),[$rel('v2.5.3-beta.1',true),$rel('v2.5.2')])->find('2.5.1-beta.2');
$ok($r['status']==='available' && count($r['releases'])===2,'Stable and newer Beta shown together');
$ok($r['releases'][0]['kind']==='stable' && $r['releases'][1]['kind']==='beta','Stable is first/default');
$r=$make($rel('v2.5.2'),[$rel('v2.5.2-beta.4',true),$rel('v2.5.2')])->find('2.5.1');
$ok(count($r['releases'])===1 && $r['releases'][0]['version']==='2.5.2','Older Beta hidden behind Stable');
$r=$make($rel('v2.5.2'),[$rel('v2.5.1-beta.2',true)])->find('2.5.2');
$ok($r['status']==='current' && !$r['releases'],'No downgrades or equal releases');
$r=$make(null,[$rel('v2.5.3-beta.1',true)],404)->find('2.5.1');
$ok(count($r['releases'])===1 && $r['releases'][0]['kind']==='beta','Beta only when no Stable exists');
$r=$make($rel('v2.5.2'),[$rel('v2.5.2-beta.1',true),$rel('v2.5.3-rc.2',true),$rel('v2.5.3-beta.10',true)])->find('2.5.1');
$ok(count($r['releases'])===2 && $r['releases'][1]['version']==='2.5.3-rc.2','Latest RC wins over Beta');
$r=$make(null,[],404)->find('2.5.1');
$ok($r['status']==='no_release','Empty sources not mistaken for up-to-date');
$r=$make($rel('v2.5.2'),[$rel('v2.5.3-beta.1',true)],200,403)->find('2.5.1');
$ok($r['status']==='github_rejected' && $r['releases']===[],'Rate limit/permission on preview fails closed');
$r=$make(null,[$rel('v2.5.3-beta.1',true)],503)->find('2.5.1');
$ok($r['status']==='service_unavailable' && $r['releases']===[],'Stable outage prevents unsafe preview recommendation');
$r=$make($rel('v2.5.2'),[$rel('v2.5.9-beta.1',true,true),$rel('v2.5.3-beta.1',true)])->find('2.5.1');
$ok(count($r['releases'])===2 && $r['releases'][1]['version']==='2.5.3-beta.1','Draft preview never offered');
$r=$make($rel('v2.5.2'),[$rel('v2.5.4',true),$rel('v2.5.3-beta.1',true)])->find('2.5.1');
$ok($r['releases'][1]['version']==='2.5.3-beta.1','Invalid flag/tag mismatch ignored');
$ok(UpdateReleaseVersion::selectPreview([$rel('v2.5.3'),$rel('v2.5.2-beta.10',true)])['_bp_version']==='2.5.2-beta.10','Preview selector excludes stable');
// A posted version must be current and exactly match the official selected release.
$tmp = sys_get_temp_dir() . '/bp-picker-qa-' . bin2hex(random_bytes(5));
mkdir($tmp . '/public_html/incl', 0700, true);
file_put_contents($tmp . '/public_html/incl/init.php', "<?php define('BOILERPLATE_VERSION', '2.5.1-beta.2');");
try {
    $catalog = [$rel('v2.5.1-beta.3', true), $rel('v2.5.1-beta.2', true), $rel('v2.5.1', false)];
    $metadata=static function(string $url) use ($catalog):array {
        return ['status'=>200, 'body'=>json_encode(str_contains($url, '?per_page=') ? $catalog : $catalog[2])];
    };
    $pkg=new UpdateOfficialReleasePackage($tmp, $metadata, static fn():array => throw new RuntimeException('No binary download expected'));
    $ok($pkg->prepareSelected('2.5.1-beta.3', 'stable')['status']==='blocked', 'Beta cannot be submitted as Stable');
    $ok($pkg->prepareSelected('2.5.1', 'beta')['status']==='blocked', 'Stable cannot be submitted as Beta');
    $ok($pkg->prepareSelected('2.5.1-beta.2', 'beta')['status']==='selection_changed', 'Older nonselected preview refused');
    $ok($pkg->prepareSelected('2.5.1-beta.4', 'beta')['status']==='selection_changed', 'Invented preview refused');
    $ok($pkg->prepareSelected('2.5.1-beta.3', 'beta')['status']==='missing_verified_asset', 'Selected preview requires official ZIP asset');
    $ok($pkg->prepareSelected('2.5.2', 'stable')['status']==='selection_changed', 'Invented Stable version refused');
    $ok(!file_exists($tmp . '/storage'), 'Rejected selections never create private staging files');
} finally {
    unlink($tmp . '/public_html/incl/init.php');
    rmdir($tmp . '/public_html/incl');
    rmdir($tmp . '/public_html');
    rmdir($tmp);
}
$root=dirname(__DIR__);
$tools=file_get_contents($root.'/public_html/admin/tools.php');
$package=file_get_contents($root.'/core/classes/UpdateOfficialReleasePackage.php');
$ok(str_contains($tools,"'release_selection'") && str_contains($tools,'admin_verify_csrf') && str_contains($tools,"'tools.manage'"),'Protected selection route');
$ok(str_contains($tools,'$eligible = false;') && str_contains($tools,'prepareSelected($expected, $kind)'),'Chosen release rechecked before prepare');
$ok(str_contains($package,'hash_equals($expectedVersion, $version)') && str_contains($package,'selectPreview($release)'),'Exact selected asset bound to source');
$ok(!str_contains($tools, "'set_update_channel'") && !str_contains($tools, 'channelSettings'),'No persistent channel setting');
$translations=json_decode(file_get_contents($root.'/core/lang/translations.json'),true,512,JSON_THROW_ON_ERROR);
foreach (['updates.choices_title','updates.choices_description','updates.stable_label','updates.preview_label','updates.stable_help','updates.preview_help','updates.update_selected'] as $key){
    $ok(count($translations[$key]??[])===5,'Translations: '.$key);
}
echo "RESULT: $pass PASS / $fail FAIL (synthetic release metadata; no network or DB)
";
exit($fail?1:0);
