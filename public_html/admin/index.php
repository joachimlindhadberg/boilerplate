<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/incl/init.php';
require_once __DIR__ . '/incl/functions.php';

header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$auth = new Auth();
$mode = strtolower(trim((string)($_GET['mode'] ?? '')));
// `mode` belongs to the active module when the request targets a module route.
// Core-only actions such as login/logout must never intercept module modes.
$moduleRoute = trim((string)($_GET['legacy_path'] ?? ''), '/');

if ($moduleRoute === '' && $mode === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        if ($auth->check()) {
            $auth->logout();
        }
    }
    admin_redirect('/admin');
}

if (!$auth->check()) {
    if ($moduleRoute === '' && $mode === 'authenticate') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            admin_redirect('/admin');
        }

        // Ignore malformed array payloads instead of emitting PHP warnings or
        // accidentally coercing input submitted by a scripted client.
        $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $remember = ($_POST['remember'] ?? null) === '1';
        $error = null;

        if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
            $error = 'Sessionen er udløbet. Genindlæs siden og prøv igen.';
        } else {
            try {
                $guard = admin_login_throttle($email);
                if (!$guard['allowed']) {
                    http_response_code(429);
                    $_SESSION['admin_login_retry_after'] = $guard['retry_after'];
                    $error = 'For mange loginforsøg. Vent et øjeblik og prøv igen.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
                    $error = 'E-mail eller adgangskode er forkert.';
                } elseif ($auth->login($email, $password, $remember)) {
                    admin_rotate_csrf();
                    admin_redirect($auth->mustChangePassword()
                        ? '/admin/index.php?mode=change-password&login=ok' : '/admin?login=ok');
                } else {
                    $error = 'E-mail eller adgangskode er forkert.';
                }
            } catch (Throwable $e) {
                // Fail closed: never bypass the guard when storage is unavailable.
                error_log('Authentication rate limiter unavailable: ' . $e->getMessage());
                http_response_code(503);
                $error = 'Login er midlertidigt utilgængeligt. Prøv igen senere.';
            }
        }

        admin_set_login_flash($error, $email, $remember);
        admin_redirect('/admin');
    }

    $flash = admin_pull_login_flash();
    $error = $flash['error'] ?? null;
    $email = (string)($flash['email'] ?? '');
    $remember = !empty($flash['remember']);

    if (($_GET['login'] ?? '') === 'ok') {
        $error = 'Login blev godkendt, men sessionen kunne ikke bevares. Kontrollér browserens cookies og PHP-sessionens konfiguration.';
    }

    admin_render('login', [
        'error' => $error,
        'email' => $email,
        'remember' => $remember,
        'retryAfter' => (int)($flash['retry_after'] ?? 0),
    ]);
    exit;
}

// Personal language preference is available to any authenticated admin user.
if ($moduleRoute === '' && $mode === 'set-language') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit;
    }
    $preferred = (string)($_POST['admin_language'] ?? '');
    if ($preferred !== '' && !Translator::supported($preferred)) {
        http_response_code(422);
        exit;
    }
    (new Users())->setAdminLanguage((int)$auth->id(), $preferred === '' ? null : $preferred);
    $auth->refresh();
    $return = (string)($_POST['return_to'] ?? '/admin');
    $path = parse_url($return, PHP_URL_PATH);
    if (!is_string($path) || !preg_match('~^/admin(?:/|$)~', $path) || str_starts_with($return, '//')) {
        $return = '/admin';
    }
    admin_redirect($return);
}

if (!$auth->canAccessAdmin()) {
    http_response_code(403);
    admin_render('forbidden', ['user' => $auth->user()]);
    exit;
}

if ($auth->mustChangePassword() && ($moduleRoute !== '' || $mode !== 'change-password')) {
    admin_redirect('/admin/index.php?mode=change-password');
}

// Read-only, authenticated badge snapshot for automatic main-menu refresh.
// This endpoint never mutates submissions, modules or session permissions.
if ($moduleRoute === '' && $mode === 'nav-badges') {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(['badges' => admin_nav_badges()], JSON_THROW_ON_ERROR);
    exit;
}

// Dashboard polls this endpoint AFTER rendering, so a slow or unavailable
// GitHub API never blocks the main admin page. Only tools managers may check.
if ($moduleRoute === '' && $mode === 'update-notice') {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        echo '{"status":"not_available","latest_version":""}';
        exit;
    }
    if (!$auth->can('tools.manage')) {
        http_response_code(403);
        echo '{"status":"not_available","latest_version":""}';
        exit;
    }
    $channel = UpdateReleaseVersion::channel((string)(new Settings())->get('update_channel', 'stable'));
    $notice = UpdateAdminNotification::check((string)BOILERPLATE_VERSION, $_SESSION, null, null, $channel);
    echo json_encode($notice, JSON_THROW_ON_ERROR);
    exit;
}

if ($moduleRoute === '' && $mode === 'change-password') {
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');

        if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
            $error = 'Sessionen er udløbet. Genindlæs siden og prøv igen.';
        } elseif (strlen($password) < 12) {
            $error = 'Den nye adgangskode skal være mindst 12 tegn.';
        } elseif ($password !== $confirm) {
            $error = 'De to adgangskoder er ikke ens.';
        } elseif (!$auth->changePassword($password)) {
            $error = 'Adgangskoden kunne ikke ændres. Prøv igen.';
        } else {
            admin_rotate_csrf();
            admin_redirect('/admin');
        }
    }

    admin_render('change-password', [
        'error' => $error,
        'user' => $auth->user(),
        'forced' => $auth->mustChangePassword(),
    ]);
    exit;
}

