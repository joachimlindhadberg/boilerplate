<?php

const ADMIN_CRUD_PAGE_SIZE = 10;

function admin_crud_pagination(int $total, ?int $requestedPage = null): array
{
    $total = max(0, $total);
    $perPage = ADMIN_CRUD_PAGE_SIZE;
    $page = max(1, $requestedPage ?? (int)($_GET['page'] ?? 1));
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    return [
        'page' => $page,
        'pages' => $pages,
        'perPage' => $perPage,
        'offset' => $offset,
        'from' => $total > 0 ? $offset + 1 : 0,
        'to' => min($total, $offset + $perPage),
    ];
}

function admin_render_pagination(int $page, int $pages, callable $urlBuilder, string $ariaLabel): void
{
    if ($pages <= 1) {
        return;
    }

    $page = max(1, min($page, $pages));
    $previousUrl = $page > 1 ? (string)$urlBuilder($page - 1) : '#';
    $nextUrl = $page < $pages ? (string)$urlBuilder($page + 1) : '#';
    ?>
    <nav class="admin-pagination" aria-label="<?= admin_e($ariaLabel); ?>">
      <a class="<?= $page <= 1 ? 'is-disabled' : ''; ?>" href="<?= admin_e($previousUrl); ?>" <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>><?= admin_e(t('pagination.previous')); ?></a>
      <span><?= admin_e(t('pagination.page_of', ['page' => $page, 'pages' => $pages])); ?></span>
      <a class="<?= $page >= $pages ? 'is-disabled' : ''; ?>" href="<?= admin_e($nextUrl); ?>" <?= $page >= $pages ? 'aria-disabled="true" tabindex="-1"' : ''; ?>><?= admin_e(t('pagination.next')); ?></a>
    </nav>
    <?php
}
function admin_e(mixed $value): string
{
    return AdminEscaper::text($value);
}

