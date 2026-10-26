<?php
/**
 * Server-side registry for independently revocable CMS logins.
 *
 * The random secrets live only in the PHP session / HttpOnly remember cookie;
 * the database stores SHA-256 of uniformly random 256-bit secrets.
 * Revoke by deleting the corresponding row. This class owns no HTTP output.
 */
final class SessionManager
{
    private Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? new Db();
    }

    public static function newSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    private static function digest(string $secret): string
    {
        return hash('sha256', $secret);
    }

    private static function validSecret(string $secret): bool
    {
        return preg_match('/^[a-f0-9]{64}$/D', $secret) === 1;
    }

    /** @return array{id:int,token:string} */
    public function create(int $userId): array
    {
        $secret = self::newSecret();
        $this->db->query(
            'INSERT INTO auth_sessions (user_id, session_hash, created_at, last_seen_at) VALUES (?, ?, NOW(), NOW())',
            [$userId, self::digest($secret)]
        );
        return ['id' => $this->db->getInsertId(), 'token' => $secret];
    }

    public function valid(int $id, int $userId, string $token): bool
    {
        if ($id < 1 || $userId < 1 || !self::validSecret($token)) return false;
        return (int)$this->db->fetchValue(
            'SELECT COUNT(*) FROM auth_sessions WHERE id = ? AND user_id = ? AND session_hash = ?',
            [$id, $userId, self::digest($token)]
        ) === 1;
    }

    /** Rotate the binding after a credential change or remember-cookie authentication. */
    public function rotate(int $id, int $userId, string $existingToken): ?string
    {
        if (!$this->valid($id, $userId, $existingToken)) return null;
        $new = self::newSecret();
        $this->db->query(
            'UPDATE auth_sessions SET session_hash = ?, last_seen_at = NOW() WHERE id = ? AND user_id = ? AND session_hash = ?',
            [self::digest($new), $id, $userId, self::digest($existingToken)]
        );
        return $this->valid($id, $userId, $new) ? $new : null;
    }

    /** Remembers only this device; does not invalidate other devices. */
    public function setRemember(int $id, int $userId, string $sessionToken, string $rememberToken, int $until): void
    {
        if (!$this->valid($id, $userId, $sessionToken) || !self::validSecret($rememberToken)) {
            throw new RuntimeException('Cannot remember an invalid session.');
        }
        $this->db->query(
            'UPDATE auth_sessions SET remember_hash = ?, remember_expires_at = FROM_UNIXTIME(?) WHERE id = ? AND user_id = ?',
            [self::digest($rememberToken), $until, $id, $userId]
        );
    }

    /** Returns the bound row ID or null. Expired/invalid tokens never authenticate. */
    public function redeemRemember(int $userId, string $rememberToken): ?int
    {
        if ($userId < 1 || !self::validSecret($rememberToken)) return null;
        $rows = $this->db->fetchArray(
            'SELECT id FROM auth_sessions WHERE user_id = ? AND remember_hash = ? AND remember_expires_at > NOW() LIMIT 1',
            [$userId, self::digest($rememberToken)]
        );
        return $rows ? (int)$rows[0]['id'] : null;
    }

    /** Atomically consumes the old remember token, replacing both secrets. */
    public function rotateRemember(int $id, int $userId, string $oldRemember, string $newSession, string $newRemember, int $until): bool
    {
        if ($id < 1 || $userId < 1 || !self::validSecret($oldRemember)
            || !self::validSecret($newSession) || !self::validSecret($newRemember)) return false;
        $this->db->query(
            'UPDATE auth_sessions SET session_hash = ?, remember_hash = ?, remember_expires_at = FROM_UNIXTIME(?), last_seen_at = NOW() WHERE id = ? AND user_id = ? AND remember_hash = ? AND remember_expires_at > NOW()',
            [self::digest($newSession), self::digest($newRemember), $until, $id, $userId, self::digest($oldRemember)]
        );
        // Distinguish an update won by this request from a concurrent redeem.
        return $this->valid($id, $userId, $newSession);
    }

    public function revoke(int $id, int $userId, string $sessionToken): void
    {
        if (!$this->valid($id, $userId, $sessionToken)) return;
        $this->db->query(
            'DELETE FROM auth_sessions WHERE id = ? AND user_id = ? AND session_hash = ?',
            [$id, $userId, self::digest($sessionToken)]
        );
    }

    /** Revoke the exact browser-held Remember Me credential, even without PHPSESSID.
     *  The caller proves possession of the random cookie secret. No other
     *  device's session is affected. An expired token can also be revoked.
     */
    public function revokeRemember(int $userId, string $rememberToken): void
    {
        if ($userId < 1 || !self::validSecret($rememberToken)) return;
        $this->db->query(
            'DELETE FROM auth_sessions WHERE user_id = ? AND remember_hash = ?',
            [$userId, self::digest($rememberToken)]
        );
    }

    /** Remove only long-abandoned rows which have no usable Remember Me token.
     *  Active PHP sessions keep last_seen_at fresh; unexpired remembered
     *  devices are retained regardless of their last visit. The 48-hour grace
     *  threshold exceeds the CMS session timeout by at least one day and
     *  is never shorter than 48 hours. Run on successful authentication only.
     */
    public function cleanupAbandoned(int $sessionTimeoutSeconds = 1800): void
    {
        $idleSeconds = max(48 * 3600, max(300, $sessionTimeoutSeconds) + 86400);
        $this->db->query(
            'DELETE FROM auth_sessions WHERE last_seen_at < DATE_SUB(NOW(), INTERVAL ? SECOND) '
            . 'AND (remember_hash IS NULL OR remember_expires_at IS NULL OR remember_expires_at <= NOW())',
            [$idleSeconds]
        );
    }

    /** Used after sensitive account changes; can retain the current authenticated login. */
    public function revokeAll(int $userId, ?int $exceptId = null): void
    {
        if ($exceptId !== null && $exceptId > 0) {
            $this->db->query('DELETE FROM auth_sessions WHERE user_id = ? AND id <> ?', [$userId, $exceptId]);
            $this->db->query('UPDATE auth_sessions SET remember_hash = NULL, remember_expires_at = NULL WHERE user_id = ? AND id = ?', [$userId, $exceptId]);
        } else {
            $this->db->query('DELETE FROM auth_sessions WHERE user_id = ?', [$userId]);
        }
    }

    /** Optional future API for an authenticated Active Sessions UI. */
    public function listForUser(int $userId): array
    {
        return $this->db->fetchArray(
            'SELECT id, created_at, last_seen_at, remember_expires_at FROM auth_sessions WHERE user_id = ? ORDER BY last_seen_at DESC',
            [$userId]
        );
    }

    public function touch(int $id, int $userId): void
    {
        $this->db->query('UPDATE auth_sessions SET last_seen_at = NOW() WHERE id = ? AND user_id = ?', [$id, $userId]);
    }
}
