<?php

declare(strict_types=1);

/**
 * Authenticated frontend shortcuts. Core owns permissions and markup;
 * the active frontend/theme owns every visual rule.
 */
final class FrontendAdminBar
{
    private static ?Auth $auth = null;

    /**
     * Only HTML page requests pass through this gate. Unauthenticated visitors
     * without login cookies do not start PHP sessions or query the Users model.
     */
    public static function prepareRequest(Settings $settings): void
    {
        // A shared cache must never reuse an anonymous page for a cookie-bearing
        // user, nor publish a personalized toolbar. Proxy caches must honor
        // Vary and bypass caching for authenticated traffic.
        header('Vary: Cookie', false);

        $rememberName = trim((string)$settings->get('remember_cookie_name', 'boilerplate_remember'));
        if ($rememberName === '') {
            $rememberName = 'boilerplate_remember';
        }
        $sessionName = session_name();
        $hasSessionCookie = $sessionName !== '' && !empty($_COOKIE[$sessionName]);
        $hasRememberCookie = !empty($_COOKIE[$rememberName]);
        $sessionActive = session_status() === PHP_SESSION_ACTIVE;
        $sessionHasAuth = $sessionActive && !empty($_SESSION['auth']['id']);

        // Forms currently starts a session even for an anonymous visitor. An
        // active *empty* session must not trigger another authentication lookup.
        // When a session cookie exists but a module has not opened it, Auth
        // needs to read it first to determine whether the user is logged in.
        $requiresAuth = $sessionHasAuth || $hasRememberCookie || ($hasSessionCookie && !$sessionActive);
        if ($sessionActive || $hasSessionCookie || $hasRememberCookie) {
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
        if (!$requiresAuth) {
            return;
        }

        // Re-validate existing session identity/role/timeout via the exact same
        // Auth service used by /admin. Remember-me is likewise validated here.
        self::$auth = new Auth();
    }

    /** Render only after the current public page has been resolved. */
    public static function render(array $page): void
    {
        $auth = self::$auth;
        if (!$auth instanceof Auth || !$auth->check() || !$auth->canAccessAdmin() || $auth->mustChangePassword()) {
            return;
        }
        $user = $auth->user() ?? [];
        echo self::markup(
            $user,
            $auth->can('content.manage'),
            $page,
            self::adminCsrfToken()
        );
    }

    /**
     * The role/permission boolean is obtained from Auth, never public input.
     * Public pages do not disclose edit shortcuts for module routes or 404s.
     */
    public static function markup(array $user, bool $canManageContent, array $page, string $csrfToken): string
    {
        $pageId = !isset($page['_module_public'])
            && in_array((string)($page['page_status'] ?? ''), ['published', 'protected'], true)
            ? (int)($page['id'] ?? 0)
            : 0;
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $label = static fn(string $key): string => $escape(Translator::get($key));
        $icon = static fn(string $name): string => AdminIcons::render($name, 'frontend-admin-bar__icon');
        $name = trim((string)($user['firstname'] ?? '') . ' ' . (string)($user['lastname'] ?? ''));

        $html = '<aside class="frontend-admin-bar" aria-label="' . $label('frontend_bar.navigation') . '">'
            . '<div class="frontend-admin-bar__inner">'
            . '<a class="frontend-admin-bar__brand" href="/admin" aria-label="Boilerplate CMS">'
            . $icon('cube') . '<span>CMS</span></a>';

        // CMS is the single Dashboard link. The shortcuts navigation is only
        // present when the role has actual page-management actions.
        if ($canManageContent) {
            $html .= '<nav class="frontend-admin-bar__nav" aria-label="' . $label('frontend_bar.shortcuts') . '">';
            if ($pageId > 0) {
                $html .= self::shortcut('edit', '/admin/sider?mode=edit&id=' . $pageId, 'Rediger side');
            }
            $html .= self::shortcut('plus', '/admin/sider?mode=new', 'Ny side') . '</nav>';
        }

        $html .= '<details class="frontend-admin-bar__account">'
            . '<summary>' . $icon('user') . '<span class="frontend-admin-bar__label">'
            . $label('frontend_bar.account') . '</span>' . $icon('chevron-down') . '</summary>'
            . '<div class="frontend-admin-bar__menu">';
        if ($name !== '') {
            $html .= '<span class="frontend-admin-bar__username">' . $escape($name) . '</span>';
        }
        $html .= '<form method="post" action="/admin/index.php?mode=logout">'
            . '<input type="hidden" name="csrf_token" value="' . $escape($csrfToken) . '">'
            . '<button type="submit">' . $label('Log ud') . '</button>'
            . '</form></div></details></div></aside>';

        return $html;
    }

    private static function shortcut(string $iconName, string $href, string $textKey): string
    {
        $label = htmlspecialchars(Translator::get($textKey), ENT_QUOTES, 'UTF-8');
        return '<a class="frontend-admin-bar__link" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8')
            . '" aria-label="' . $label . '">' . AdminIcons::render($iconName, 'frontend-admin-bar__icon')
            . '<span class="frontend-admin-bar__label">' . $label . '</span></a>';
    }

    /** Shares the existing /admin POST + CSRF logout contract without loading admin templates. */
    private static function adminCsrfToken(): string
    {
        if (empty($_SESSION['admin_csrf']) || !is_string($_SESSION['admin_csrf'])) {
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['admin_csrf'];
    }
}
