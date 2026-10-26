<?php

/**
 * Authentication service for the Boilerplate CMS.
 */
class Auth
{
    protected Db $db;
    protected Users $users;
    protected Settings $settings;
    protected SessionManager $sessions;

    public function __construct()
    {
        $this->db = new Db();
        $this->users = new Users();
        $this->settings = new Settings();
        $this->sessions = new SessionManager($this->db);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->rememberLogin();
        $this->checkTimeout();
        $this->validateSessionUser();
    }

    public function login(string $email, string $password, bool $remember = false): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || $password === '') {
            return false;
        }

        $user = $this->users->findByEmail($email);
        if (!$user || ($user['status'] ?? '') !== 'active') {
            return false;
        }

        if (!password_verify($password, (string)$user['password'])) {
            return false;
        }

        if (!self::roleCan((string)($user['role_name'] ?? ''), 'admin.access')) {
            return false;
        }

        // Password rehashing is NOT a password change and must not revoke other logins.
        if (password_needs_rehash((string)$user['password'], PASSWORD_DEFAULT)) {
            $this->users->rehashPassword((int)$user['id'], $password);
        }

        // Replacing an existing login on this browser must not orphan its
        // database row. A valid remembered cookie is also revoked when its
        // PHP session has been removed (e.g. manually clearing PHPSESSID).
        // Only credentials actually held by this browser are affected.
        $this->revokeBrowserBindings();
        $this->discardLocalBinding();
        session_regenerate_id(true);
        $binding = $this->sessions->create((int)$user['id']);
        $this->storeBinding($binding);
        $this->writeSession($user);
        $this->users->updateLastLogin((int)$user['id']);

        if ($remember) {
            $this->createRememberCookie((int)$user['id']);
        } else {
            $this->clearRememberCookie();
        }

        $this->cleanupAbandonedSessions();
        return true;
    }

    public function check(): bool
    {
        return isset($_SESSION['auth']['id']) && (int)$_SESSION['auth']['id'] > 0;
    }

    public function id(): ?int
    {
        return $this->check() ? (int)$_SESSION['auth']['id'] : null;
    }

    public function user(): ?array
    {
        return $this->check() ? (array)$_SESSION['auth'] : null;
    }

    public function role(): ?string
    {
        return $this->check() ? (string)($_SESSION['auth']['role'] ?? '') : null;
    }

    /**
     * Central CMS permission matrix. Keep role behaviour in one place so
     * navigation, route guards and future modules cannot drift apart.
     */
    public static function roleCan(string $role, string $capability): bool
    {
        $role = strtolower(trim($role));
        $capability = strtolower(trim($capability));

        if ($role === '' || $capability === '') {
            return false;
        }

        return CapabilityRegistry::allows($role, $capability);
    }

    public function can(string $capability): bool
    {
        return $this->check() && self::roleCan((string)$this->role(), $capability);
    }

    public function canAccessAdmin(): bool
    {
        return $this->can('admin.access');
    }

    public function isAdmin(): bool
    {
        return $this->can('users.manage') && $this->can('settings.manage');
    }

    public function mustChangePassword(): bool
    {
        return $this->check() && !empty($_SESSION['auth']['must_change_password']);
    }

    public function changePassword(string $password): bool
    {
        if (!$this->check() || strlen($password) < 12) {
            return false;
        }

        $id = $this->id();
        if ($id === null) {
            return false;
        }

        $binding = $this->binding();
        if ($binding === null || !$this->sessions->valid($binding['id'], $id, $binding['token'])) return false;
        // Atomic password update + revocation of every *other* device.
        $this->users->changePasswordAndClearRequirement($id, $password, $binding['id']);
        session_regenerate_id(true);
        $newToken = $this->sessions->rotate($binding['id'], $id, $binding['token']);
        if ($newToken === null) {
            $this->discardLocalBinding();
            $this->logout();
            return false;
        }
        $this->storeBinding(['id' => $binding['id'], 'token' => $newToken]);
        $this->clearRememberCookie();
        $this->refresh();
        return true;
    }

    public function refresh(): void
    {
        if (!$this->check()) {
            return;
        }

        $user = $this->users->find((int)$this->id());
        if (!$user || ($user['status'] ?? '') !== 'active') {
            $this->logout();
            return;
        }

        $this->writeSession($user, false);
    }

    public function logout(): void
    {
        // Revoke BOTH available credentials for this browser. In particular,
        // a Remember Me token must not survive logout when PHPSESSID has been
        // lost. Never revoke other devices without possessing their secrets.
        try {
            $this->revokeBrowserBindings();
        } catch (Throwable $e) {
            error_log('Unable to revoke login on logout: ' . $e->getMessage());
        }

        $this->clearRememberCookie();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'] ?: '',
                'secure' => (bool)$params['secure'],
                'httponly' => (bool)$params['httponly'],
                'samesite' => $params['samesite'] ?: 'Lax',
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    protected function writeSession(array $user, bool $resetLastSeen = true): void
    {
        $_SESSION['auth'] = [
            'id' => (int)$user['id'],
            'role_id' => (int)$user['role_id'],
            'role' => (string)($user['role_name'] ?? ''),
            'firstname' => (string)$user['firstname'],
            'lastname' => (string)$user['lastname'],
            'email' => (string)$user['email'],
            'admin_language' => isset($user['admin_language']) && Translator::supported((string)$user['admin_language'])
                ? (string)$user['admin_language'] : null,
            'must_change_password' => !empty($user['must_change_password']),
            // A new random marker belongs to each successful login (password or remember-cookie).
            // Session refreshes must preserve it; displaying Dashboard cannot create a login event.
            'welcome_login_marker' => $resetLastSeen
                ? bin2hex(random_bytes(16))
                : (string)($_SESSION['auth']['welcome_login_marker'] ?? ''),
            'last_seen' => $resetLastSeen ? time() : (int)($_SESSION['auth']['last_seen'] ?? time()),
        ];
        Translator::setLocale($_SESSION['auth']['admin_language']);
    }

    protected function validateSessionUser(): void
    {
        if (!$this->check()) {
            return;
        }

        $user = $this->users->find((int)$this->id());
        if (!$user || ($user['status'] ?? '') !== 'active' || !self::roleCan((string)($user['role_name'] ?? ''), 'admin.access')) {
            $this->logout();
            return;
        }

        $binding = $this->binding();
        if ($binding === null || !$this->sessions->valid($binding['id'], (int)$user['id'], $binding['token'])) {
            // Missing, invalid or administratively revoked token: fail closed.
            $this->logout();
            return;
        }
        // Reporting activity at most once per five minutes avoids a DB write
        // for every authenticated request while keeping device management useful.
        if (time() - (int)($_SESSION['auth_registry_touch'] ?? 0) >= 300) {
            $this->sessions->touch($binding['id'], (int)$user['id']);
            $_SESSION['auth_registry_touch'] = time();
        }
        $this->writeSession($user, false);
    }

    /** @return array{id:int,token:string}|null */
    protected function binding(): ?array
    {
        $value = $_SESSION['auth_binding'] ?? null;
        if (!is_array($value) || !isset($value['id'], $value['token'])
            || !is_int($value['id']) || $value['id'] < 1
            || !is_string($value['token']) || !preg_match('/^[a-f0-9]{64}$/D', $value['token'])) return null;
        return ['id' => $value['id'], 'token' => $value['token']];
    }

    protected function storeBinding(array $binding): void
    {
        $_SESSION['auth_binding'] = $binding;
    }

    protected function discardLocalBinding(): void
    {
        unset($_SESSION['auth'], $_SESSION['auth_binding'], $_SESSION['auth_registry_touch']);
    }

    /** Revoke only sessions for which this browser holds a valid secret. */
    protected function revokeBrowserBindings(): void
    {
        $id = $this->id();
        $binding = $this->binding();
        if ($id !== null && $binding !== null) {
            $this->sessions->revoke($binding['id'], $id, $binding['token']);
        }

        $cookie = (string)($_COOKIE[$this->rememberCookieName()] ?? '');
        $parts = explode('|', $cookie, 2);
        if (count($parts) === 2 && ctype_digit($parts[0])) {
            // The SQL matches a SHA-256 digest of the unguessable cookie token;
            // a forged user ID or malformed token cannot revoke a session.
            $this->sessions->revokeRemember((int)$parts[0], $parts[1]);
        }
    }

    protected function cleanupAbandonedSessions(): void
    {
        try {
            $this->sessions->cleanupAbandoned((int)$this->settings->get('session_timeout', 1800));
        } catch (Throwable $e) {
            // Cleanup is housekeeping, never a dependency for valid login.
            error_log('Unable to clean abandoned sessions: ' . $e->getMessage());
        }
    }

    protected function createRememberCookie(int $userId): void
    {
        $token = SessionManager::newSecret();
        $days = max(1, (int)$this->settings->get('remember_me_days', 30));
        $expire = time() + ($days * 86400);
        $binding = $this->binding();
        if ($binding === null) throw new RuntimeException('Cannot issue remember cookie without a login session.');
        $this->sessions->setRemember($binding['id'], $userId, $binding['token'], $token, $expire);

        setcookie($this->rememberCookieName(), $userId . '|' . $token, [
            'expires' => $expire,
            'path' => '/',
            'secure' => $this->secureCookie(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    protected function rememberLogin(): void
    {
        if ($this->check()) {
            return;
        }

        $cookie = (string)($_COOKIE[$this->rememberCookieName()] ?? '');
        if ($cookie === '') {
            return;
        }

        $parts = explode('|', $cookie, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0]) || $parts[1] === '') {
            $this->clearRememberCookie();
            return;
        }

        $user = $this->users->find((int)$parts[0]);
        if (!$user || ($user['status'] ?? '') !== 'active' || !self::roleCan((string)($user['role_name'] ?? ''), 'admin.access')) {
            $this->clearRememberCookie();
            return;
        }

        $userId = (int)$user['id'];
        $recordId = $this->sessions->redeemRemember($userId, $parts[1]);
        if ($recordId === null) {
            $this->clearRememberCookie();
            return;
        }

        $newSession = SessionManager::newSecret();
        $newRemember = SessionManager::newSecret();
        $days = max(1, (int)$this->settings->get('remember_me_days', 30));
        $expire = time() + ($days * 86400);
        if (!$this->sessions->rotateRemember($recordId, $userId, $parts[1], $newSession, $newRemember, $expire)) {
            $this->clearRememberCookie();
            return;
        }
        session_regenerate_id(true);
        $this->storeBinding(['id' => $recordId, 'token' => $newSession]);
        $this->writeSession($user);
        $this->users->updateLastLogin($userId);
        setcookie($this->rememberCookieName(), $userId . '|' . $newRemember, [
            'expires' => $expire, 'path' => '/', 'secure' => $this->secureCookie(),
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        $this->cleanupAbandonedSessions();
    }

    protected function checkTimeout(): void
    {
        if (!$this->check() || !isset($_SESSION['auth']['last_seen'])) {
            return;
        }

        $timeout = max(300, (int)$this->settings->get('session_timeout', 1800));
        if ((time() - (int)$_SESSION['auth']['last_seen']) > $timeout) {
            $this->logout();
            return;
        }

        $_SESSION['auth']['last_seen'] = time();
    }

    protected function rememberCookieName(): string
    {
        $name = trim((string)$this->settings->get('remember_cookie_name', 'boilerplate_remember'));
        return $name !== '' ? $name : 'boilerplate_remember';
    }

    protected function clearRememberCookie(): void
    {
        setcookie($this->rememberCookieName(), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $this->secureCookie(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[$this->rememberCookieName()]);
    }

    protected function secureCookie(): bool
    {
        // Match the session-cookie policy: Secure must describe the request
        // currently being served. In development APP_URL may already point at
        // the future HTTPS production origin while the local site still uses
        // HTTP; marking the remember cookie Secure there would make browsers
        // silently discard it.
        return AppConfig::requestIsHttps();
    }
}
