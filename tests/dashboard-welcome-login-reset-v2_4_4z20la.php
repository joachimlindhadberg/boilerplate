<?php
/** V2.4.4z20l-a — login-scoped 12-hour welcome reset contract. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$pass = 0; $fail = 0;
$check = static function(bool $ok, string $label) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
    $ok ? ++$pass : ++$fail;
};
$get = static fn(string $rel): string => (string)file_get_contents($root . '/' . $rel);
$authCode = $get('core/classes/Auth.php');
$js = $get('public_html/admin/assets/js/admin.js');
$view = $get('public_html/admin/views/dashboard.php');
$controller = $get('public_html/admin/index.php');
$check(str_contains($authCode, "'welcome_login_marker' => \$resetLastSeen"), 'Login marker issued only for a fresh authentication');
$check(str_contains($authCode, 'bin2hex(random_bytes(16))'), 'Marker cryptographically unpredictable and generated per login');
$check(str_contains($authCode, "(string)(\$_SESSION['auth']['welcome_login_marker'] ?? '')"), 'Refresh retains the original session marker');
$check(str_contains($authCode, '$this->writeSession($user);') && str_contains($authCode, '$this->writeSession($user, false);'), 'Password and remember authentication both use fresh marker; refresh does not');
$check(str_contains($controller, "'user' => \$auth->user()"), 'Dashboard obtains session-backed auth user');
$check(!str_contains($view, 'data-login-marker='), 'Onboarding no longer depends on the login marker');
$check(str_contains($js, 'dashboard-welcome.v3.user-') && str_contains($js, 'dashboard-welcome.v2.user-'), 'New state key and legacy preference migration both present');
$check(!str_contains($js, 'welcomeIntervalMs') && !str_contains($js, 'lastShownAt'), 'Onboarding no longer reappears on a timer');
$check(str_contains($js, "saved === 'dismissed'"), 'Previously dismissed onboarding remains hidden');
$check(str_contains($js, "localStorage.getItem(legacyKey) !== null"), 'Existing v2 preferences suppress surprise reappearance');
$check(str_contains($js, "localStorage.setItem(storageKey, 'dismissed')"), 'Legacy preference promoted to v3');
$check(str_contains($js, 'welcomeRestore.focus({ preventScroll: true })') && str_contains($js, 'close.focus({ preventScroll: true })'), 'Close and restore preserve focus management');
$check(str_contains($view, 'admin-modal__close') && !str_contains($view, 'data-modal-close'), 'Original modal-header close design preserved');
$check($get('public_html/admin/assets/scss/admin.scss') === $get('public_html/admin/assets/css/admin.css'), 'Core SCSS/CSS stay byte-identical');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'/", $get('public_html/incl/init.php'), $versionMatch) === 1
    && version_compare($versionMatch[1], '2.4.4.99', '>='), 'Internal version at least 2.4.4.99');
$check(is_file($root . '/docs/releases/DASHBOARD-WELCOME-LOGIN-RESET-V2.4.4z20l-a.md'), 'Release notes present');

// Exercise the real Auth::writeSession implementation in isolation, no database connection.
require_once $root . '/core/classes/Translator.php';
require_once $root . '/core/classes/Auth.php';
Translator::boot('da');
$dummy = (new ReflectionClass(Auth::class))->newInstanceWithoutConstructor();
$write = new ReflectionMethod(Auth::class, 'writeSession');
$user = [
    'id' => 41, 'role_id' => 1, 'role_name' => 'administrator',
    'firstname' => 'Test', 'lastname' => 'User', 'email' => 'test@example.invalid',
    'must_change_password' => false,
];
$write->invoke($dummy, $user, true);
$first = (string)($_SESSION['auth']['welcome_login_marker'] ?? '');
$check((bool)preg_match('/^[a-f0-9]{32}$/', $first), 'Real PHP fresh login generates 128-bit hex marker');
$write->invoke($dummy, $user, false);
$check(($_SESSION['auth']['welcome_login_marker'] ?? '') === $first, 'Real PHP refresh preserves existing login marker');
$write->invoke($dummy, $user, true);
$second = (string)($_SESSION['auth']['welcome_login_marker'] ?? '');
$check((bool)preg_match('/^[a-f0-9]{32}$/', $second) && $second !== $first, 'Second login creates a distinct marker');
unset($_SESSION['auth']);

$fixture = $root . '/tests/fixtures/dashboard-welcome-browser-v2_4_4z20l.php';
$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture) . ' da 41 Test 1 1 ' . escapeshellarg($first);
$render = (string)shell_exec($cmd);
$check(!str_contains($render, 'data-login-marker='), 'Dashboard markup does not expose unused login marker');
$check(str_contains($render, 'data-user-id="41"'), 'Account-scoped storage identity preserved');
$check(substr_count($render, 'class="admin-dashboard-welcome__link"') === 3, 'New onboarding actions rendered for administrator');
$check(str_contains($render, 'data-dashboard-welcome-close'), 'Actual rendered modal-style close still present');
$evil = (string)shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture) . ' da 41 Test 1 1 ' . escapeshellarg('" onmouseover="alert(1)'));
$check(!str_contains($evil, '<script>'), 'Untrusted login marker never appears in HTML');
echo "Dashboard Welcome Login Reset z20l-a: $pass PASS / $fail FAIL\n";
exit($fail ? 1 : 0);
