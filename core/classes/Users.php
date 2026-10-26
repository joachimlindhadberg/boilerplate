<?php
/**
 * User repository for the current Boilerplate users/roles schema.
 */
class Users
{
    protected Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    public static function validStatuses(): array
    {
        return ['active', 'inactive', 'banned'];
    }

    public function find(int $id): ?array
    {
        $rows = $this->db->fetchArray(
            "SELECT u.*, r.role_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = ?
             LIMIT 1",
            [$id]
        );

        return $rows[0] ?? null;
    }

    public function findByEmail(string $email): ?array
    {
        $rows = $this->db->fetchArray(
            "SELECT u.*, r.role_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.email = ?
             LIMIT 1",
            [strtolower(trim($email))]
        );

        return $rows[0] ?? null;
    }

    public function all(): array
    {
        return $this->db->fetchArray(
            "SELECT u.*, r.role_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             ORDER BY u.created_at DESC"
        );
    }

    public function roleOptions(): array
    {
        return $this->db->fetchArray(
            "SELECT id, role_name
             FROM roles
             ORDER BY FIELD(role_name, 'admin', 'editor', 'user'), id ASC"
        ) ?: [];
    }

    public function getStatusCounts(): array
    {
        $counts = ['all' => 0, 'active' => 0, 'inactive' => 0, 'banned' => 0];
        foreach ($this->db->fetchArray('SELECT status, COUNT(*) AS total FROM users GROUP BY status') as $row) {
            $status = (string)($row['status'] ?? '');
            $total = (int)($row['total'] ?? 0);
            if (array_key_exists($status, $counts)) {
                $counts[$status] = $total;
            }
            $counts['all'] += $total;
        }
        return $counts;
    }

    public function countFiltered(string $search = '', string $status = '', int $roleId = 0): int
    {
        [$where, $params] = $this->buildAdminFilters($search, $status, $roleId);
        $sql = 'SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id = u.role_id'
             . ($where !== '' ? ' WHERE ' . $where : '');
        return (int)$this->db->fetchValue($sql, $params);
    }

    public function getFiltered(string $search = '', string $status = '', int $roleId = 0, int $limit = 10, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        [$where, $params] = $this->buildAdminFilters($search, $status, $roleId);

        $sql = "SELECT u.*, r.role_name
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id"
             . ($where !== '' ? ' WHERE ' . $where : '')
             . " ORDER BY
                    FIELD(u.status, 'active', 'inactive', 'banned'),
                    FIELD(r.role_name, 'admin', 'editor', 'user'),
                    u.firstname ASC, u.lastname ASC, u.id ASC
                 LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        return $this->db->fetchArray($sql, $params) ?: [];
    }

