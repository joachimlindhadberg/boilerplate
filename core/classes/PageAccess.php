<?php

declare(strict_types=1);

/**
 * Server-side Pages draft preview and password-gated Protected pages.
 *
 * The page HTML is guarded before the theme renders anything. This does NOT
 * secure independently reachable public /uploads URLs; see release README.
 * It never grants access to /admin and never authorizes mutations to Pages.
 */
final class PageAccess
{
    private const GRANTS_KEY = 'boilerplate_page_access_grants_v1';
    private const CSRF_KEY = 'boilerplate_page_access_csrf_v1';
    private const GRANT_TTL = 43200; // 12 hours; also invalidated by password change.
    private static ?bool $editorAuthenticated = null;

    public static function validNewPassword(string $password): bool
    {
        return strlen($password) >= 12 && strlen($password) <= 128
            && !preg_match('/[\x00-\x1f\x7f]/', $password);
    }

    /** Only authorized CMS users can preview Draft or bypass a page password. */
    public static function editorAuthenticated(): bool
    {
        if (self::$editorAuthenticated !== null) return self::$editorAuthenticated;
        $auth = new Auth(); // validates the CMS session/remember token, not just a session flag.
        return self::$editorAuthenticated = $auth->check()
            && $auth->can('content.manage') && !$auth->mustChangePassword();
    }

    /** Returns null for CMS-only Protected pages (legacy behaviour). */
    public static function passwordHash(int $pageId, ?Db $db = null): ?string
    {
        if ($pageId < 1) return null;
        $db ??= new Db();
        $value = $db->fetchValue('SELECT password_hash FROM page_access WHERE page_id = ? LIMIT 1', [$pageId]);
        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function hasPassword(int $pageId, ?Db $db = null): bool
    {
        return self::passwordHash($pageId, $db) !== null;
    }

    /** Must be called within the existing Pages save transaction. */
    public static function save(int $pageId, string $status, string $newPassword, bool $clear, ?Db $db = null): void
    {
        if ($pageId < 1) throw new InvalidArgumentException('Invalid page ID.');
        $db ??= new Db();
        if ($status !== 'protected' || $clear) {
            $db->query('DELETE FROM page_access WHERE page_id = ?', [$pageId]);
            return;
        }
        if ($newPassword === '') return; // Keep an existing password on ordinary edits.
        if (!self::validNewPassword($newPassword)) throw new InvalidArgumentException('Invalid page password.');
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') throw new RuntimeException('Password hashing unavailable.');
        $db->query('INSERT INTO page_access (page_id, password_hash, updated_at) VALUES (?, ?, UTC_TIMESTAMP()) '
            . 'ON DUPLICATE KEY UPDATE password_hash = ?, updated_at = UTC_TIMESTAMP()',
            [$pageId, $hash, $hash]);
    }

    /** Avoid a theme, SEO, module-action or metadata leak before authorization. */
    public static function guard(Pages $pages): bool
    {
        $path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        if ($path === '/robots.txt' || $path === '/sitemap.xml' || str_starts_with($path, '/admin/')) return false;
        $page = $pages->findByPublicPath($path);
        if (!is_array($page)) return false;
        $status = (string)($page['page_status'] ?? '');
        if (!in_array($status, ['draft', 'protected'], true)) return false;
        self::privateHeaders();
        if (self::canDisplay($page)) return false;
        if ($status === 'draft') {
            // Do not allow module POST actions to run before the 404 renderer.
            if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
                http_response_code(404);
                return true;
            }
            return false; // public renderer supplies an ordinary 404 for GET.
        }

        $hash = self::passwordHash((int)($page['id'] ?? 0));
        if ($hash === null) {
            // Existing Protected pages without a page password keep CMS-only access.
            header('Location: /admin', true, 302);
            return true;
        }
        self::passwordGate($page, $hash, $pages->buildPublicUrl($page));
        return true;
    }

    /** Second fail-closed check at the actual rendering boundary. */
    public static function canDisplay(array $page): bool
    {
        $status = (string)($page['page_status'] ?? '');
        if ($status === 'published') return true;
        if ($status === 'draft') return self::editorAuthenticated();
        if ($status !== 'protected') return false;
        if (self::editorAuthenticated()) return true;
        $id = (int)($page['id'] ?? 0);
        $hash = self::passwordHash($id);
        if ($hash === null || session_status() !== PHP_SESSION_ACTIVE) return false;
        $entry = $_SESSION[self::GRANTS_KEY][(string)$id] ?? null;
        return is_array($entry)
            && is_string($entry['fingerprint'] ?? null)
            && hash_equals(hash('sha256', $hash), $entry['fingerprint'])
            && is_int($entry['expires'] ?? null) && $entry['expires'] >= time();
    }

