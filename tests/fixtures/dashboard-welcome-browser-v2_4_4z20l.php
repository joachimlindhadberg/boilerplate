<?php
/** Render the real Dashboard welcome PHP fragment without touching any database. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__, 2);
require_once $root . '/core/classes/AdminEscaper.php';
require_once $root . '/core/classes/AdminIcons.php';
require_once $root . '/core/classes/Translator.php';
function t(string $key, array $args = []): string { return Translator::get($key, $args); }
function admin_e(mixed $value): string { return AdminEscaper::text($value); }
function admin_icon(string $name, string $class = ''): string { return AdminIcons::render($name, $class); }
$locale = $argv[1] ?? 'da';
Translator::boot('da');
Translator::setLocale($locale);
$user = ['id' => (int)($argv[2] ?? 81), 'firstname' => (string)($argv[3] ?? 'Test'), 'welcome_login_marker' => (string)($argv[6] ?? str_repeat('a', 32))];
$canManageContent = ($argv[4] ?? '1') === '1';
$canManageMedia = ($argv[5] ?? '1') === '1';
$canManageSettings = ($argv[7] ?? '1') === '1';
$homePageEditUrl = '/admin/sider?mode=edit&id=9';
// CLI fixture can simulate the public, non-secret ID of different installations.
$siteInstanceId = (string)($argv[8] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/D', $siteInstanceId)) $siteInstanceId = '';
$source = file_get_contents($root . '/public_html/admin/views/dashboard.php');
$start = strpos($source, '<div class="admin-page-titlebar admin-page-titlebar--split">');
$end = strpos($source, '<?php if (is_int($overdueSchedules)', $start);
if ($start === false || $end === false || $end <= $start) { throw new RuntimeException('Dashboard welcome fragment not found'); }
ob_start();
eval('?>' . substr($source, $start, $end - $start));
$fragment = (string)ob_get_clean();
echo '<!doctype html><html lang="' . admin_e($locale) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/admin.css"><script src="/admin.js" defer></script></head><body class="nf-admin"><div class="admin-layout"><main class="admin-main" id="admin-main">' . $fragment . '<section class="admin-metrics" aria-label="Stats"><a href="/admin/sider">Published pages</a></section></main></div></body></html>';
