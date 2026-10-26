<?php
/** 4A.6.7a database-free behavior regression; no production files changed. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/classes/Autoload.php';

$oldServer = $_SERVER;
$keys = ['TRUST_PROXY_HEADERS', 'TRUSTED_PROXY_IPS', 'INSTALL_ALLOW_LOCAL_HTTP'];
$oldEnv = [];
foreach ($keys as $key) {
    $oldEnv[$key] = getenv($key);
    putenv($key); // make test cases independent of host configuration
}
$count = 0;
$pass = static function (bool $condition, string $message) use (&$count): void {
    if (!$condition) {
        fwrite(STDERR, 'FAIL 4A.6.7a: ' . $message . "\n");
        exit(1);
    }
    $count++;
};
$run = static function (string $label, array $env, array $server, bool $expectedHttps, bool $expectedLocal = false)
    use ($keys, $pass): void {
    foreach ($keys as $key) {
        putenv($key);
    }
    foreach ($env as $key => $value) {
        putenv($key . '=' . $value);
    }
    $_SERVER = $server;
    $pass(AppConfig::requestIsHttps() === $expectedHttps, $label . ': HTTPS detection');
    $pass(AppConfig::localInstallerHttpAllowed() === $expectedLocal, $label . ': local HTTP boundary');
};
try {
    $direct = ['REMOTE_ADDR'=>'198.51.100.10', 'SERVER_ADDR'=>'192.0.2.10', 'HTTP_HOST'=>'example.com', 'SERVER_NAME'=>'example.com', 'HTTPS'=>'off'];
    $run('direct plaintext', [], $direct, false);
    $run('direct native TLS', [], $direct + ['unused'=>'ignored'], false);
    $run('native TLS on', [], array_replace($direct, ['HTTPS'=>'on']), true);
    $run('native TLS 1', [], array_replace($direct, ['HTTPS'=>'1']), true);
    $run('native TLS true', [], array_replace($direct, ['HTTPS'=>'true']), true);
    $run('unexpected native value', [], array_replace($direct, ['HTTPS'=>'nonsense']), false);
    $run('untrusted spoof default disabled', [], array_replace($direct, ['HTTP_X_FORWARDED_PROTO'=>'https']), false);
    $run('enabled but no allowlist', ['TRUST_PROXY_HEADERS'=>'true'], array_replace($direct, ['HTTP_X_FORWARDED_PROTO'=>'https']), false);
    $run('enabled and wrong peer', ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'127.0.0.1'], array_replace($direct, ['HTTP_X_FORWARDED_PROTO'=>'https']), false);
    $run('exact IPv4 trusted', ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'198.51.100.10'], array_replace($direct, ['HTTP_X_FORWARDED_PROTO'=>'https']), true);
    $run('exact IPv4 trusted HTTP edge', ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'198.51.100.10'], array_replace($direct, ['HTTP_X_FORWARDED_PROTO'=>'http']), false);
    $run('trusted missing header fails closed', ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'198.51.100.10'], array_replace($direct, ['HTTPS'=>'on']), false);
    $run('trusted http overrides backend TLS', ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'198.51.100.10'], array_replace($direct, ['HTTPS'=>'on','HTTP_X_FORWARDED_PROTO'=>'http']), false);
    $run('trusted single uppercase HTTPS', ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'198.51.100.10'], array_replace($direct, ['HTTP_X_FORWARDED_PROTO'=>'HTTPS']), true);
    foreach (['https,http','http,https','https, https','https;http','invalid','',"https\r\nhttp"] as $header) {
        $run('malformed forwarded ' . json_encode($header), ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'198.51.100.10'], array_replace($direct, ['HTTP_X_FORWARDED_PROTO'=>$header]), false);
    }
    $run('narrow CIDR IPv4 accepted', ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'198.51.100.0/24'], array_replace($direct, ['HTTP_X_FORWARDED_PROTO'=>'https']), true);
    $run('narrow CIDR IPv4 rejected', ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'198.51.101.0/24'], array_replace($direct, ['HTTP_X_FORWARDED_PROTO'=>'https']), false);
    $run('multi allowlist', ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'192.0.2.1,198.51.100.10;2001:db8::1'], array_replace($direct, ['HTTP_X_FORWARDED_PROTO'=>'https']), true);
    $run('catch-all IPv4 rejected', ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'0.0.0.0/0'], array_replace($direct, ['HTTP_X_FORWARDED_PROTO'=>'https']), false);
    $run('invalid IP rejected', ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'proxy.example.net'], array_replace($direct, ['HTTP_X_FORWARDED_PROTO'=>'https']), false);
    $ipv6 = array_replace($direct, ['REMOTE_ADDR'=>'2001:db8::ac','HTTP_X_FORWARDED_PROTO'=>'https']);
    $run('IPv6 CIDR accepted', ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'2001:db8::/64'], $ipv6, true);
    $run('IPv6 CIDR excluded', ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'2001:db9::/64'], $ipv6, false);
    $run('IPv6 catchall rejected', ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'::/0'], $ipv6, false);
    $local = ['REMOTE_ADDR'=>'127.0.0.1','SERVER_ADDR'=>'127.0.0.1','HTTP_HOST'=>'boilerplate-preflight.test','SERVER_NAME'=>'boilerplate-preflight.test','HTTPS'=>'off'];
    $run('loopback alone not enough', [], $local, false, false);
    $run('explicit isolated local vhost', ['INSTALL_ALLOW_LOCAL_HTTP'=>'true'], $local, false, true);
    $run('host localhost direct', ['INSTALL_ALLOW_LOCAL_HTTP'=>'true'], array_replace($local, ['HTTP_HOST'=>'localhost:8080','SERVER_NAME'=>'localhost']), false, true);
    $run('loopback proxy with public host denied', ['INSTALL_ALLOW_LOCAL_HTTP'=>'true'], array_replace($local, ['HTTP_HOST'=>'example.com','SERVER_NAME'=>'example.com']), false, false);
    $run('loopback proxy forwarded HTTP denied', ['INSTALL_ALLOW_LOCAL_HTTP'=>'true'], array_replace($local, ['HTTP_X_FORWARDED_PROTO'=>'http']), false, false);
    $run('loopback proxy forwarded HTTPS denied for local HTTP', ['INSTALL_ALLOW_LOCAL_HTTP'=>'true'], array_replace($local, ['HTTP_X_FORWARDED_PROTO'=>'https']), false, false);
    $run('loopback proxy forwarded header presence denied', ['INSTALL_ALLOW_LOCAL_HTTP'=>'true'], array_replace($local, ['HTTP_X_FORWARDED_FOR'=>'127.0.0.1']), false, false);
    $run('external server addr denied', ['INSTALL_ALLOW_LOCAL_HTTP'=>'true'], array_replace($local, ['SERVER_ADDR'=>'203.0.113.12']), false, false);
    $run('external remote addr denied', ['INSTALL_ALLOW_LOCAL_HTTP'=>'true'], array_replace($local, ['REMOTE_ADDR'=>'203.0.113.12']), false, false);
    $run('host and vhost mismatch denied', ['INSTALL_ALLOW_LOCAL_HTTP'=>'true'], array_replace($local, ['SERVER_NAME'=>'localhost']), false, false);
    $run('public DNS hostname with test string denied', ['INSTALL_ALLOW_LOCAL_HTTP'=>'true'], array_replace($local, ['HTTP_HOST'=>'example.test.attacker.net','SERVER_NAME'=>'example.test.attacker.net']), false, false);
    $run('missing local server address denied', ['INSTALL_ALLOW_LOCAL_HTTP'=>'true'], array_diff_key($local, ['SERVER_ADDR'=>true]), false, false);
    $run('HTTPS behind loopback trusted proxy', ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'127.0.0.1'], array_replace($local, ['HTTP_HOST'=>'example.com','SERVER_NAME'=>'example.com','HTTP_X_FORWARDED_PROTO'=>'https']), true, false);
    $run('HTTP edge behind loopback trusted proxy denied', ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'127.0.0.1','INSTALL_ALLOW_LOCAL_HTTP'=>'true'], array_replace($local, ['HTTP_HOST'=>'example.com','SERVER_NAME'=>'example.com','HTTP_X_FORWARDED_PROTO'=>'http']), false, false);
    $run('local HTTP fails with invalid forwarding despite opt-in', ['TRUST_PROXY_HEADERS'=>'true','TRUSTED_PROXY_IPS'=>'127.0.0.1','INSTALL_ALLOW_LOCAL_HTTP'=>'true'], array_replace($local, ['HTTP_X_FORWARDED_PROTO'=>'bogus']), false, false);
} finally {
    $_SERVER = $oldServer;
    foreach ($oldEnv as $key => $value) {
        $value === false ? putenv($key) : putenv($key . '=' . $value);
    }
}
echo '4A.6.7a Trusted Proxy / HTTPS: ' . $count . " PASS / 0 FAIL (DB-free)\n";