// RC5b: same authorized per-user selection as RC5, with a JSON-only AJAX response.
// No unauthenticated read/write, no client-supplied user ID, URL, SQL or HTML.
if ($moduleRoute === '' && $mode === 'dashboard-cards-ajax') {
    $respond = static function (int $code, array $data): never {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        echo json_encode($data, JSON_THROW_ON_ERROR);
        exit;
    };
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        header('Allow: POST');
        $respond(405, ['ok' => false, 'error' => 'method']);
    }
    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        $respond(403, ['ok' => false, 'error' => 'csrf']);
    }
    try {
        $service = new DashboardCards();
        $activeModules = $service->activeModules();
        $available = DashboardCards::available((string)$auth->role(), $activeModules);
        $selection = DashboardCards::validate($_POST['cards'] ?? null, $available);
        $service->save((int)$auth->id(), $selection);
        $baseCounts = admin_dashboard_data()['counts'];
        $counts = array_merge($baseCounts, $service->extraCounts($selection, $activeModules));
        $cards = [];
        foreach ($selection as $metricKey) {
            $option = $available[$metricKey];
            $cards[] = [
                'key' => $metricKey,
                'label' => t($option['label']),
                'count' => isset($counts[$metricKey]) ? (string)(int)$counts[$metricKey] : '—',
                'url' => $option['url'],
            ];
        }
        $respond(200, ['ok' => true, 'cards' => $cards]);
    } catch (InvalidArgumentException $e) {
        $respond(422, ['ok' => false, 'error' => 'invalid']);
    } catch (Throwable $e) {
        error_log('Dashboard AJAX preferences: ' . $e->getMessage());
        $respond(503, ['ok' => false, 'error' => 'unavailable']);
    }
}

// RC5: per-user dashboard customization; CSRF + authenticated role + PRG.
// Do not accept a posted user ID, SQL, URLs, or arbitrary metric definitions.
if ($moduleRoute === '' && $mode === 'dashboard-cards') {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }
    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        admin_render('forbidden', ['user' => $auth->user()]);
        exit;
    }
    try {
        $service = new DashboardCards();
        $options = DashboardCards::available((string)$auth->role(), $service->activeModules());
        $selection = DashboardCards::validate($_POST['cards'] ?? null, $options);
        $service->save((int)$auth->id(), $selection);
        admin_redirect('/admin?cards=saved');
    } catch (InvalidArgumentException $e) {
        admin_redirect('/admin?cards=invalid');
    } catch (Throwable $e) {
        error_log('Dashboard preference save: ' . $e->getMessage());
        admin_redirect('/admin?cards=error');
    }
}

if ($moduleRoute !== '') {
    try {
        $moduleManager = admin_module_manager();
        $resolvedRoute = $moduleManager->resolveAdminRoute($moduleRoute);
        if ($resolvedRoute !== null) {
            $requiredCapability = (string)($resolvedRoute['capability'] ?? 'admin.access');
            if (!$auth->can($requiredCapability)) {
                http_response_code(403);
                admin_render('forbidden', ['user' => $auth->user()]);
                exit;
            }

            $requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            if (!in_array($requestMethod, (array)($resolvedRoute['methods'] ?? ['GET']), true)) {
                http_response_code(405);
                header('Allow: ' . implode(', ', (array)$resolvedRoute['methods']));
                admin_render('not-found', ['user' => $auth->user()]);
                exit;
            }

            // Single CSRF gate for all state-changing plugin-admin requests.
            // Existing module-level checks stay in place as defense in depth.
            if ($requestMethod === 'POST' && !AdminRequest::csrfValid($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                admin_render('forbidden', ['user' => $auth->user()]);
                exit;
            }

            $moduleManager->invokeAdminRoute($resolvedRoute, [
                'auth' => $auth,
                'user' => $auth->user(),
                'route' => $moduleRoute,
                'nav_key' => (string)($resolvedRoute['nav_key'] ?? ''),
            ]);
            exit;
        }
    } catch (Throwable $e) {
        error_log('Module admin dispatch [' . $moduleRoute . ']: ' . $e->getMessage());
        if (!AppConfig::isProduction()) {
            throw $e;
        }
        http_response_code(500);
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Module error · Boilerplate</title></head><body><main><h1>The module could not be loaded.</h1><p>Please try again shortly.</p></main></body></html>';
        exit;
    }
}

if ($mode !== '' || $moduleRoute !== '') {
    http_response_code(404);
    admin_render('not-found', ['user' => $auth->user()]);
    exit;
}

// Getting Started: resolve the homepage edit destination server-side, with existing
// role checks. A missing homepage falls back to the authorized pages list.
$canManageContent = $auth->can('content.manage');
$homePageEditUrl = null;
if ($canManageContent) {
    try {
        $home = (new Pages())->findBySlug('forside');
        $homePageEditUrl = $home && (int)($home['id'] ?? 0) > 0
            ? '/admin/sider?mode=edit&id=' . (int)$home['id']
            : '/admin/sider';
    } catch (Throwable $e) {
        error_log('Dashboard homepage shortcut: ' . $e->getMessage());
        $homePageEditUrl = '/admin/sider';
    }
}

admin_render('dashboard', [
    'user' => $auth->user(),
    'activeNav' => 'dashboard',
    'dashboard' => admin_dashboard_data(),
    'dashboardCards' => admin_dashboard_cards_data($auth),
    'canViewUpdateNotice' => $auth->can('tools.manage'),
    'canViewActivityLog' => $auth->can('tools.manage'),
    'canManageContent' => $canManageContent,
    'canManageSettings' => $auth->can('settings.manage'),
    'homePageEditUrl' => $homePageEditUrl,
    'canManageMedia' => $auth->can('media.manage'),
]);