    private static function privateHeaders(): void
    {
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true);
        header('Pragma: no-cache', true);
        header('Vary: Cookie', false);
        header('Referrer-Policy: no-referrer', true);
    }

    private static function passwordGate(array $page, string $hash, string $path): void
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $error = false;
        $blocked = false;
        if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
            header('Allow: GET, HEAD, POST', true, 405);
            exit;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (!isset($_SESSION[self::CSRF_KEY]) || !is_string($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }
        if ($method === 'POST') {
            $token = $_POST['_page_access_csrf'] ?? null;
            $value = $_POST['_page_access_password'] ?? null;
            if (!is_string($token) || !hash_equals($_SESSION[self::CSRF_KEY], $token)
                || !is_string($value) || strlen($value) > 128) {
                http_response_code(400);
                $error = true;
            } else {
                // Separate page/password versions to avoid carrying a lockout
                // across password rotation. Count *failed* passwords only.
                $scope = 'page_password:' . (int)$page['id'] . ':' . hash('sha256', $hash);
                $identifier = RateLimiter::clientIp();
                $limiter = new RateLimiter();
                $limit = $limiter->status($scope, $identifier, 5);
                if (!$limit['allowed']) {
                    $blocked = true;
                    header('Retry-After: ' . max(1, (int)$limit['retry_after']));
                } elseif (password_verify($value, $hash)) {
                    $limiter->clear($scope, $identifier);
                    session_regenerate_id(true);
                    $_SESSION[self::GRANTS_KEY][(string)$page['id']] = [
                        'fingerprint' => hash('sha256', $hash),
                        'expires' => time() + self::GRANT_TTL,
                    ];
                    $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
                    header('Location: ' . $path, true, 303);
                    exit;
                } else {
                    $failure = $limiter->hit($scope, $identifier, 5, 900);
                    if (!$failure['allowed']) {
                        $blocked = true;
                        header('Retry-After: ' . max(1, (int)$failure['retry_after']));
                    } else {
                        $error = true;
                    }
                }
            }
        }
        if ($blocked) http_response_code(429);
        else http_response_code($error ? 403 : 401);
        self::renderGate($path, $error, $blocked);
    }

    private static function renderGate(string $path, bool $error, bool $blocked): void
    {
        // Core supplies semantic, safely escaped markup. The frontend theme
        // alone owns presentation in components/_components.page-access.scss.
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $tr = static fn(string $key): string => $e(Translator::get($key));
        $csrf = $e((string)($_SESSION[self::CSRF_KEY] ?? ''));
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="' . $e(Translator::locale()) . '"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<meta name="robots" content="noindex,nofollow,noarchive">'
           . '<title>' . $tr('pages.access.gate_title') . ' · Boilerplate</title>'
           . '<link rel="stylesheet" href="' . $e(asset_url('/assets/css/style.css')) . '">'
           . '</head><body class="page-access-page">'
           . '<main class="page-access" id="main-content">'
           . '<section class="page-access__panel" aria-labelledby="page-access-title">'
           . '<header class="page-access__header">'
           . '<h1 class="page-access__title" id="page-access-title">' . $tr('pages.access.gate_title') . '</h1>'
           . '<p class="page-access__description">' . $tr('pages.access.gate_description') . '</p>'
           . '</header>';
        if ($blocked) {
            echo '<p class="page-access__notice page-access__notice--error" id="page-access-notice" role="alert">'
                . $tr('pages.access.gate_blocked') . '</p>';
        } elseif ($error) {
            echo '<p class="page-access__notice page-access__notice--error" id="page-access-notice" role="alert">'
                . $tr('pages.access.gate_error') . '</p>';
        }
        echo '<form class="page-access__form" method="post" action="' . $e($path) . '">'
           . '<input type="hidden" name="_page_access_csrf" value="' . $csrf . '">'
           . '<div class="page-access__field">'
           . '<label for="page-access-password">' . $tr('pages.access.gate_password') . '</label>'
           . '<input id="page-access-password" type="password" name="_page_access_password"'
           . ' required maxlength="128" autocomplete="off"'
           . ($error ? ' aria-invalid="true" aria-describedby="page-access-notice"' : '')
           . ($blocked ? ' aria-describedby="page-access-notice" disabled' : '')
           . '>'
           . '</div>'
           . '<button class="button page-access__submit" type="submit"'
           . ($blocked ? ' disabled' : '')
           . '>' . $tr('pages.access.gate_button') . '</button>'
           . '</form></section></main></body></html>';
    }
}
