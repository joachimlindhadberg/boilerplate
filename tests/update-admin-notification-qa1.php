<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
require_once dirname(__DIR__) . '/core/classes/Autoload.php';
$pass = 0; $fail = 0;
$check = static function(bool $ok, string $label) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
    $ok ? $pass++ : $fail++;
};
$time = 1800000000;
$count = 0;
$available = static function(string $installed) use (&$count): array {
    $count++;
    return ['status'=>'available','latest_version'=>'2.5.1','release_url'=>'https://untrusted.example/'];
};
$session=[];
$check(UpdateAdminNotification::check('2.5.0',$session,$available,$time) === ['status'=>'available','latest_version'=>'2.5.1'], 'Official stable release triggers dashboard notice');
$check($count === 1, 'First dashboard visit performs one lookup');
$check(UpdateAdminNotification::check('2.5.0',$session,$available,$time+1)['status'] === 'available', 'Notification reuses session cache');
$check($count === 1, 'No repeated request during cache TTL');
$check(UpdateAdminNotification::check('2.5.0',$session,$available,$time+3599)['status'] === 'available', 'Cache remains valid until TTL');
$check($count === 1, 'Cache avoids GitHub request during final TTL second');
$check(UpdateAdminNotification::check('2.5.0',$session,$available,$time+3600)['status'] === 'available', 'Cache refreshes after one hour');
$check($count === 2, 'One hourly refresh');
$check(UpdateAdminNotification::check('2.5.1',$session,$available,$time+3601)['status'] === 'not_available', 'Installed version change invalidates stale prompt');
$check($count === 3, 'Installed version change triggers a fresh lookup');
foreach (['2.5.0-rc1','2.x.0','','2.5.0/evil'] as $bad) {
    $n=$count;
    $check(UpdateAdminNotification::check($bad,$session,$available,$time+4000)['status'] === 'not_available' && $count === $n,'Invalid installed version never performs a lookup');
}
foreach (['2.5.0','2.4.9','2.4.9.9','2.5.1-rc1','https://evil.example','999999999999999999999999'] as $candidate) {
    $s=[];
    $out=UpdateAdminNotification::check('2.5.0',$s,static fn(string $v):array=>['status'=>'available','latest_version'=>$candidate],$time);
    $check($out['status'] === 'not_available', 'Non-newer or invalid release not announced: ' . $candidate);
}
$s=[];
$out=UpdateAdminNotification::check('2.5.0',$s,static fn(string $v): array=>['status'=>'current','latest_version'=>'2.5.0'],$time);
$check($out['status'] === 'not_available', 'Current version produces no notice');
$s=[];
$out=UpdateAdminNotification::check('2.5.0',$s,static fn(string $v): array=>['status'=>'no_release','latest_version'=>''],$time);
$check($out['status'] === 'not_available', 'No stable release produces no notice');
$s=[];$hits=0;
$broken=static function(string $v) use (&$hits): array {$hits++; throw new RuntimeException('private transport error');};
$check(UpdateAdminNotification::check('2.5.0',$s,$broken,$time)['status'] === 'not_available', 'Transport exception fails quietly');
$check(UpdateAdminNotification::check('2.5.0',$s,$broken,$time+60)['status'] === 'not_available' && $hits === 1, 'Failed lookup throttled for five minutes');
$check(UpdateAdminNotification::check('2.5.0',$s,$broken,$time+300)['status'] === 'not_available' && $hits === 2, 'Failed lookup retries after five minutes');
$s=[];$s['boilerplate_admin_update_notice_v1']=['status'=>'available','latest_version'=>'99.0.0','installed'=>'2.5.0','checked_at'=>$time,'ttl'=>3600];
$check(UpdateAdminNotification::check('2.5.0',$s,static fn(string $v):array=>['status'=>'current','latest_version'=>'2.5.0'],$time)['status'] === 'available', 'Cache can render a previously validated newer version (session data is not installation authority)');
$s=[];$s['boilerplate_admin_update_notice_v1']=['status'=>'available','latest_version'=>'https://bad','installed'=>'2.5.0','checked_at'=>$time,'ttl'=>3600];
$check(UpdateAdminNotification::check('2.5.0',$s,static fn(string $v):array=>['status'=>'current','latest_version'=>'2.5.0'],$time)['status'] === 'not_available', 'Malformed cache cannot render untrusted version');
$s=[];$s['boilerplate_admin_update_notice_v1']=['status'=>'available','latest_version'=>'2.5.1','installed'=>'2.5.0','checked_at'=>$time+20,'ttl'=>3600];
$check(UpdateAdminNotification::check('2.5.0',$s,static fn(string $v):array=>['status'=>'current','latest_version'=>'2.5.0'],$time)['status'] === 'not_available', 'Future session timestamp is not trusted');
$s=[];
$check(UpdateAdminNotification::check('2.5.0',$s,static fn(string $v):array=>['status'=>'available','latest_version'=>['evil']],$time)['status'] === 'not_available', 'Malformed lookup result cannot render');
$s=[];
$check(UpdateAdminNotification::check('2.5.0',$s, static fn(string $v):array=>['status'=>'available','latest_version'=>'2.5.1-beta.3'],$time,'all')['status']==='available', 'Dashboard all-releases notification includes Beta');
$controller=file_get_contents(dirname(__DIR__).'/public_html/admin/index.php');
$view=file_get_contents(dirname(__DIR__).'/public_html/admin/views/dashboard.php');
$check(str_contains($controller, "\$auth->can('tools.manage')"), 'Dashboard notification restricted to tools.manage capability');
$check(str_contains($controller, "UpdateAdminNotification::check"), 'Dashboard wired to explicit read-only notice');
$check(str_contains($view, 'href="/admin/tools/update"'), 'Notification links only to Core update page');
$js=file_get_contents(dirname(__DIR__).'/public_html/admin/assets/js/admin.js');
$check(str_contains($view, "t('updates.available')") && str_contains($view, 'data-dashboard-update-version'), 'Notification uses localized existing Core labels');
$check(str_contains($js, 'version.textContent = data.latest_version') && str_contains($js, "data?.status !== 'available'"), 'Client sets only validated release text, never HTML');
$check(str_contains($controller, "\$mode === 'update-notice'") && str_contains($controller, 'Cache-Control: private, no-store'), 'Update notice endpoint is JSON and noncacheable');
$check(str_contains($controller, 'if (!$auth->can(\'tools.manage\'))') && str_contains($controller, 'http_response_code(403)'), 'Endpoint access denied without tools.manage');
$check(!str_contains($controller, "\$updateNotice = UpdateAdminNotification::check") && str_contains($js, 'fetch(endpoint,'), 'GitHub lookup runs asynchronously, never blocking dashboard HTML');
$check(!str_contains($view, '<script') && !str_contains($view, '<style'), 'Notification adds no scripts or inline CSS');
$check(!str_contains((string)file_get_contents(dirname(__DIR__).'/core/classes/UpdateAdminNotification.php'),'->apply('), 'Notification never runs an installer');
$check(!str_contains((string)file_get_contents(dirname(__DIR__).'/core/classes/UpdateAdminNotification.php'),'file_put_contents('), 'Notification does not write persistent files');

$gripJs = file_get_contents(dirname(__DIR__) . '/public_html/admin/assets/js/admin.js');
$gripPhp = file_get_contents(dirname(__DIR__) . '/public_html/admin/incl/page-builder-functions.php');
$check(str_contains($gripJs, 'Only the explicit grips may start a Page Builder move.'), 'Previously approved grip-only JS protection remains installed');
$check(str_contains($gripJs, "if (event.target.closest('[data-builder-block], [data-grid-child]')) event.preventDefault();"), 'Native drag from Page Builder content remains prevented');
$check(str_contains($gripPhp, 'data-builder-drag'), 'Page Builder grip markup remains in approved PHP template');
$check(str_contains($gripJs, 'data-dashboard-update-notice') && str_contains($gripJs, 'data-grid-child-drag'), 'Merged JS includes both update notification and grip drag support');
echo "Update Admin Notification QA1: $pass PASS / $fail FAIL (injected transports; no HTTP, SQL or updates)\n";
exit($fail ? 1 : 0);