function admin_ensure_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function admin_csrf_token(): string
{
    admin_ensure_session();
    if (empty($_SESSION['admin_csrf']) || !is_string($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function admin_verify_csrf(mixed $token): bool
{
    admin_ensure_session();
    $stored = $_SESSION['admin_csrf'] ?? null;
    return is_string($stored) && is_string($token) && $token !== '' && hash_equals($stored, $token);
}

function admin_rotate_csrf(): void
{
    admin_ensure_session();
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

/**
 * Convert a PHP ini size such as 128M or 1G to bytes.
 * A zero/negative value is treated as unlimited and returned as 0.
 */
function admin_ini_size_bytes(string|false $value): int
{
    if ($value === false) {
        return 0;
    }

    $value = trim($value);
    if ($value === '' || $value === '-1' || $value === '0') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float)$value;
    $multiplier = match ($unit) {
        'g' => 1024 ** 3,
        'm' => 1024 ** 2,
        'k' => 1024,
        default => 1,
    };

    $bytes = (int)floor($number * $multiplier);
    return max(0, $bytes);
}

/**
 * PHP discards both $_POST and $_FILES when post_max_size is exceeded.
 * Detect that case before CSRF validation so a large upload is not reported
 * as an expired session.
 */
function admin_request_exceeds_post_max_size(): bool
{
    $limit = admin_ini_size_bytes(ini_get('post_max_size'));
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    return $limit > 0 && $contentLength > $limit;
}

function admin_redirect(string $path): void
{
    if (!str_starts_with($path, '/') || str_starts_with($path, '//')
        || preg_match('/[\x00-\x1F\x7F\\\\]/', $path)) {
        $path = '/admin';
    }

    // Persist regenerated session IDs and authentication data before the
    // browser follows the redirect. This avoids host-specific session handlers
    // losing the freshly written login state during an immediate redirect.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    header('Location: ' . $path, true, 302);
    exit;
}

function admin_route(): string
{
    $route = trim((string)($_GET['route'] ?? ''), '/');
    if ($route !== '') {
        return strtolower($route);
    }

    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/admin'), PHP_URL_PATH);
    if (!is_string($path)) {
        return '';
    }

    $prefix = '/admin';
    if ($path === $prefix || $path === $prefix . '/') {
        return '';
    }

    if (str_starts_with($path, $prefix . '/')) {
        return strtolower(trim(substr($path, strlen($prefix) + 1), '/'));
    }

    return '';
}

/**
 * Login throttling is persisted in MySQL, never in the PHP session.
 * Count attempts for the client IP as well as valid account identifiers.
 * The IP threshold is intentionally higher to reduce collateral lockouts.
 * @return array{allowed:bool,retry_after:int}
 */
function admin_login_throttle(string $email): array
{
    $limiter = new RateLimiter();
    $ip = RateLimiter::clientIp();
    $ipResult = $limiter->hit('login-ip', $ip, 40, 900);
    $accountResult = ['allowed' => true, 'retry_after' => 0];
    $email = strtolower(trim($email));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $accountResult = $limiter->hit('login-email', $email, 12, 900);
    }
    return [
        'allowed' => $ipResult['allowed'] && $accountResult['allowed'],
        'retry_after' => max($ipResult['retry_after'], $accountResult['retry_after']),
    ];
}

function admin_set_login_flash(?string $error, string $email = '', bool $remember = false): void
{
    admin_ensure_session();
    $_SESSION['admin_login_flash'] = [
        'error' => $error,
        'email' => $email,
        'remember' => $remember,
        'retry_after' => max(0, (int)($_SESSION['admin_login_retry_after'] ?? 0)),
    ];
    unset($_SESSION['admin_login_retry_after']);
}

function admin_pull_login_flash(): array
{
    admin_ensure_session();
    $flash = $_SESSION['admin_login_flash'] ?? [];
    unset($_SESSION['admin_login_flash']);
    return is_array($flash) ? $flash : [];
}

function admin_asset(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $file = PUBLICPATH . $path;
    $version = is_file($file) ? (string)filemtime($file) : '1';
    return $path . '?v=' . rawurlencode($version);
}

function admin_render(string $view, array $data = []): void
{
    // Support module-like view directories while rejecting traversal/absolute paths.
    // A view name is a trusted logical identifier, not a filesystem path from input.
    if (!preg_match('~\A[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*\z~D', $view)) {
        throw new RuntimeException('Invalid admin view name.');
    }
    $viewFile = __DIR__ . '/../views/' . $view . '.php';
    if (!is_file($viewFile)) {
        throw new RuntimeException('Admin view not found.');
    }

    extract($data, EXTR_SKIP);
    require $viewFile;
}

function admin_module_manager(): ModuleManager
{
    return cms_module_manager();
}

function admin_icon(string $name, string $class = ''): string
{
    return AdminIcons::render($name, $class);
}

/** Reusable Core icon-only button. Label must already be localized (e.g. t('Rediger')). */
function admin_icon_action_button(string $name, string $label, array $attributes = [], string $tone = 'neutral'): string
{
    return AdminIcons::actionButton($name, $label, $attributes, $tone);
}

/** Reusable Core icon-only link. Never use a link to perform a destructive action. */
function admin_icon_action_link(string $name, string $label, string $href, array $attributes = [], string $tone = 'neutral'): string
{
    return AdminIcons::actionLink($name, $label, $href, $attributes, $tone);
}

/** Shared, accessible Core switch (native checkbox; module code decides what is saved). */
function admin_toggle_switch(string $id, string $label, bool $checked = false, string $description = '', array $attributes = [], array $stateLabels = []): string
{
    return AdminToggle::render($id, $label, $checked, $description, $attributes, $stateLabels);
}

function admin_nav_items(array $badges = []): array
{
    $currentRole = (string)($_SESSION['auth']['role'] ?? '');
    $canAccessAdmin = Auth::roleCan($currentRole, 'admin.access');
    $canManageContent = Auth::roleCan($currentRole, 'content.manage');
    $canManageMedia = Auth::roleCan($currentRole, 'media.manage');
    $canManageModules = Auth::roleCan($currentRole, 'modules.manage');
    $canManageUsers = Auth::roleCan($currentRole, 'users.manage');
    $canManageTools = Auth::roleCan($currentRole, 'tools.manage');
    $canManageSettings = Auth::roleCan($currentRole, 'settings.manage');

    $items = [
        ['label'=>'Dashboard','href'=>$canAccessAdmin?'/admin':null,'key'=>'dashboard','icon'=>'home','enabled'=>$canAccessAdmin],
        ['label'=>t('Medier'),'href'=>$canManageMedia?'/admin/medier':null,'key'=>'media','icon'=>'image','enabled'=>$canManageMedia],
        ['label'=>t('Sider'),'href'=>$canManageContent?'/admin/sider':null,'key'=>'pages','icon'=>'file','enabled'=>$canManageContent],
        ['label'=>'Navigation','href'=>$canManageContent?'/admin/navigation':null,'key'=>'navigation','icon'=>'menu','enabled'=>$canManageContent],
        ['label'=>'Modules','href'=>$canManageModules?'/admin/modules':null,'key'=>'modules','icon'=>'cube','badge_label'=>'ui.nav_module_updates','enabled'=>$canManageModules,'disabled_title'=>t('Kun administratorer kan administrere modules')],
    ];

    // Active modules may register their own admin navigation, but core keeps
    // the final permission decision. A module cannot make a capability true.
    try {
        $moduleManager = admin_module_manager();
        foreach ($moduleManager->adminMenuItems() as $moduleItem) {
            $capability = (string)($moduleItem['capability'] ?? 'admin.access');
            $enabled = Auth::roleCan($currentRole, $capability);
            $items[] = [
                'label' => (string)$moduleItem['label'],
                'href' => $enabled ? (string)$moduleItem['href'] : null,
                'key' => (string)$moduleItem['key'],
                'icon' => (string)($moduleItem['icon'] ?? 'cube'),
                'badge_label' => (string)($moduleItem['badge_label'] ?? ''),
                'enabled' => $enabled,
                'disabled_title' => t('Du har ikke adgang til dette module'),
            ];
        }
    } catch (Throwable $e) {
        error_log('Module navigation: ' . $e->getMessage());
    }

    $items[] = ['label'=>t('Brugere'),'href'=>$canManageUsers?'/admin/brugere':null,'key'=>'users','icon'=>'user','enabled'=>$canManageUsers,'disabled_title'=>t('Kun administratorer kan administrere brugere')];
    $items[] = ['label'=>t('Værktøjer'),'href'=>$canManageTools?'/admin/tools':null,'key'=>'tools','icon'=>'wrench','enabled'=>$canManageTools,'disabled_title'=>t('Kun administratorer kan bruge værktøjer')];
    $items[] = ['label'=>t('Indstillinger'),'href'=>$canManageSettings?'/admin/indstillinger':null,'key'=>'settings','icon'=>'settings','enabled'=>$canManageSettings,'disabled_title'=>t('Kun administratorer kan ændre globale indstillinger')];

    // Apply badges only to enabled items; never disclose counts on locked menus.
    foreach ($items as &$item) {
        $key = (string)$item['key'];
        $item['badge'] = !empty($item['enabled']) && is_int($badges[$key] ?? null)
            ? max(0, $badges[$key]) : 0;
    }
    unset($item);
    return $items;
}

/** Request-scoped cached snapshot: at most one count query per active provider. */
function admin_nav_badges(): array
{
    static $badges = null;
    if ($badges !== null) {
        return $badges;
    }
    $role = (string)($_SESSION['auth']['role'] ?? '');
    return $badges = AdminNavigationBadges::collect(admin_module_manager(), $role);
}

function admin_nav_badge_markup(array $item): string
{
    $label = (string)($item['badge_label'] ?? '');
    if ($label === '') {
        $label = 'ui.nav_pending_items';
    }
    return AdminNavigationBadges::render((int)($item['badge'] ?? 0), t($label));
}

/**
 * Store a language-independent flash message. The key is translated only on
 * display so a redirected request (or language change) uses the active locale.
 * Parameters are inserted by Translator::get() and escaped by the view.
 */
function admin_set_notice(string $message, string $type = 'success', array $parameters = []): void
{
    admin_ensure_session();
    $_SESSION['admin_notice'] = [
        'key' => $message,
        'parameters' => $parameters,
        'type' => in_array($type, ['success', 'error'], true) ? $type : 'success',
    ];
}

function admin_pull_notice(): ?array
{
    admin_ensure_session();
    $notice = $_SESSION['admin_notice'] ?? null;
    unset($_SESSION['admin_notice']);
    if (!is_array($notice)) {
        return null;
    }

    // The previous version stored a translated 'message'. Retain compatibility
    // for flash data from a request immediately before this code was deployed.
    $key = $notice['key'] ?? $notice['message'] ?? '';
    if (!is_string($key)) {
        return null;
    }
    $parameters = $notice['parameters'] ?? [];
    $notice['message'] = t($key, is_array($parameters) ? $parameters : []);
    unset($notice['key'], $notice['parameters']);
    return $notice;
}

/** Log a completed admin action. A missing migration must not undo a completed save. */
function admin_log_activity(string $action, string $type, ?int $id, string $label): void
{
    try {
        $actorId = isset($_SESSION['auth']['id']) ? (int)$_SESSION['auth']['id'] : null;
        (new ActivityLog())->record($action, $type, $id, $label, $actorId);
    } catch (Throwable $e) {
        error_log('Activity Log write: ' . $e->getMessage());
    }
}

function admin_activity_label(string $action, string $type): string
{
    $map = [
        'created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted',
        'restored' => 'Restored', 'published' => 'Published', 'unpublished' => 'Unpublished',
        'activated' => 'Activated', 'deactivated' => 'Deactivated',
        'uploaded' => 'Uploaded', 'status_changed' => 'Changed status',
    ];
    $subjects = [
        'page'=>'Page', 'media'=>'Media', 'menu'=>'Menu', 'user'=>'User',
        'module'=>'Module', 'settings'=>'Settings', 'redirect'=>'Redirect',
        'form'=>'Form', 'gallery'=>'Gallery', 'post'=>'Post', 'category'=>'Category',
        'submission'=>'Submission', 'form_settings'=>'Form settings',
    ];
    return t('activity.action.' . $action) !== 'activity.action.' . $action
        ? t('activity.action.' . $action) . ' ' . (t('activity.type.' . $type) === 'activity.type.' . $type ? $type : t('activity.type.' . $type))
        : (($map[$action] ?? ucfirst(str_replace('_', ' ', $action))) . ' ' . ($subjects[$type] ?? str_replace('_', ' ', $type)));
}

/**
 * Dashboard data is read-only. Schedule timestamps are stored in UTC; always
 * compare them using MySQL UTC_TIMESTAMP(), never the session timezone.
 *
 * A schedule that remains due for 10+ minutes is overdue, NOT necessarily
 * failed. No alert is shown for normal future jobs or empty schedules.
 */
/**
 * RC5 dashboard: one read-only preference lookup and bounded metric queries.
 * A missing pending migration keeps the previously approved default dashboard available.
 */
function admin_dashboard_cards_data(Auth $auth): array
{
    $service = new DashboardCards();
    $role = (string)($auth->role() ?? '');
    try {
        $modules = $service->activeModules();
    } catch (Throwable $e) {
        error_log('Dashboard module state: ' . $e->getMessage());
        $modules = [];
    }
    $available = DashboardCards::available($role, $modules);
    $needsMigration = false;
    try {
        $selection = $service->load((int)$auth->id(), $available);
    } catch (Throwable $e) {
        error_log('Dashboard preferences: ' . $e->getMessage());
        $selection = DashboardCards::sanitize(null, $available);
        $needsMigration = true;
    }
    return [
        'available' => $available,
        'selection' => $selection,
        'counts' => $service->extraCounts($selection, $modules),
        'needs_migration' => $needsMigration,
    ];
}

function admin_dashboard_data(): array
{
    $data = [
        'counts' => ['media' => null, 'published' => null, 'drafts' => null, 'scheduled' => null],
        'recent_activity' => [],
        'activity_loaded' => false,
        'upcoming_schedules' => [],
        'overdue_schedules' => null,
        'overdue_items' => [],
        'schedules_loaded' => false,
    ];
    try {
        $db = new Db();
        $data['counts']['media'] = (int)$db->fetchValue('SELECT COUNT(*) FROM media');
        $pageCounts = $db->fetchArray(
            "SELECT COALESCE(SUM(CASE WHEN page_status = 'published' THEN 1 ELSE 0 END), 0) AS published_count, "
            . "COALESCE(SUM(CASE WHEN page_status = 'draft' THEN 1 ELSE 0 END), 0) AS draft_count FROM pages"
        );
        $data['counts']['published'] = (int)($pageCounts[0]['published_count'] ?? 0);
        $data['counts']['drafts'] = (int)($pageCounts[0]['draft_count'] ?? 0);
    } catch (Throwable $e) {
        error_log('Dashboard metrics: ' . $e->getMessage());
    }
    try {
        $db ??= new Db();
        $registry = new ScheduledContent($db, admin_module_manager());
        $data['counts']['scheduled'] = $registry->count('upcoming');
        $data['overdue_schedules'] = $registry->count('overdue');
        if ($data['overdue_schedules'] > 0) {
            $data['overdue_items'] = $registry->items('overdue', 5);
        }
        $data['upcoming_schedules'] = $registry->items('upcoming', 5);
        $data['schedules_loaded'] = true;
    } catch (Throwable $e) {
        // A failing provider must not be represented as "no schedules".
        $data['counts']['scheduled'] = null;
        $data['overdue_schedules'] = null;
        $data['upcoming_schedules'] = [];
        $data['overdue_items'] = [];
        error_log('Dashboard schedules: ' . $e->getMessage());
    }
    try {
        $data['recent_activity'] = (new ActivityLog())->latest(5);
        $data['activity_loaded'] = true;
    } catch (Throwable $e) {
        error_log('Dashboard activity: ' . $e->getMessage());
    }
    return $data;
}

function admin_media_data(): array
{
    $media = new MediaLibrary();
    $search = trim((string)($_GET['q'] ?? ''));
    $kind = strtolower(trim((string)($_GET['type'] ?? '')));
    if (!in_array($kind, MediaLibrary::allowedKinds(), true)) {
        $kind = '';
    }

    // Core list tabs show counts for the same search across all media types.
    // Reuse the already-filtered count for pagination; no extra list reads.
    $counts = [
        'all' => $media->countFiltered($search, ''),
        'image' => $media->countFiltered($search, 'image'),
        'video' => $media->countFiltered($search, 'video'),
    ];
    $total = $counts[$kind === '' ? 'all' : $kind];
    $perPage = 24;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    return [
        'items' => $media->getFiltered($search, $kind, $perPage, $offset),
        'search' => $search,
        'kind' => $kind,
        'counts' => $counts,
        'page' => $page,
        'pages' => $pages,
        'total' => $total,
        'perPage' => $perPage,
        'from' => $total > 0 ? $offset + 1 : 0,
        'to' => min($total, $offset + $perPage),
    ];
}

function admin_media_url(array $changes = []): string
{
    $params = [
        'q' => trim((string)($_GET['q'] ?? '')),
        'type' => trim((string)($_GET['type'] ?? '')),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
    ];
    foreach ($changes as $key => $value) {
        $params[$key] = $value;
    }
    $params = array_filter($params, static fn($value, $key): bool => !($value === '' || ($key === 'page' && (int)$value <= 1)), ARRAY_FILTER_USE_BOTH);
    $query = http_build_query($params);
    return '/admin/medier' . ($query !== '' ? '?' . $query : '');
}

function admin_format_bytes(int $bytes): string
{
    $bytes = max(0, $bytes);
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    if ($bytes < 1073741824) return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    return number_format($bytes / 1073741824, 1, ',', '.') . ' GB';
}

function admin_media_kind_label(string $kind): string
{
    return $kind === 'video' ? t('Video') : t('Billede');
}

function admin_page_status_label(string $status): string
{
    return match ($status) {
        'published' => t('Publiceret'),
        'draft' => t('Kladde'),
        'protected' => t('Beskyttet'),
        default => t('Ukendt'),
    };
}

function admin_page_status_class(string $status): string
{
    return in_array($status, Pages::validStatuses(), true) ? 'page-' . $status : 'unknown';
}

function admin_pages_url(array $changes = []): string
{
    $params = [
        'status' => trim((string)($_GET['status'] ?? '')),
        'q' => trim((string)($_GET['q'] ?? '')),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
    ];
    foreach ($changes as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    if (($params['page'] ?? 1) <= 1) unset($params['page']);
    if (($params['status'] ?? '') === '') unset($params['status']);
    if (($params['q'] ?? '') === '') unset($params['q']);
    $query = http_build_query($params);
    return '/admin/sider' . ($query !== '' ? '?' . $query : '');
}

function admin_pages_data(): array
{
    $pagesModel = new Pages();
    $search = trim((string)($_GET['q'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));
    if (!in_array($status, Pages::validStatuses(), true)) {
        $status = '';
    }
    $total = $pagesModel->countFiltered($search, $status);
    $pagination = admin_crud_pagination($total);
    $items = $pagesModel->getFiltered($search, $status, $pagination['perPage'], $pagination['offset']);
    foreach ($items as &$item) {
        $item['public_url'] = $pagesModel->buildPublicUrl($item);
    }
    unset($item);

    return [
        'items' => $items,
        'counts' => $pagesModel->getStatusCounts(),
        'search' => $search,
        'status' => $status,
        'page' => $pagination['page'],
        'pages' => $pagination['pages'],
        'total' => $total,
        'perPage' => $pagination['perPage'],
        'from' => $pagination['from'],
        'to' => $pagination['to'],
    ];
}

function admin_page_form_payload(array $source): array
{
    $contentMode = (($source['content_mode'] ?? 'builder') === 'legacy') ? 'legacy' : 'builder';
    $form = [
        'page_title' => trim((string)($source['page_title'] ?? '')),
        'page_slug' => trim((string)($source['page_slug'] ?? '')),
        'parent_id' => trim((string)($source['parent_id'] ?? '')),
        'page_description' => trim((string)($source['page_description'] ?? '')),
        'page_content' => (string)($source['page_content'] ?? ''),
        'content_mode' => $contentMode,
        'page_status' => trim((string)($source['page_status'] ?? 'draft')),
        'schedule_publish' => trim((string)($source['schedule_publish'] ?? '')),
        'schedule_unpublish' => trim((string)($source['schedule_unpublish'] ?? '')),
        'menu_order' => trim((string)($source['menu_order'] ?? '')),
        'show_in_menu' => !empty($source['show_in_menu']) ? '1' : '0',
        'meta_title' => trim((string)($source['meta_title'] ?? '')),
        'meta_description' => trim((string)($source['meta_description'] ?? '')),
    ];
    $errors = [];
    $length = static fn(string $value): int => function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);

    if ($form['page_title'] === '') $errors['page_title'] = 'Sidetitel er påkrævet.';
    elseif ($length($form['page_title']) > 255) $errors['page_title'] = 'Sidetitlen må højst være 255 tegn.';
    if ($length($form['page_slug']) > 255) $errors['page_slug'] = 'Slug må højst være 255 tegn.';
    if (!in_array($form['page_status'], Pages::validStatuses(), true)) $errors['page_status'] = 'Vælg en gyldig status.';
    if ($form['menu_order'] !== '' && !preg_match('/^\d+$/', $form['menu_order'])) $errors['menu_order'] = 'Menurækkefølge skal være 0 eller et positivt heltal.';
    if ($length($form['meta_title']) > 255) $errors['meta_title'] = 'SEO-titlen må højst være 255 tegn.';
    if ($form['parent_id'] !== '' && !preg_match('/^\d+$/', $form['parent_id'])) $errors['parent_id'] = 'Vælg en gyldig overordnet side.';

    return [
        'form' => $form,
        'errors' => $errors,
        'data' => [
            'page_title' => $form['page_title'],
            'page_slug' => $form['page_slug'],
            'parent_id' => $form['parent_id'] === '' ? null : (int)$form['parent_id'],
            'page_description' => $form['page_description'],
            'page_content' => $form['page_content'],
            'content_mode' => $form['content_mode'],
            'page_status' => $form['page_status'],
            'menu_order' => $form['menu_order'] === '' ? null : (int)$form['menu_order'],
            'show_in_menu' => $form['show_in_menu'] === '1',
            'meta_title' => $form['meta_title'],
            'meta_description' => $form['meta_description'],
        ],
    ];
}

function admin_page_form_from_record(?array $page = null): array
{
    return [
        'page_title' => (string)($page['page_title'] ?? ''),
        'page_slug' => (string)($page['page_slug'] ?? ''),
        'parent_id' => !empty($page['parent_id']) ? (string)$page['parent_id'] : '',
        'page_description' => (string)($page['page_description'] ?? ''),
        'page_content' => (string)($page['page_content'] ?? ''),
        'content_mode' => $page === null ? 'builder' : (((string)($page['content_mode'] ?? 'legacy') === 'builder') ? 'builder' : 'legacy'),
        'page_status' => (string)($page['page_status'] ?? 'draft'),
        'schedule_publish' => PageScheduler::localInput($page['publish_at_utc'] ?? null),
        'schedule_unpublish' => PageScheduler::localInput($page['unpublish_at_utc'] ?? null),
        'menu_order' => isset($page['menu_order']) && $page['menu_order'] !== null ? (string)$page['menu_order'] : '',
        'show_in_menu' => !empty($page['show_in_menu']) ? '1' : '0',
        'meta_title' => (string)($page['meta_title'] ?? ''),
        'meta_description' => (string)($page['meta_description'] ?? ''),
    ];
}

// Page Builder functions are loaded once and stay in the global admin helper API.
require_once __DIR__ . '/page-builder-functions.php';

function admin_page_public_url(array $page): string
{
    if (!empty($page['public_url'])) {
        return (string)$page['public_url'];
    }
    return (new Pages())->buildPublicUrl($page);
}


/* ================================================================
 * User and settings modules
 * ================================================================ */


function admin_user_status_label(string $status): string
{
    return match ($status) {
        'active' => t('Aktiv'),
        'inactive' => t('Inaktiv'),
        'banned' => t('Blokeret'),
        default => t('Ukendt'),
    };
}

function admin_user_status_class(string $status): string
{
    return in_array($status, Users::validStatuses(), true) ? 'user-' . $status : 'unknown';
}

function admin_user_role_label(string $role): string
{
    return match ($role) {
        'admin' => t('Administrator'),
        'editor' => t('Redaktør'),
        'user' => t('Bruger'),
        default => ucfirst($role !== '' ? $role : 'Ukendt'),
    };
}

function admin_user_role_description(string $role): string
{
    return match ($role) {
        'admin' => t('Fuld adgang til CMS, indhold, brugere, værktøjer og globale indstillinger.'),
        'editor' => t('Adgang til dashboard og alle indholdsmoduler, men ikke Brugere, Værktøjer eller Indstillinger.'),
        'user' => t('Ingen adgang til CMS. Rollen er reserveret til eventuelle fremtidige kunde-/portal-funktioner.'),
        default => t('Ukendt adgangsniveau.'),
    };
}

function admin_users_url(array $changes = []): string
{
    $params = [
        'status' => trim((string)($_GET['status'] ?? '')),
        'role' => max(0, (int)($_GET['role'] ?? 0)),
        'q' => trim((string)($_GET['q'] ?? '')),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
    ];

    foreach ($changes as $key => $value) {
        if ($value === null || $value === '' || ($key === 'role' && (int)$value <= 0)) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    if (($params['page'] ?? 1) <= 1) unset($params['page']);
    if (($params['status'] ?? '') === '') unset($params['status']);
    if (($params['role'] ?? 0) <= 0) unset($params['role']);
    if (($params['q'] ?? '') === '') unset($params['q']);

    $query = http_build_query($params);
    return '/admin/brugere' . ($query !== '' ? '?' . $query : '');
}

function admin_users_data(): array
{
    $users = new Users();
    $search = trim((string)($_GET['q'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));
    $roleId = max(0, (int)($_GET['role'] ?? 0));

    if (!in_array($status, Users::validStatuses(), true)) {
        $status = '';
    }

    $roles = $users->roleOptions();
    $validRoleIds = [];
    foreach ($roles as $role) {
        $validRoleIds[(int)($role['id'] ?? 0)] = true;
    }
    if ($roleId > 0 && !isset($validRoleIds[$roleId])) {
        $roleId = 0;
    }

    $total = $users->countFiltered($search, $status, $roleId);
    $pagination = admin_crud_pagination($total);

    return [
        'items' => $users->getFiltered($search, $status, $roleId, $pagination['perPage'], $pagination['offset']),
        'counts' => $users->getStatusCounts(),
        'activeAdminCount' => $users->countActiveAdminsExcluding(),
        'roles' => $roles,
        'search' => $search,
        'status' => $status,
        'roleId' => $roleId,
        'page' => $pagination['page'],
        'pages' => $pagination['pages'],
        'total' => $total,
        'perPage' => $pagination['perPage'],
        'from' => $pagination['from'],
        'to' => $pagination['to'],
    ];
}

function admin_user_form_payload(array $source, array $roles, ?array $existing = null, int $actorId = 0): array
{
    $isEdit = is_array($existing) && !empty($existing['id']);
    $existingId = $isEdit ? (int)$existing['id'] : 0;
    $form = [
        'firstname' => trim((string)($source['firstname'] ?? '')),
        'lastname' => trim((string)($source['lastname'] ?? '')),
        'email' => strtolower(trim((string)($source['email'] ?? ''))),
        'phone' => trim((string)($source['phone'] ?? '')),
        'role_id' => max(0, (int)($source['role_id'] ?? 0)),
        'status' => trim((string)($source['status'] ?? 'active')),
        'password' => (string)($source['password'] ?? ''),
        'password_confirm' => (string)($source['password_confirm'] ?? ''),
        'must_change_password' => !empty($source['must_change_password']) ? '1' : '0',
    ];

    $errors = [];
    $length = static fn(string $value): int => function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    $validRoleIds = [];
    $roleNamesById = [];
    foreach ($roles as $role) {
        $rid = (int)($role['id'] ?? 0);
        if ($rid > 0) {
            $validRoleIds[$rid] = true;
            $roleNamesById[$rid] = (string)($role['role_name'] ?? '');
        }
    }

    if ($form['firstname'] === '') $errors['firstname'] = 'Fornavn er påkrævet.';
    elseif ($length($form['firstname']) > 100) $errors['firstname'] = 'Fornavn må højst være 100 tegn.';
    if ($form['lastname'] === '') $errors['lastname'] = 'Efternavn er påkrævet.';
    elseif ($length($form['lastname']) > 100) $errors['lastname'] = 'Efternavn må højst være 100 tegn.';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Indtast en gyldig e-mailadresse.';
    elseif ($length($form['email']) > 255) $errors['email'] = 'E-mail må højst være 255 tegn.';
    if ($length($form['phone']) > 30) $errors['phone'] = 'Telefonnummer må højst være 30 tegn.';
    if ($form['role_id'] < 1 || !isset($validRoleIds[$form['role_id']])) $errors['role_id'] = 'Vælg en gyldig rolle.';
    if (!in_array($form['status'], Users::validStatuses(), true)) $errors['status'] = 'Vælg en gyldig status.';

    $passwordRequired = !$isEdit;
    if ($passwordRequired && $form['password'] === '') {
        $errors['password'] = 'Adgangskode er påkrævet.';
    } elseif ($form['password'] !== '' && strlen($form['password']) < 12) {
        $errors['password'] = 'Adgangskoden skal være mindst 12 tegn.';
    }
    if ($form['password'] !== $form['password_confirm']) {
        $errors['password_confirm'] = 'De to adgangskoder er ikke ens.';
    }

    $users = new Users();
    if (!isset($errors['email']) && $users->emailExists($form['email'], $existingId)) {
        $errors['email'] = 'E-mailadressen bruges allerede af en anden bruger.';
    }

    if ($isEdit && $existingId === $actorId) {
        $currentRoleId = (int)($existing['role_id'] ?? 0);
        if ($form['role_id'] !== $currentRoleId) {
            $errors['role_id'] = 'Du kan ikke ændre din egen rolle.';
        }
        if ($form['status'] !== 'active') {
            $errors['status'] = 'Du kan ikke deaktivere eller blokere din egen konto.';
        }
    }

    if ($isEdit && (string)($existing['role_name'] ?? '') === 'admin' && (string)($existing['status'] ?? '') === 'active') {
        $newRole = (string)($roleNamesById[$form['role_id']] ?? '');
        $wouldRemoveActiveAdmin = $newRole !== 'admin' || $form['status'] !== 'active';
        if ($wouldRemoveActiveAdmin && $users->countActiveAdminsExcluding($existingId) < 1) {
            if ($newRole !== 'admin') $errors['role_id'] = 'Den sidste aktive administrator kan ikke nedgraderes.';
            if ($form['status'] !== 'active') $errors['status'] = 'Den sidste aktive administrator kan ikke deaktiveres eller blokeres.';
        }
    }

    return [
        'form' => $form,
        'data' => [
            'firstname' => $form['firstname'],
            'lastname' => $form['lastname'],
            'email' => $form['email'],
            'phone' => $form['phone'],
            'role_id' => $form['role_id'],
            'status' => $form['status'],
            'weekly_hours' => $isEdit ? (float)($existing['weekly_hours'] ?? 0) : 0.0,
            'must_change_password' => $form['must_change_password'] === '1',
        ],
        'newPassword' => $form['password'] !== '' ? $form['password'] : null,
        'forcePasswordChange' => $form['must_change_password'] === '1',
        'errors' => $errors,
    ];
}

function admin_user_form_from_record(?array $user = null): array
{
    $user ??= [];
    return [
        'firstname' => (string)($user['firstname'] ?? ''),
        'lastname' => (string)($user['lastname'] ?? ''),
        'email' => (string)($user['email'] ?? ''),
        'phone' => (string)($user['phone'] ?? ''),
        'role_id' => (int)($user['role_id'] ?? 0),
        'status' => (string)($user['status'] ?? 'active'),
        'password' => '',
        'password_confirm' => '',
        'must_change_password' => !empty($user['must_change_password']) ? '1' : '0',
    ];
}


/* ================================================================
 * Settings admin module
 * ================================================================ */

function admin_setting_bool(mixed $value): bool
{
    $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($parsed !== null) return $parsed;
    return (string)$value === '1';
}

function admin_settings_form_from_model(Settings $settings): array
{
    $adminEmail = trim((string)$settings->get('admin_email', ''));
    $legacyNotificationEmail = trim((string)$settings->get('contact_notification_email', ''));
    $notificationEmail = trim((string)$settings->get('notification_email', $legacyNotificationEmail !== '' ? $legacyNotificationEmail : $adminEmail));
    $timeoutSeconds = max(300, (int)$settings->get('session_timeout', 1800));

    return [
        'admin_language' => Translator::supported((string)$settings->get('admin_language', 'en'))
            ? (string)$settings->get('admin_language', 'en') : 'en',
        'site_name' => trim((string)$settings->get('site_name', 'Boilerplate')),
        'site_tagline' => trim((string)$settings->get('site_tagline', 'CMS boilerplate')),
        'site_url' => rtrim(trim((string)$settings->get('site_url', '')), '/'),
        'admin_email' => $adminEmail,
        'notification_email' => $notificationEmail,
        'timezone' => trim((string)$settings->get('timezone', 'Europe/Copenhagen')),
        'seo_default_description' => trim((string)$settings->get('seo_default_description', 'Generisk CMS boilerplate.')),
        'seo_title_separator' => trim((string)$settings->get('seo_title_separator', '|')),
        'seo_indexing' => admin_setting_bool($settings->get('seo_indexing', 1)) ? '1' : '0',
        'maintenance_mode' => admin_setting_bool($settings->get('maintenance_mode', 0)) ? '1' : '0',
        'maintenance_message' => trim((string)$settings->get('maintenance_message', 'Siden er midlertidigt utilgængelig på grund af vedligeholdelse.')),
        'session_timeout_minutes' => (string)max(5, (int)round($timeoutSeconds / 60)),
        'remember_me_days' => (string)max(1, (int)$settings->get('remember_me_days', 30)),
    ];
}

function admin_settings_form_payload(array $source): array
{
    $form = [
        'admin_language' => trim((string)($source['admin_language'] ?? 'en')),
        'site_name' => trim((string)($source['site_name'] ?? '')),
        'site_tagline' => trim((string)($source['site_tagline'] ?? '')),
        'site_url' => rtrim(trim((string)($source['site_url'] ?? '')), '/'),
        'admin_email' => strtolower(trim((string)($source['admin_email'] ?? ''))),
        'notification_email' => strtolower(trim((string)($source['notification_email'] ?? ''))),
        'timezone' => trim((string)($source['timezone'] ?? 'Europe/Copenhagen')),
        'seo_default_description' => trim((string)($source['seo_default_description'] ?? '')),
        'seo_title_separator' => trim((string)($source['seo_title_separator'] ?? '|')),
        'seo_indexing' => !empty($source['seo_indexing']) ? '1' : '0',
        'maintenance_mode' => !empty($source['maintenance_mode']) ? '1' : '0',
        'maintenance_message' => trim((string)($source['maintenance_message'] ?? '')),
        'session_timeout_minutes' => trim((string)($source['session_timeout_minutes'] ?? '30')),
        'remember_me_days' => trim((string)($source['remember_me_days'] ?? '30')),
    ];

    $errors = [];
    if (!Translator::supported($form['admin_language'])) {
        $errors['admin_language'] = t('settings.language_invalid');
    }
    $length = static fn(string $value): int => function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);

    if ($form['site_name'] === '') $errors['site_name'] = 'Sitenavn er påkrævet.';
    elseif ($length($form['site_name']) > 120) $errors['site_name'] = 'Sitenavn må højst være 120 tegn.';
    if ($length($form['site_tagline']) > 180) $errors['site_tagline'] = 'Tagline må højst være 180 tegn.';

    if (SiteUrl::normalizeOrigin($form['site_url']) === null) {
        $errors['site_url'] = 'Indtast en gyldig http- eller https-URL uden sti, brugernavn, query eller fragment.';
    } else {
        $form['site_url'] = SiteUrl::normalizeOrigin($form['site_url']);
    }


    if (!filter_var($form['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors['admin_email'] = 'Indtast en gyldig administrator-e-mail.';
    }
    if ($form['notification_email'] !== '' && !filter_var($form['notification_email'], FILTER_VALIDATE_EMAIL)) {
        $errors['notification_email'] = 'Indtast en gyldig standard notifikations-e-mail.';
    }

    if (!in_array($form['timezone'], DateTimeZone::listIdentifiers(), true)) {
        $errors['timezone'] = 'Indtast en gyldig IANA-tidszone, fx Europe/Copenhagen.';
    }
    if ($form['seo_default_description'] === '') {
        $errors['seo_default_description'] = 'Standard SEO-beskrivelse er påkrævet.';
    } elseif ($length($form['seo_default_description']) > 320) {
        $errors['seo_default_description'] = 'SEO-beskrivelsen må højst være 320 tegn.';
    }
    if ($form['seo_title_separator'] === '' || $length($form['seo_title_separator']) > 3) {
        $errors['seo_title_separator'] = 'Separatoren skal være mellem 1 og 3 tegn.';
    }
    if ($form['maintenance_mode'] === '1' && $form['maintenance_message'] === '') {
        $errors['maintenance_message'] = 'Skriv en besked, når vedligeholdelsestilstand er aktiv.';
    } elseif ($length($form['maintenance_message']) > 600) {
        $errors['maintenance_message'] = 'Vedligeholdelsesbeskeden må højst være 600 tegn.';
    }

    if (!preg_match('/^\d+$/', $form['session_timeout_minutes'])) {
        $errors['session_timeout_minutes'] = 'Session timeout skal være et helt antal minutter.';
        $sessionMinutes = 30;
    } else {
        $sessionMinutes = (int)$form['session_timeout_minutes'];
        if ($sessionMinutes < 5 || $sessionMinutes > 1440) {
            $errors['session_timeout_minutes'] = 'Session timeout skal være mellem 5 og 1440 minutter.';
        }
    }

    if (!preg_match('/^\d+$/', $form['remember_me_days'])) {
        $errors['remember_me_days'] = 'Husk mig skal være et helt antal dage.';
        $rememberDays = 30;
    } else {
        $rememberDays = (int)$form['remember_me_days'];
        if ($rememberDays < 1 || $rememberDays > 365) {
            $errors['remember_me_days'] = 'Husk mig skal være mellem 1 og 365 dage.';
        }
    }

    if ($form['notification_email'] === '') {
        $form['notification_email'] = $form['admin_email'];
    }

    return [
        'form' => $form,
        'errors' => $errors,
        'data' => [
            'admin_language' => $form['admin_language'],
            'site_name' => $form['site_name'],
            'site_tagline' => $form['site_tagline'],
            'site_url' => $form['site_url'],
            'admin_email' => $form['admin_email'],
            'notification_email' => $form['notification_email'],
            'timezone' => $form['timezone'],
            'seo_default_description' => $form['seo_default_description'],
            'seo_title_separator' => $form['seo_title_separator'],
            'seo_indexing' => $form['seo_indexing'],
            'maintenance_mode' => $form['maintenance_mode'],
            'maintenance_message' => $form['maintenance_message'],
            'session_timeout' => max(300, $sessionMinutes * 60),
            'remember_me_days' => max(1, $rememberDays),
        ],
    ];
}

/**
 * Redirect administration data for Værktøjer → Redirects.
 */
function admin_redirects_data(): array
{
    $redirects = new Redirects();

    $search = trim((string)($_GET['q'] ?? ''));
    $status = strtolower(trim((string)($_GET['status'] ?? '')));
    if (!in_array($status, ['', 'active', 'inactive', 'automatic'], true)) {
        $status = '';
    }

    // Keep the existing four redirect filters authoritative and scoped to the
    // same search. Automatic is a subset of All (and may be active/inactive).
    // Reuse the selected count for pagination instead of counting it twice.
    $counts = [
        'all' => $redirects->countFiltered($search, ''),
        'active' => $redirects->countFiltered($search, 'active'),
        'inactive' => $redirects->countFiltered($search, 'inactive'),
        'automatic' => $redirects->countFiltered($search, 'automatic'),
    ];
    $total = $counts[$status !== '' ? $status : 'all'];
    $pagination = admin_crud_pagination($total);

    return array_merge($pagination, [
        'items' => $redirects->getFiltered($search, $status, $pagination['perPage'], $pagination['offset']),
        'search' => $search,
        'status' => $status,
        'counts' => $counts,
        'total' => $total,
    ]);
}

function admin_redirects_url(array $changes = []): string
{
    $params = [
        'q' => trim((string)($_GET['q'] ?? '')),
        'status' => trim((string)($_GET['status'] ?? '')),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
    ];
    foreach ($changes as $key => $value) {
        if ($value === null) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    $params = array_filter(
        $params,
        static fn($value, $key): bool => !($value === '' || ($key === 'page' && (int)$value <= 1)),
        ARRAY_FILTER_USE_BOTH
    );
    $query = http_build_query($params);
    return '/admin/tools/redirects' . ($query !== '' ? '?' . $query : '');
}

function admin_redirect_type_label(int $type): string
{
    return $type === 302 ? '302 Midlertidig' : '301 Permanent';
}
