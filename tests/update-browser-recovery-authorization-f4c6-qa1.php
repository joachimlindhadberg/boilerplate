<?php

declare(strict_types=1);

/** F4c.6: isolated policy-only regression; NO SQL or real HTTP route. */
$root = dirname(__DIR__);
foreach ([
    'core/classes/CapabilityRegistry.php',
    'core/classes/Auth.php',
    'core/classes/AdminToolsRoute.php',
    'core/classes/UpdateDurableRecoveryGate.php',
    'public_html/admin/incl/functions.php',
    'core/classes/UpdateBrowserRecoveryAuthorizationQa.php',
] as $file) {
    require_once $root . '/' . $file;
}

class F4c6FakeAuth extends Auth
{
    /** Real Auth::check(), ::id(), ::can(), and capability registry are used. */
    public function __construct() {}
}

$pass = 0;
$errors = [];
function f4c6_expect(bool $ok, string $name): void
{
    global $pass, $errors;
    if ($ok) {
        $pass++;
        echo "PASS: {$name}\n";
    } else {
        $errors[] = $name;
        echo "FAIL: {$name}\n";
    }
}
function f4c6_private(string $path): void
{
    if (!mkdir($path, 0700) && !is_dir($path)) {
        throw new RuntimeException('Cannot create isolated F4c.6 test fixture.');
    }
    chmod($path, 0700);
}

$parent = realpath('/tmp');
if (!is_string($parent)) throw new RuntimeException('No /tmp');
$name = 'bp-f4c6-browser-' . bin2hex(random_bytes(8));
$fixture = $parent . '/' . $name;
f4c6_private($fixture);
f4c6_private($fixture . '/storage');
f4c6_private($fixture . '/storage/update-recovery');
f4c6_private($fixture . '/public_html');
f4c6_private($fixture . '/public_html/admin');
$script = $fixture . '/public_html/admin/tools.php';
file_put_contents($script, "<?php /* F4c.6 fixture only */\n");
chmod($script, 0600);
$markerId = bin2hex(random_bytes(16));
$marker = $fixture . '/storage/update-recovery/.db-update-pending-' . $markerId;
$token = bin2hex(random_bytes(32));
$journal = [
    'format' => 'boilerplate-f4c6-browser-authorization-qa1',
    'id' => $markerId,
    'phase' => 'manual_recovery',
    'owner_id' => 73,
    'run_sha256' => hash('sha256', $token),
    'blocked' => true,
    'install_ready' => false,
    'production_migration' => false,
];
function f4c6_journal(string $path, array $journal): void
{
    file_put_contents($path, json_encode($journal, JSON_THROW_ON_ERROR));
    chmod($path, 0600);
    clearstatcache(true, $path);
}
f4c6_journal($marker, $journal);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['auth'] = ['id' => 73, 'role' => 'admin'];
$_SESSION['f4c6_qa_owner_id'] = 73;
$_SESSION['f4c6_qa_run_token'] = $token;
$csrf = admin_csrf_token();
$auth = new F4c6FakeAuth();
$server = [
    'REQUEST_URI' => '/admin/tools/update',
    'REQUEST_METHOD' => 'POST',
    'SCRIPT_FILENAME' => $script,
    'SERVER_ADDR' => '127.0.0.1',
    'SERVER_PORT' => '18080',
    'HTTP_HOST' => '127.0.0.1:18080',
    'HTTP_ORIGIN' => 'http://127.0.0.1:18080',
    'HTTP_SEC_FETCH_SITE' => 'same-origin',
];
$post = ['action' => 'f4c6_qa_review_only', 'csrf_token' => $csrf, 'run_token' => $token];
$gate = new UpdateDurableRecoveryGate($fixture);
$policy = new UpdateBrowserRecoveryAuthorizationQa($fixture);
$call = static fn(array $s = [], array $q = [], array $p = [], ?Auth $a = null): array =>
    $policy->review($a ?? $auth, array_replace($server, $s), $q, array_replace($post, $p));
$approved = static fn(array $r): bool => $r['decision'] === 'policy_review_only'
    && $r['blocked'] === true && $r['install_ready'] === false && $r['can_execute_sql'] === false;
$denied = static fn(array $r): bool => $r['decision'] === 'denied'
    && $r['blocked'] === true && $r['install_ready'] === false && $r['can_execute_sql'] === false;

