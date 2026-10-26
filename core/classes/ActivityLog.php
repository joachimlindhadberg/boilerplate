<?php
/**
 * CMS activity feed. Store action identifiers and safe object labels only.
 * Never store passwords, form answers, tokens, customer data or request bodies.
 * Audit events are best-effort and MUST NOT turn a successful save into an error.
 */
final class ActivityLog
{
    public function __construct(private ?Db $db = null)
    {
        $this->db ??= new Db();
    }

    public function record(string $action, string $subjectType, ?int $subjectId, string $label, ?int $actorId): bool
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $action)
            || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $subjectType)) {
            throw new InvalidArgumentException('Invalid activity identifier.');
        }
        $actorType = $actorId !== null && $actorId > 0 ? 'user' : 'system';
        $actorName = 'System';
        if ($actorType === 'user') {
            $rows = $this->db->fetchArray('SELECT firstname, lastname FROM users WHERE id = ? LIMIT 1', [$actorId]);
            $actorName = trim((string)($rows[0]['firstname'] ?? '') . ' ' . (string)($rows[0]['lastname'] ?? ''));
            if ($actorName === '') $actorName = 'User #' . $actorId;
        }
        $label = trim($label);
        $label = function_exists('mb_substr') ? mb_substr($label, 0, 255) : substr($label, 0, 255);
        $this->db->query(
            'INSERT INTO activity_log (actor_id, actor_name, actor_type, action, subject_type, subject_id, subject_label, created_at_utc) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$actorType === 'user' ? $actorId : null, $actorName, $actorType, $action, $subjectType, $subjectId > 0 ? $subjectId : null, $label]
        );
        return true;
    }

    /** All filters are parameter-bound; the event is never silently mutated here. */
    private function conditions(string $search, string $type, string $action): array
    {
        $clauses = []; $params = [];
        if ($search !== '') {
            $clauses[] = '(subject_label LIKE ? OR actor_name LIKE ?)';
            $query = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $params[] = $query; $params[] = $query;
        }
        if ($type !== '') { $clauses[] = 'subject_type = ?'; $params[] = $type; }
        if ($action !== '') { $clauses[] = 'action = ?'; $params[] = $action; }
        return [$clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    public function count(string $search = '', string $type = '', string $action = ''): int
    {
        [$where, $params] = $this->conditions($search, $type, $action);
        return (int)$this->db->fetchValue('SELECT COUNT(*) FROM activity_log' . $where, $params);
    }

    public function latest(int $limit = 10, int $offset = 0, string $search = '', string $type = '', string $action = ''): array
    {
        $limit = max(1, min(100, $limit)); $offset = max(0, $offset);
        [$where, $params] = $this->conditions($search, $type, $action);
        return $this->db->fetchArray('SELECT id, actor_id, actor_name, actor_type, action, subject_type, subject_id, '
            . 'subject_label, created_at_utc FROM activity_log' . $where
            . ' ORDER BY created_at_utc DESC, id DESC LIMIT ? OFFSET ?', [...$params, $limit, $offset]);
    }

    public function types(): array
    {
        return array_column($this->db->fetchArray('SELECT DISTINCT subject_type FROM activity_log ORDER BY subject_type'), 'subject_type');
    }

    public function actions(): array
    {
        return array_column($this->db->fetchArray('SELECT DISTINCT action FROM activity_log ORDER BY action'), 'action');
    }

    public static function localDate(string $utc): string
    {
        if ($utc === '') return '—';
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('d.m.Y H:i');
    }
}
