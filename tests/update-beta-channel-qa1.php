<?php
/** Stable/Beta metadata regression, no HTTP, DB writes, or installation. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
require_once dirname(__DIR__) . '/core/classes/Autoload.php';
$pass=0; $fail=0;
$assert = static function (bool $ok, string $name) use (&$pass,&$fail): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
    $ok ? $pass++ : $fail++;
};
$rel = static fn(string $tag, bool $pre = false, bool $draft = false, array $assets = []): array
    => ['tag_name' => $tag, 'draft' => $draft, 'prerelease' => $pre, 'assets' => $assets];
$releaseList = [
    $rel('v2.5.1-beta.2', true),
    $rel('v2.5.1-beta.10', true),
    $rel('v2.5.1-rc.1', true),
    $rel('v2.5.1', false, true), // draft must be ignored
    $rel('v2.5.0'),
];
$api='';
$lookup = new UpdateReleaseLookup(null, static function(string $url) use (&$api,$releaseList): array {
    $api=$url;
    return ['status'=>200,'body'=>json_encode($releaseList, JSON_THROW_ON_ERROR)];
});
$r = $lookup->check('2.5.0', 'beta');
$assert($api === 'https://api.github.com/repos/joachimlindhadberg/boilerplate/releases?per_page=10', 'Beta uses the publisher-pinned catalog API');
$assert($r['status']==='available' && $r['latest_version']==='2.5.1-rc.1', 'Beta selects greatest valid published semver');
$assert($r['release_url']==='https://github.com/joachimlindhadberg/boilerplate/releases/tag/v2.5.1-rc.1', 'Beta URL is constructed from trusted repository/tag');
$assert($lookup->check('2.5.1-rc.1','beta')['status']==='current', 'No reinstall of equal release');
$assert($lookup->check('2.5.1','beta')['status']==='current', 'Never downgrade installed stable to prerelease');
$assert($lookup->check('2.5.0','stable')['status']==='invalid_response', 'Stable API refuses a catalog-shaped response');
$assert(UpdateReleaseVersion::valid('2.5.1-beta.1') && UpdateReleaseVersion::valid('2.5.1-rc.3'), 'Strict Beta and RC versions allowed');
$assert(!UpdateReleaseVersion::valid('2.5.1-alpha.1') && !UpdateReleaseVersion::valid('2.5.1-beta.0') && !UpdateReleaseVersion::valid('2.5.1-beta.1/evil'), 'Unsupported/non-canonical version forms refused');
$assert(version_compare('2.5.1-beta.10','2.5.1-beta.2','>') && version_compare('2.5.1','2.5.1-rc.9','>'), 'Prerelease numeric ordering and stable precedence');
$source = "<?php define('BOILERPLATE_VERSION', '2.5.1-beta.1');";
$assert(preg_match(UpdateReleaseVersion::SOURCE_PATTERN, $source, $m)===1 && $m[1]==='2.5.1-beta.1', 'Same version contract reads release ZIP init file');
$onlyBeta=[$rel('v2.5.1-beta.1',true)];
$probe = new UpdateReleaseLookup(null, static fn(string $url): array=>['status'=>200,'body'=>json_encode($onlyBeta)]);
$assert($probe->check('2.5.0','stable')['status']==='invalid_response', 'Stable refuses preview metadata');
$assert($probe->check('2.5.0','beta')['status']==='available', 'Beta opts in to preview metadata');
$empty = new UpdateReleaseLookup(null,static fn(string $url): array=>['status'=>200,'body'=>'[]']);
$assert($empty->check('2.5.0','beta')['status']==='no_release','Empty beta catalog is not up to date');
$draft = new UpdateReleaseLookup(null,static fn(string $url):array=>['status'=>200,'body'=>json_encode([$rel('v2.5.2-beta.1',true,true)])]);
$assert($draft->check('2.5.0','beta')['status']==='no_release','Drafts never offered as beta');
$invalid = new UpdateReleaseLookup(null,static fn(string $url):array=>['status'=>200,'body'=>json_encode([$rel('v2.5.2',true)])]);
$assert($invalid->check('2.5.0','beta')['status']==='no_release','Inconsistent tag/pre-release flags rejected');
$tmp = sys_get_temp_dir() . '/bp-beta-qa-' . bin2hex(random_bytes(6));
mkdir($tmp . '/public_html/incl', 0700, true);
file_put_contents($tmp . '/public_html/incl/init.php', "<?php define('BOILERPLATE_VERSION', '2.5.0');");
try {
    $metaApi='';
    $meta=static function(string $url) use (&$metaApi,$onlyBeta):array {
        $metaApi=$url;
        return ['status'=>200,'body'=>json_encode($onlyBeta,JSON_THROW_ON_ERROR)];
    };
    $package=new UpdateOfficialReleasePackage($tmp,$meta,static fn():array=>throw new RuntimeException('Should never download'));
    $assert($package->reviewLatest('beta')['status']==='missing_verified_asset', 'Beta verifies required GitHub asset identity before downloading');
    $assert($metaApi==='https://api.github.com/repos/joachimlindhadberg/boilerplate/releases?per_page=10', 'Asset verifier uses same Beta catalog');
    $assert($package->reviewLatest('stable')['status']==='blocked', 'Stable release verifier rejects catalog response');
    $assert(!file_exists($tmp . '/storage'), 'Blocked metadata check never creates recovery storage');
    $vault=$tmp.'/storage/update-recovery';
    mkdir($vault,0700,true);chmod($vault,0700);
    $bytes='isolated-stage-sentinel';$hash=hash('sha256',$bytes);
    $archive=$vault.'/.official-release-'.$hash.'.zip';
    file_put_contents($archive,$bytes);chmod($archive,0600);
    $manifest=[ 'schema'=>1, 'repository'=>'joachimlindhadberg/boilerplate',
        'version'=>'2.5.1-beta.1','channel'=>'beta','sha256'=>$hash,'size'=>strlen($bytes),
        'files'=>1,'prepared_at'=>time(),'install_ready'=>false ];
    file_put_contents($vault.'/.official-stage.json',json_encode($manifest));
    chmod($vault.'/.official-stage.json',0600);
    $assert($package->preparedStatus('beta')['status']==='prepared_for_review_only', 'Beta staged asset recognized only in beta channel');
    $assert($package->preparedStatus('stable')['status']==='not_prepared', 'Changing channel cannot install an old beta stage');
    unlink($vault.'/.official-stage.json');unlink($archive);rmdir($vault);rmdir($tmp.'/storage');
} finally {
    unlink($tmp.'/public_html/incl/init.php');rmdir($tmp.'/public_html/incl');rmdir($tmp.'/public_html');rmdir($tmp);
}
$tools=file_get_contents(dirname(__DIR__).'/public_html/admin/tools.php');
$view=file_get_contents(dirname(__DIR__).'/public_html/admin/views/tools/update.php');
$assert(!str_contains($tools, "'set_update_channel'") && str_contains($tools, "'release_selection'")
    && str_contains($tools, 'admin_verify_csrf'), 'Explicit choice replaces persistent channel, retains CSRF');
$assert(str_contains($tools, 'prepareSelected($expected, $kind)')
    && str_contains($tools, '$eligible = false;'), 'Selected release is revalidated against GitHub choices');
$assert(str_contains($view, 'name="release_selection"')
    && str_contains($view, 'updates.choices_title'), 'Core update picker replaces channel selector');
$assert(!str_contains($view, 'updates.save_channel') && !str_contains($view, '<style'), 'No channel settings or inline styles');
$translations=json_decode(file_get_contents(dirname(__DIR__).'/core/lang/translations.json'),true,512,JSON_THROW_ON_ERROR);
foreach (['updates.channel','updates.channel_beta','updates.beta_caution','updates.save_channel'] as $key) {
    $assert(count($translations[$key] ?? [])===5, 'Five languages for '.$key);
}
echo "RESULT: $pass PASS / $fail FAIL (metadata, source and UI only; no network, MySQL or update)\n";
exit($fail ? 1:0);
