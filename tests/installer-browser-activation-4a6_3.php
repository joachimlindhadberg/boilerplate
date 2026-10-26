<?php

declare(strict_types=1);
/** Isolated filesystem only; does not open DB or modify any real CMS install. */
$cms = dirname(__DIR__);
require_once $cms . '/core/classes/Autoload.php';
$fixture = sys_get_temp_dir() . '/cms-qa-4a63-' . bin2hex(random_bytes(9));
$clean = static function (string $p) use (&$clean): void {
    if (is_link($p) || is_file($p)) { @unlink($p); return; }
    if (!is_dir($p)) return;
    foreach (scandir($p) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') $clean($p . '/' . $entry);
    }
    @rmdir($p);
};
$pass = 0;
$check = static function (bool $yes, string $label) use (&$pass): void {
    if (!$yes) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ' . $label . "\n";
    $pass++;
};
$make = static function (string $suffix) use ($fixture): array {
    $root = $fixture . '/' . $suffix;
    mkdir($root . '/public_html', 0700, true);
    return [$root, $root . '/public_html'];
};
try {
    [$root,$public] = $make('normal');
    $check(FirstInstall::hasPrivateWebroot($root,$public,$public), 'private parent and actual public webroot accepted');
    $check(!FirstInstall::hasPrivateWebroot($root,$public,$root), 'exposed project root rejected');
    $check(!FirstInstall::hasPrivateWebroot($root,$public,''), 'missing document root rejected');
    $check(!FirstInstall::hasPrivateWebroot($root,$public,$fixture), 'parent document root rejected');
    $check(!is_dir($root.'/storage'), 'no installer key or storage created during read-only safety checks');
    [$other,$otherPub] = $make('existing');
    file_put_contents($other.'/.env',"DB_NAME='synthetic'\n");
    $check(FirstInstall::configured($other), 'existing config locks the installer even if DB is inaccessible');
    $check(!FirstInstall::configured($root), 'fresh unconfigured site accepted');
    $css=file_get_contents($cms.'/public_html/assets/install.css');
    $scss=file_get_contents($cms.'/public_html/assets/scss/install.scss');
    $check($css===$scss,'SCSS and compiled installer CSS stay synchronized');
    $check(str_contains($css, 'height: min(680px, calc(100dvh - 32px))') && preg_match('/\.install__body\s*\{[^}]*overflow-y:\s*auto/s', $css) === 1, 'wizard body scrolls inside fixed header/footer');
    $html=file_get_contents($cms.'/public_html/install.php');
    $check(str_contains($html, '$webrootSafe') && str_contains($html, 'install_csrf'), 'webroot and CSRF guards retained');
    $check(!str_contains($html, "case 'authorize':") && !str_contains($html,'install_authorized') && !str_contains($html,'name="key"'), 'no key authorization step or key field');
    $check(str_contains($html,"'database'=>1, 'website'=>2, 'done'=>3") && str_contains($html, 'Step <?= $number ?> of 3'), 'three-step progress');
    $check(str_contains($html,"if (!\$readyDb) throw new DomainException('Test the database connection first.');"), 'finish action requires previously tested database session');
    $check(str_contains($html, "unset(\$_SESSION['install_db'], \$_SESSION['install_csrf'], \$_SESSION['install_step'], \$_SESSION['install_site_draft']);"), 'successful setup clears temporary credentials and drafts');
    $check(!str_contains($html,'install.key') && !str_contains(file_get_contents($cms.'/core/classes/FirstInstall.php'),'CMS_INSTALL_KEY'), 'no active installation key references');
    echo "Keyless installer / layout QA: {$pass} PASS / 0 FAIL\n";
} finally {
    $clean($fixture);
}