try {
    f4c6_expect($gate->status() === UpdateDurableRecoveryGate::DATABASE, 'F4c.2 sees private database recovery marker');
    f4c6_expect($approved($call()), 'Authenticated admin can review policy only');
    f4c6_expect(!$gate->permitsHttp($script, '/admin/tools/update', 'POST', [], $post), 'F4c.2 still rejects actual DB recovery POST');
    f4c6_expect($gate->permitsHttp($script, '/admin/tools/update', 'GET'), 'F4c.2 allows protected update status GET');
    f4c6_expect($policy->review($auth, array_replace($server, ['REQUEST_METHOD'=>'GET']), [], [])['decision'] === 'read_only_status', 'Read-only recovery status available to admin');
    f4c6_expect($approved($call(['REQUEST_URI'=>'/admin/vaerktoejer/update'])), 'Localized canonical update URL allowed');
    f4c6_expect($approved($call(['REQUEST_URI'=>'/admin/tools.php'], ['mode'=>'update'])), 'Legacy direct update URL requires matching mode');
    f4c6_expect($denied($call(['REQUEST_URI'=>'/admin/tools.php'])), 'Direct tools page without update mode denied');
    f4c6_expect($denied($call(['REQUEST_URI'=>'/admin/tools/data'])), 'Different Tools subroute denied');
    f4c6_expect($denied($call([], ['mode'=>'data'])), 'Conflicting MultiViews mode denied');
    f4c6_expect($denied($call([], ['extra'=>'1'])), 'Unexpected query parameters denied');
    f4c6_expect($denied($call(['REQUEST_URI'=>'/admin/tools/update/'])), 'Ambiguous trailing slash denied');
    f4c6_expect($denied($call(['REQUEST_METHOD'=>'PUT'])), 'PUT denied');
    f4c6_expect($denied($call(['REQUEST_METHOD'=>'DELETE'])), 'DELETE denied');
    f4c6_expect($denied($call(['REQUEST_METHOD'=>'post'])), 'Noncanonical method denied');
    f4c6_expect($denied($call(['HTTP_ORIGIN'=>'https://attacker.example'])), 'Cross-origin POST denied');
    f4c6_expect($denied($call(['HTTP_ORIGIN'=>null])), 'Missing POST Origin denied');
    f4c6_expect($denied($call(['HTTP_HOST'=>'attacker.example'])), 'Host mismatch denied');
    f4c6_expect($denied($call(['HTTP_SEC_FETCH_SITE'=>'cross-site'])), 'Cross-site fetch denied');
    f4c6_expect($denied($call(['SERVER_ADDR'=>'0.0.0.0'])), 'Nonloopback listener denied');
    f4c6_expect($denied($call(['SERVER_PORT'=>'99999'])), 'Invalid port denied');
    f4c6_expect($denied($call(['SCRIPT_FILENAME'=>$root.'/public_html/admin/tools.php'])), 'Production Tools script refused');
    f4c6_expect($denied($call([], [], ['csrf_token'=>str_repeat('f',64)])), 'Invalid CSRF denied');
    f4c6_expect($denied($call([], [], ['csrf_token'=>['array']])), 'Array CSRF denied');
    f4c6_expect($denied($call([], [], ['run_token'=>str_repeat('f',64)])), 'Wrong run token denied');
    f4c6_expect($denied($call([], [], ['run_token'=>['array']])), 'Array run token denied');
    f4c6_expect($denied($call([], [], ['action'=>'install_official_update'])), 'File update action not a DB recovery authorization');
    f4c6_expect($denied($call([], [], ['action'=>'f4c6_run_sql'])), 'SQL action denied');
    f4c6_expect($denied($policy->review($auth, $server, [], array_merge($post, ['sql'=>'DROP TABLE pages']))), 'Extra SQL field denied');
    f4c6_expect($denied($policy->review($auth, $server, [], array_merge($post, ['url'=>'https://a.test']))), 'User-supplied URL denied');
    f4c6_expect($denied($policy->review($auth, $server, [], array_reverse($post, true))), 'Noncanonical field layout denied');

    $_SESSION['auth']['role'] = 'editor';
    f4c6_expect($denied($call()), 'Editor cannot review database recovery action');
    $_SESSION['auth']['role'] = 'user';
    f4c6_expect($denied($call()), 'Ordinary user cannot review database recovery action');
    $_SESSION['auth']['role'] = 'admin';
    $_SESSION['auth']['must_change_password'] = true;
    f4c6_expect($denied($call()), 'Forced password-change session denied');
    unset($_SESSION['auth']['must_change_password']);
    unset($_SESSION['auth']);
    f4c6_expect($denied($call()), 'Unauthenticated session denied');
    $_SESSION['auth'] = ['id'=>73,'role'=>'admin'];
    $_SESSION['auth']['id'] = 74;
    f4c6_expect($denied($call()), 'Other administrator cannot reuse owner journal');
    $_SESSION['auth']['id'] = 73;
    $_SESSION['f4c6_qa_owner_id'] = 74;
    f4c6_expect($denied($call()), 'Session ownership mismatch denied');
    $_SESSION['f4c6_qa_owner_id'] = 73;
    $_SESSION['f4c6_qa_run_token'] = str_repeat('a',64);
    f4c6_expect($denied($call()), 'Rotated or missing session run token denied');
    $_SESSION['f4c6_qa_run_token'] = $token;

    $bad = $journal; $bad['phase']='prepared'; f4c6_journal($marker,$bad);
    f4c6_expect($denied($call()), 'Unapproved journal phase denied');
    $bad = $journal; $bad['blocked']=false; f4c6_journal($marker,$bad);
    f4c6_expect($denied($call()), 'Unblocked marker denied');
    $bad = $journal; $bad['install_ready']=true; f4c6_journal($marker,$bad);
    f4c6_expect($denied($call()), 'Install-ready claim denied');
    $bad = $journal; $bad['production_migration']=true; f4c6_journal($marker,$bad);
    f4c6_expect($denied($call()), 'Production migration claim denied');
    $bad = $journal; $bad['run_sha256']=str_repeat('b',64); f4c6_journal($marker,$bad);
    f4c6_expect($denied($call()), 'Mismatching journal token digest denied');
    $bad = $journal; $bad['owner_id']=74; f4c6_journal($marker,$bad);
    f4c6_expect($denied($call()), 'Journal owner mismatch denied');
    file_put_contents($marker, '{invalid'); chmod($marker,0600);
    f4c6_expect($denied($call()), 'Corrupt recovery JSON denied');
    f4c6_journal($marker,$journal);
    chmod($marker,0644);
    f4c6_expect($denied($call()), 'Publicly readable marker denied');
    chmod($marker,0600);
    file_put_contents($fixture.'/storage/update-recovery/.db-update-pending-'.bin2hex(random_bytes(16)),'{}');
    f4c6_expect($denied($call()), 'Multiple database markers denied');
    foreach (glob($fixture.'/storage/update-recovery/.db-update-pending-*') ?: [] as $file) if ($file !== $marker) unlink($file);
    f4c6_expect($approved($call()), 'Valid private marker resumes policy-only review');
    $before = hash_file('sha256',$marker);
    f4c6_expect($approved($call()) && hash_equals($before, (string)hash_file('sha256',$marker)), 'Policy leaves durable recovery journal byte-identical');
    f4c6_expect($gate->status() === UpdateDurableRecoveryGate::DATABASE, 'Persistent database block retained after review');
    f4c6_expect(!method_exists(UpdateBrowserRecoveryAuthorizationQa::class, 'executeSql'), 'No SQL executor exposed');
    f4c6_expect(!method_exists(UpdateBrowserRecoveryAuthorizationQa::class, 'unlockRecovery'), 'No recovery unlock exposed');
    f4c6_expect(!method_exists(UpdateBrowserRecoveryAuthorizationQa::class, 'install'), 'No production install API exposed');
    f4c6_expect(!str_contains((string)file_get_contents($root.'/public_html/admin/tools.php'),'UpdateBrowserRecoveryAuthorizationQa'), 'Live Tools controller remains disconnected');
    f4c6_expect(!str_contains((string)file_get_contents($root.'/public_html/incl/init.php'),'UpdateBrowserRecoveryAuthorizationQa'), 'Live bootstrap remains disconnected');
} finally {
    if ($errors !== []) {
        fwrite(STDERR, 'NOTE: Private F4c.6 policy QA evidence retained at: ' . $fixture . PHP_EOL);
    } else {
    // The fixture is created by this test in one direct /tmp parent; do not
    // attempt broad glob cleanup or touch any real CMS folders.
    if (is_dir($fixture.'/storage/update-recovery')) {
        foreach (new DirectoryIterator($fixture.'/storage/update-recovery') as $e) {
            if (!$e->isDot() && !$e->isLink() && $e->isFile()) unlink($e->getPathname());
        }
        rmdir($fixture.'/storage/update-recovery');
    }
    if (is_file($script)) unlink($script);
    rmdir($fixture.'/public_html/admin');
    rmdir($fixture.'/public_html');
    rmdir($fixture.'/storage');
    rmdir($fixture);
    }
}
if ($errors !== []) {
    fwrite(STDERR, "F4c.6 policy QA: {$pass} PASS / " . count($errors) . " FAIL\n");
    exit(1);
}
echo "F4c.6 Browser Recovery Authorization QA1: {$pass} PASS / 0 FAIL (policy only; NO SQL or real web routes)\n";
