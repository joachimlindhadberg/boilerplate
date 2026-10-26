<?php

declare(strict_types=1);

/** Standalone bootstrap: no normal CMS init.php, DB, modules or authentication. */
$root = dirname(__DIR__);
// Standalone installer must reject unsupported PHP before loading CMS classes.
require_once $root . '/core/bootstrap/php-requirement.php';
boilerplate_require_php_runtime();
require_once $root . '/core/classes/Autoload.php';
header_remove('X-Powered-By');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

if (FirstInstall::configured($root)) {
    header('Location: /admin', true, 303);
    exit;
}
// Installer credentials must never travel across public HTTP.
$https = AppConfig::requestIsHttps();
$local = !$https && AppConfig::localInstallerHttpAllowed();
if (!$https && !$local) {
    http_response_code(403);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>HTTPS required</title><p>The installer requires HTTPS on public servers. If TLS terminates at a proxy, configure trusted proxy addresses on the host. For isolated local HTTP development, explicitly enable INSTALL_ALLOW_LOCAL_HTTP in your loopback-only virtual host.</p></html>';
    exit;
}
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>$https, 'httponly'=>true, 'samesite'=>'Strict']);
    session_start();
}
if (!isset($_SESSION['install_csrf'])) $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
// The keyless first-run wizard is available only with private CMS files outside
// the actual public webroot. A fresh, publicly reachable site is first-come-first-served:
// deploy and finish setup immediately; this check does not authenticate ownership.
$webrootSafe = FirstInstall::hasPrivateWebroot($root, __DIR__, (string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
if (!$webrootSafe) http_response_code(503);
$readyDb = $webrootSafe && isset($_SESSION['install_db']) && is_array($_SESSION['install_db']);
// Navigation is explicit: going Back keeps the tested DB in the private session.
$step = !$webrootSafe ? 'unsafe-webroot' : ($readyDb && ($_SESSION['install_step'] ?? 'website') === 'website' ? 'website' : 'database');
$error = '';
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'GET' && $method !== 'POST') {
    header('Allow: GET, POST'); http_response_code(405); exit;
}
if ($method === 'POST' && $webrootSafe) {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['install_csrf'], $_POST['csrf'])) {
        http_response_code(403);
        $error = 'Your session has expired. Reload the installer.';
    } elseif (!is_string($_POST['action'] ?? null)) {
        http_response_code(400); $error = 'Invalid action.';
    } else {
        try {
            switch ($_POST['action']) {
                case 'db-test':
                    InstallerPreflight::requireReady($root, __DIR__, (string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
                    // Returning from step 2 must not expose the verified DB password in HTML.
                    // Reuse the server-held secret ONLY if all visible connection fields match.
                    $dbInput = $_POST;
                    if (($dbInput['pass'] ?? null) === '' && $readyDb) {
                        $saved = $_SESSION['install_db'];
                        if (is_string($dbInput['host'] ?? null) && is_string($dbInput['name'] ?? null)
                            && is_string($dbInput['user'] ?? null) && is_scalar($dbInput['port'] ?? null)
                            && $dbInput['host'] === $saved['host'] && $dbInput['name'] === $saved['name']
                            && $dbInput['user'] === $saved['user'] && (string)$dbInput['port'] === (string)$saved['port']) {
                            $dbInput['pass'] = $saved['pass'];
                        } else {
                            throw new DomainException('Enter the database password again after changing the connection settings.');
                        }
                    }
                    $config = FirstInstall::validateDb($dbInput);
                    try {
                        $db = FirstInstall::connect($config);
                    } catch (mysqli_sql_exception $e) {
                        error_log('First-install database connection error: ' . $e->getCode());
                        throw new DomainException('Could not connect to the database. Check the host, port, database name and credentials.');
                    }
                    try {
                        FirstInstall::assertEmpty($db);
                        FirstInstall::assertInnoDbSupported($db);
                    } catch (mysqli_sql_exception $e) {
                        error_log('First-install database inspection error: ' . $e->getCode());
                        throw new DomainException('Could not inspect this database. Ask your host to verify database access and InnoDB support. No tables were created.');
                    } finally {
                        $db->close();
                    }
                    session_regenerate_id(true);
                    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
                    $_SESSION['install_db'] = $config;
                    $_SESSION['install_step'] = 'website';
                    $readyDb = true;
                    $step = 'website';
                    break;
                case 'back':
                    if (!$readyDb) throw new DomainException('Test the database connection first.');
                    // Preserve only non-secret website fields. Never persist admin passwords.
                    $draft = [];
                    foreach (['site_name', 'site_url', 'first', 'last', 'email'] as $field) {
                        if (is_string($_POST[$field] ?? null)) {
                            $draft[$field] = substr($_POST[$field], 0, 2048);
                        }
                    }
                    $_SESSION['install_site_draft'] = $draft;
                    $_SESSION['install_step'] = 'database';
                    $step = 'database';
                    break;
                case 'finish':
                    if (!$readyDb) throw new DomainException('Test the database connection first.');
                    // Recheck immediately before any irreversible CREATE TABLE or trigger DDL.
                    InstallerPreflight::requireReady($root, __DIR__, (string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
                    $site = FirstInstall::validateSite($_POST);
                    // Never allow accidental canonical cross-domain redirects at first login.
                    $postedHost = strtolower((string)(parse_url($site['site_url'], PHP_URL_HOST) ?: ''));
                    $currentHost = strtolower((string)(parse_url('//' . (string)($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST) ?: ''));
                    if ($currentHost === '' || $postedHost !== $currentHost) {
                        throw new InvalidArgumentException('The website URL must use the same hostname as the installer.');
                    }
                    if (!$local && !str_starts_with($site['site_url'], 'https://')) {
                        throw new InvalidArgumentException('Public websites must be installed using HTTPS.');
                    }
                    FirstInstall::install($root, $_SESSION['install_db'], $site);
                    unset($_SESSION['install_db'], $_SESSION['install_csrf'], $_SESSION['install_step'], $_SESSION['install_site_draft']);
                    session_regenerate_id(true);
                    // Installation is finished: no verified DB credentials remain in this session.
                    $readyDb = false;
                    $step = 'done';
                    break;
                default:
                    http_response_code(400);
                    throw new DomainException('Invalid action.');
            }
        } catch (InvalidArgumentException | DomainException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            error_log('First-install failed (' . get_class($e) . '): ' . $e->getMessage());
            http_response_code(500);
            $error = 'Installation could not be completed. Existing data was not deleted. The database may be partially created; use a new empty database for another attempt. Contact your administrator and review the server log.';
        }
    }
}
$hostingChecks = $webrootSafe
    ? InstallerPreflight::checks($root, __DIR__, (string)($_SERVER['DOCUMENT_ROOT'] ?? '')) : [];
$hostingReady = InstallerPreflight::ready($hostingChecks);
$escape = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = $escape($_SESSION['install_csrf'] ?? '');
$host = strtolower((string)(parse_url('//' . (string)($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST) ?: 'localhost'));
$incomingAuthority = (string)($_SERVER['HTTP_HOST'] ?? '');
$authorityParts = parse_url('//' . $incomingAuthority);
$authorityPort = is_array($authorityParts) && isset($authorityParts['port']) ? ':' . (int)$authorityParts['port'] : '';
$origin = ($https ? 'https://' : 'http://') . $host . $authorityPort;
$steps = ['database'=>1, 'website'=>2, 'done'=>3];
$number = $steps[$step] ?? 1;
$databaseDraft = $readyDb && is_array($_SESSION['install_db'] ?? null) ? $_SESSION['install_db'] : [];
// When retrying a failed DB request, show the attempted non-secret inputs.
if ($step === 'database' && ($_POST['action'] ?? null) === 'db-test') {
    foreach (['host', 'name', 'port', 'user'] as $field) {
        if (is_string($_POST[$field] ?? null)) $databaseDraft[$field] = $_POST[$field];
    }
}
$siteDraft = is_array($_SESSION['install_site_draft'] ?? null) ? $_SESSION['install_site_draft'] : [];
if ($step === 'website' && ($_POST['action'] ?? null) === 'finish') {
    foreach (['site_name', 'site_url', 'first', 'last', 'email'] as $field) {
        if (is_string($_POST[$field] ?? null)) $siteDraft[$field] = $_POST[$field];
    }
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Installation · Boilerplate CMS</title>
  <link rel="stylesheet" href="/admin/assets/css/admin.css">
  <link rel="stylesheet" href="/assets/install.css?v=4a6.9f1">
  <script src="/assets/install.js?v=4a6.9f" defer></script>
</head>
<body>
<main class="install" aria-labelledby="install-title">
  <header class="install__header">
    <div class="install__header-top">
      <div class="install__brand">Boilerplate CMS</div>
      <span class="install__step">Step <?= $number ?> of 3</span>
    </div>
    <nav class="install__progress" aria-label="Installation progress">
      <?php foreach (['Database', 'Website', 'Complete'] as $index => $label): ?>
        <div class="install__progress-item<?= $index + 1 <= $number ? ' is-active' : '' ?><?= $index + 1 === $number ? ' is-current' : '' ?>"<?= $index + 1 === $number ? ' aria-current="step"' : '' ?>>
          <span class="install__progress-bar" aria-hidden="true"></span>
          <span class="install__progress-label"><?= $label ?></span>
        </div>
      <?php endforeach; ?>
    </nav>
  </header>
  <section class="install__body" id="install-content">
    <?php if ($error !== ''): ?>
      <div class="install__error" role="alert" tabindex="-1" data-install-error>
        <strong>There is a problem</strong>
        <p><?= $escape($error) ?></p>
      </div>
    <?php endif; ?>
    <?php if ($step === 'unsafe-webroot'): ?>
      <h1 id="install-title">Check your webroot</h1>
      <p>For security, the installer only runs when your domain's document root is the public CMS directory (<code>public_html</code>, <code>htdocs</code> or <code>www</code>) and the private CMS files are directly above it.</p>
      <p>Ask your hosting provider to configure the document root and make sure PHP reports the correct path. No database changes have been made.</p>
    <?php elseif ($step === 'database'): ?>
      <h1 id="install-title">Database connection</h1>
      <p>Enter the details for an empty MySQL database created in your hosting control panel.</p>
      <?php if (!$hostingReady): ?>
        <div class="install__error" role="alert">
          <strong>Hosting setup needs attention</strong>
          <p>Some server requirements are not met. Open Technical details below to see what needs fixing, then reload this page.</p>
        </div>
        <details class="install__preflight">
          <summary id="install-preflight-title">Technical details</summary>
          <ul class="install__checks">
            <?php foreach ($hostingChecks as $check): ?>
              <?php if (!$check['required'] || $check['ok']) continue; ?>
              <li class="install__check">
                <span class="install__check-state is-fail">Action required</span>
                <span><strong><?= $escape($check['label']) ?></strong><small><?= $escape($check['detail']) ?></small></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <p class="install__note">No database changes were made by these checks. Contact your hosting provider if you cannot change these settings.</p>
        </details>
      <?php endif; ?>
      <form id="install-db-form" method="post" action="/install.php" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="db-test">
        <div class="install__field admin-field">
          <label for="db-host">Database host</label>
          <input id="db-host" name="host" type="text" value="<?= $escape($databaseDraft['host'] ?? 'localhost') ?>" required maxlength="255" autocomplete="off">
        </div>
        <div class="install__columns">
          <div class="install__field admin-field">
            <label for="db-name">Database name</label>
            <input id="db-name" name="name" type="text" value="<?= $escape($databaseDraft['name'] ?? '') ?>" required maxlength="255" autocomplete="off">
          </div>
          <div class="install__field admin-field">
            <label for="db-port">Port</label>
            <input id="db-port" name="port" type="number" min="1" max="65535" value="<?= $escape($databaseDraft['port'] ?? '3306') ?>" required>
          </div>
        </div>
        <div class="install__field admin-field">
          <label for="db-user">Username</label>
          <input id="db-user" name="user" type="text" value="<?= $escape($databaseDraft['user'] ?? '') ?>" required maxlength="255" autocomplete="off">
        </div>
        <div class="install__field admin-field">
          <label for="db-pass">Password</label>
          <div class="install__password">
            <input id="db-pass" name="pass" type="password"<?= $readyDb ? '' : ' required' ?> autocomplete="off" aria-describedby="db-pass-help">
            <button class="install__password-toggle install__secondary" type="button" data-password-toggle="db-pass" aria-controls="db-pass" aria-pressed="false" aria-label="Show database password" hidden>Show</button>
          </div>
          <small id="db-pass-help" class="install__hint"><?= $readyDb ? 'Leave blank to reuse the verified password if the connection settings are unchanged.' : 'Use the password provided by your hosting provider.' ?></small>
        </div>
      </form>
    <?php elseif ($step === 'website'): ?>
      <?php if (!$hostingReady): ?><div class="install__error" role="alert" tabindex="-1" data-install-error>Hosting requirements have changed. Return to the database step to review them. Installation is blocked before database changes.</div><?php endif; ?>
      <h1 id="install-title">Website and administrator</h1>
      <p>Your database connection has been verified. Set up your website and first administrator.</p>
      <form id="install-site-form" method="post" action="/install.php" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <button class="install__implicit-submit" type="submit" name="action" value="finish" tabindex="-1" aria-hidden="true"<?= $hostingReady ? '' : ' disabled' ?>>Install CMS</button>
        <div class="install__field admin-field">
          <label for="site-name">Website name</label>
          <input id="site-name" name="site_name" type="text" required maxlength="191" value="<?= $escape($siteDraft['site_name'] ?? '') ?>">
        </div>
        <div class="install__field admin-field">
          <label for="site-url">Website URL</label>
          <input id="site-url" name="site_url" type="url" required value="<?= $escape($siteDraft['site_url'] ?? $origin) ?>">
        </div>
        <div class="install__columns">
          <div class="install__field admin-field">
            <label for="first">First name</label>
            <input id="first" name="first" type="text" required maxlength="100" value="<?= $escape($siteDraft['first'] ?? '') ?>" autocomplete="given-name">
          </div>
          <div class="install__field admin-field">
            <label for="last">Last name</label>
            <input id="last" name="last" type="text" required maxlength="100" value="<?= $escape($siteDraft['last'] ?? '') ?>" autocomplete="family-name">
          </div>
        </div>
        <div class="install__field admin-field">
          <label for="email">Administrator email</label>
          <input id="email" name="email" type="email" required maxlength="255" value="<?= $escape($siteDraft['email'] ?? '') ?>" autocomplete="email">
        </div>
        <div class="install__field admin-field">
          <label for="password">Password (at least 12 characters)</label>
          <div class="install__password">
            <input id="password" name="password" type="password" required minlength="12" autocomplete="new-password" aria-describedby="password-help">
            <button class="install__password-toggle install__secondary" type="button" data-password-toggle="password" aria-controls="password" aria-pressed="false" aria-label="Show administrator password" hidden>Show</button>
          </div>
          <small class="install__hint" id="password-help">Use a unique password. For your security, it is not saved when you go back.</small>
        </div>
        <p class="install__note">Installation creates database tables and cannot be automatically rolled back. There is no default administrator account.</p>
      </form>
    <?php else: ?>
      <div class="install__complete-icon" aria-hidden="true">✓</div>
      <h1 id="install-title">Installation complete</h1>
      <p>Your website, database tables and administrator account are ready. You can now sign in.</p>
      <p class="install__note">Keep your <code>.env</code> file private and make a backup. The installer is locked once configuration exists.</p>
    <?php endif; ?>
  </section>
  <footer class="install__footer" aria-label="Installation actions">
    <?php if ($step === 'database'): ?>
      <button type="submit" form="install-db-form"<?= $hostingReady ? '' : ' disabled aria-disabled="true"' ?>>Test connection <span aria-hidden="true">→</span></button>
    <?php elseif ($step === 'website'): ?>
      <button type="submit" form="install-site-form" name="action" value="back" formnovalidate class="install__secondary"><span aria-hidden="true">←</span> Back</button>
      <button type="submit" form="install-site-form" name="action" value="finish"<?= $hostingReady ? '' : ' disabled aria-disabled="true"' ?>>Install CMS <span aria-hidden="true">→</span></button>
    <?php elseif ($step === 'done'): ?>
      <a class="install__button" href="/admin">Go to administration <span aria-hidden="true">→</span></a>
    <?php else: ?>
      <span class="install__footer-hint">Installation is unavailable until your webroot is configured correctly.</span>
    <?php endif; ?>
  </footer>
</main>
</body>
</html>