    public function emailExists(string $email, int $excludeId = 0): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }

        if ($excludeId > 0) {
            return (int)$this->db->fetchValue(
                'SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?',
                [$email, $excludeId]
            ) > 0;
        }

        return (int)$this->db->fetchValue('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0;
    }

    public function countActiveAdminsExcluding(int $excludeId = 0): int
    {
        $sql = "SELECT COUNT(*)
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE r.role_name = 'admin' AND u.status = 'active'";
        $params = [];
        if ($excludeId > 0) {
            $sql .= ' AND u.id <> ?';
            $params[] = $excludeId;
        }
        return (int)$this->db->fetchValue($sql, $params);
    }

    public function create(array $data): int
    {
        $this->db->query(
            "INSERT INTO users
                (role_id, firstname, lastname, phone, email, password, weekly_hours, status, remember_token, must_change_password, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, NOW(), NOW())",
            [
                (int)$data['role_id'],
                trim((string)$data['firstname']),
                trim((string)$data['lastname']),
                ($data['phone'] ?? null) !== '' ? ($data['phone'] ?? null) : null,
                strtolower(trim((string)$data['email'])),
                password_hash((string)$data['password'], PASSWORD_DEFAULT),
                (float)($data['weekly_hours'] ?? 0),
                in_array((string)($data['status'] ?? ''), self::validStatuses(), true) ? (string)$data['status'] : 'active',
                !empty($data['must_change_password']) ? 1 : 0,
            ]
        );

        return $this->db->getInsertId();
    }

    public function update(int $id, array $data): void
    {
        $this->db->query(
            "UPDATE users SET
                role_id = ?, firstname = ?, lastname = ?, phone = ?, email = ?,
                weekly_hours = ?, status = ?, updated_at = NOW()
             WHERE id = ?",
            [
                (int)$data['role_id'],
                trim((string)$data['firstname']),
                trim((string)$data['lastname']),
                ($data['phone'] ?? null) !== '' ? ($data['phone'] ?? null) : null,
                strtolower(trim((string)$data['email'])),
                (float)($data['weekly_hours'] ?? 0),
                in_array((string)($data['status'] ?? ''), self::validStatuses(), true) ? (string)$data['status'] : 'active',
                $id,
            ]
        );
    }

    public function updateAdmin(int $id, array $data, ?string $newPassword = null, ?bool $forcePasswordChange = null): bool
    {
        $existing = $this->find($id);
        if (!$existing) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $this->update($id, $data);
            // Credential/role/status changes revoke existing logins atomically.
            $securityChange = $newPassword !== null && $newPassword !== ''
                || $forcePasswordChange !== null
                || (int)$existing['role_id'] !== (int)$data['role_id']
                || (string)$existing['status'] !== (string)$data['status'];

            if ($newPassword !== null && $newPassword !== '') {
                $this->db->query(
                    "UPDATE users
                     SET password = ?, remember_token = NULL, must_change_password = ?, updated_at = NOW()
                     WHERE id = ?",
                    [password_hash($newPassword, PASSWORD_DEFAULT), $forcePasswordChange ? 1 : 0, $id]
                );
            } elseif ($forcePasswordChange !== null) {
                $this->db->query(
                    "UPDATE users SET must_change_password = ?, remember_token = NULL, updated_at = NOW() WHERE id = ?",
                    [$forcePasswordChange ? 1 : 0, $id]
                );
            }

            if ($securityChange) {
                (new SessionManager($this->db))->revokeAll($id);
            }
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function updateProfile(int $id, array $data): void
    {
        $this->db->query(
            "UPDATE users SET firstname = ?, lastname = ?, phone = ?, email = ?, updated_at = NOW() WHERE id = ?",
            [
                trim((string)$data['firstname']),
                trim((string)$data['lastname']),
                ($data['phone'] ?? null) !== '' ? ($data['phone'] ?? null) : null,
                strtolower(trim((string)$data['email'])),
                $id,
            ]
        );
    }

    /** Rehash of the SAME password: deliberately preserves active sessions. */
    public function rehashPassword(int $id, string $password): void
    {
        $this->db->query(
            'UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $id]
        );
    }

    public function updatePassword(int $id, string $password): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->query(
                'UPDATE users SET password = ?, remember_token = NULL, updated_at = NOW() WHERE id = ?',
                [password_hash($password, PASSWORD_DEFAULT), $id]
            );
            (new SessionManager($this->db))->revokeAll($id);
            $this->db->commit();
        } catch (Throwable $e) { $this->db->rollback(); throw $e; }
    }

    public function changePasswordAndClearRequirement(int $id, string $password, ?int $preserveSession = null): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->query(
                'UPDATE users SET password = ?, must_change_password = 0, remember_token = NULL, updated_at = NOW() WHERE id = ?',
                [password_hash($password, PASSWORD_DEFAULT), $id]
            );
            (new SessionManager($this->db))->revokeAll($id, $preserveSession);
            $this->db->commit();
        } catch (Throwable $e) { $this->db->rollback(); throw $e; }
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, self::validStatuses(), true)) {
            throw new InvalidArgumentException('Invalid user status.');
        }
        $this->db->beginTransaction();
        try {
            $this->db->query(
                'UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?',
                [$status, $id]
            );
            if ($status !== 'active') (new SessionManager($this->db))->revokeAll($id);
            $this->db->commit();
        } catch (Throwable $e) { $this->db->rollback(); throw $e; }
    }

    public function clearRememberToken(int $id): void
    {
        $this->db->query("UPDATE users SET remember_token = NULL WHERE id = ?", [$id]);
    }

    public function setRememberToken(int $id, string $hash): void
    {
        $this->db->query("UPDATE users SET remember_token = ? WHERE id = ?", [$hash, $id]);
    }

    public function setAdminLanguage(int $id, ?string $locale): void
    {
        if ($locale !== null && !Translator::supported($locale)) {
            throw new InvalidArgumentException('Unsupported admin language.');
        }
        $this->db->query('UPDATE users SET admin_language = ? WHERE id = ?', [$locale, $id]);
    }

    public function updateLastLogin(int $id): void
    {
        $this->db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->query("DELETE FROM users WHERE id = ?", [$id]);
    }

    public function deleteAdmin(int $id, int $actorId): array
    {
        $user = $this->find($id);
        if (!$user) {
            return ['ok' => false, 'message' => 'Brugeren findes ikke længere.'];
        }
        if ($id === $actorId) {
            return ['ok' => false, 'message' => 'Du kan ikke slette din egen brugerkonto.'];
        }
        if ((string)($user['role_name'] ?? '') === 'admin' && (string)($user['status'] ?? '') === 'active' && $this->countActiveAdminsExcluding($id) < 1) {
            return ['ok' => false, 'message' => 'Den sidste aktive administrator kan ikke slettes.'];
        }

        $this->delete($id);
        return ['ok' => true, 'user' => $user];
    }

    private function buildAdminFilters(string $search, string $status, int $roleId): array
    {
        $parts = [];
        $params = [];

        $search = trim($search);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $parts[] = '(u.firstname LIKE ? OR u.lastname LIKE ? OR CONCAT(u.firstname, \' \', u.lastname) LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (in_array($status, self::validStatuses(), true)) {
            $parts[] = 'u.status = ?';
            $params[] = $status;
        }
        if ($roleId > 0) {
            $parts[] = 'u.role_id = ?';
            $params[] = $roleId;
        }

        return [implode(' AND ', $parts), $params];
    }
}
