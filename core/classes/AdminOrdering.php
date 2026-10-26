<?php
/**
 * Shared zero-based ordering helper for CMS CRUD entities.
 *
 * Invariants:
 * - positions are contiguous: 0..N-1
 * - moving one record shifts the records in between
 * - scoped entities (pages/page blocks) are ordered inside their scope
 * - callers wrap multi-step writes in a DB transaction
 */
class AdminOrdering
{
    protected Db $db;

    private const DEFINITIONS = [
        'pages' => ['table' => 'pages', 'order' => 'menu_order', 'scope' => 'parent_id'],
        'page_blocks' => ['table' => 'page_blocks', 'order' => 'sort_order', 'scope' => 'page_id'],
    ];
    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    /** Prepare a contiguous slot for a new record and return the clamped position. */
    public function prepareInsert(string $entity, ?int $target, mixed $scope = null): int
    {
        $def = $this->definition($entity);
        $this->normalize($entity, $scope);
        $count = $this->count($def, $scope);
        $target = $target === null ? $count : max(0, min($target, $count));

        [$where, $params] = $this->scopeWhere($def, $scope);
        $sql = 'UPDATE ' . $def['table'] . ' SET ' . $def['order'] . ' = ' . $def['order'] . ' + 1'
             . ' WHERE ' . ($where !== '' ? $where . ' AND ' : '') . $def['order'] . ' >= ?';
        $params[] = $target;
        $this->db->query($sql, $params);
        return $target;
    }

    /**
     * Move an existing record. For scoped entities, $newScope may move the record
     * to another scope while preserving contiguous order in both scopes.
     */
    public function move(string $entity, int $id, ?int $target, mixed $newScope = null): int
    {
        $def = $this->definition($entity);
        if ($id < 1) {
            throw new InvalidArgumentException('Invalid ordering id.');
        }

        $columns = $def['order'] . ($def['scope'] ? ', ' . $def['scope'] : '');
        $rows = $this->db->fetchArray('SELECT ' . $columns . ' FROM ' . $def['table'] . ' WHERE id = ? LIMIT 1 FOR UPDATE', [$id]);
        if (!$rows) {
            throw new RuntimeException('Record not found while ordering.');
        }

        $oldScope = $def['scope'] ? $this->normaliseScopeValue($rows[0][$def['scope']] ?? null) : null;
        if (!$def['scope']) {
            $newScope = null;
        } else {
            $newScope = $this->normaliseScopeValue($newScope);
        }

        if ($def['scope'] && !$this->sameScope($oldScope, $newScope)) {
            $this->normalize($entity, $oldScope);
            $this->normalize($entity, $newScope);

            $current = (int)$this->db->fetchValue('SELECT ' . $def['order'] . ' FROM ' . $def['table'] . ' WHERE id = ?', [$id]);
            [$oldWhere, $oldParams] = $this->scopeWhere($def, $oldScope);
            $sql = 'UPDATE ' . $def['table'] . ' SET ' . $def['order'] . ' = ' . $def['order'] . ' - 1'
                 . ' WHERE ' . ($oldWhere !== '' ? $oldWhere . ' AND ' : '') . $def['order'] . ' > ?';
            $oldParams[] = $current;
            $this->db->query($sql, $oldParams);

            $newCount = $this->count($def, $newScope);
            $target = $target === null ? $newCount : max(0, min($target, $newCount));
            [$newWhere, $newParams] = $this->scopeWhere($def, $newScope);
            $sql = 'UPDATE ' . $def['table'] . ' SET ' . $def['order'] . ' = ' . $def['order'] . ' + 1'
                 . ' WHERE ' . ($newWhere !== '' ? $newWhere . ' AND ' : '') . $def['order'] . ' >= ?';
            $newParams[] = $target;
            $this->db->query($sql, $newParams);

            if ($newScope === null) {
                $this->db->query(
                    'UPDATE ' . $def['table'] . ' SET ' . $def['scope'] . ' = NULL, ' . $def['order'] . ' = ? WHERE id = ?',
                    [$target, $id]
                );
            } else {
                $this->db->query(
                    'UPDATE ' . $def['table'] . ' SET ' . $def['scope'] . ' = ?, ' . $def['order'] . ' = ? WHERE id = ?',
                    [$newScope, $target, $id]
                );
            }
            return $target;
        }

        $scope = $def['scope'] ? $oldScope : null;
        $this->normalize($entity, $scope);
        $current = (int)$this->db->fetchValue('SELECT ' . $def['order'] . ' FROM ' . $def['table'] . ' WHERE id = ?', [$id]);
        $count = $this->count($def, $scope);
        $target = $target === null ? max(0, $count - 1) : max(0, min($target, max(0, $count - 1)));

        if ($target < $current) {
            [$where, $params] = $this->scopeWhere($def, $scope);
            $sql = 'UPDATE ' . $def['table'] . ' SET ' . $def['order'] . ' = ' . $def['order'] . ' + 1'
                 . ' WHERE ' . ($where !== '' ? $where . ' AND ' : '')
                 . $def['order'] . ' >= ? AND ' . $def['order'] . ' < ? AND id <> ?';
            array_push($params, $target, $current, $id);
            $this->db->query($sql, $params);
        } elseif ($target > $current) {
            [$where, $params] = $this->scopeWhere($def, $scope);
            $sql = 'UPDATE ' . $def['table'] . ' SET ' . $def['order'] . ' = ' . $def['order'] . ' - 1'
                 . ' WHERE ' . ($where !== '' ? $where . ' AND ' : '')
                 . $def['order'] . ' > ? AND ' . $def['order'] . ' <= ? AND id <> ?';
            array_push($params, $current, $target, $id);
            $this->db->query($sql, $params);
        }

        $this->db->query('UPDATE ' . $def['table'] . ' SET ' . $def['order'] . ' = ? WHERE id = ?', [$target, $id]);
        return $target;
    }

    /** Rewrite a scope to contiguous 0..N-1 positions, preserving current order then id. */
    public function normalize(string $entity, mixed $scope = null): void
    {
        $def = $this->definition($entity);
        [$where, $params] = $this->scopeWhere($def, $scope);
        $sql = 'SELECT id, ' . $def['order'] . ' AS position FROM ' . $def['table']
             . ($where !== '' ? ' WHERE ' . $where : '')
             . ' ORDER BY CASE WHEN ' . $def['order'] . ' IS NULL THEN 1 ELSE 0 END, ' . $def['order'] . ' ASC, id ASC FOR UPDATE';
        $rows = $this->db->fetchArray($sql, $params);
        foreach ($rows as $position => $row) {
            if ($row['position'] === null || (int)$row['position'] !== $position) {
                $this->db->query(
                    'UPDATE ' . $def['table'] . ' SET ' . $def['order'] . ' = ? WHERE id = ?',
                    [$position, (int)$row['id']]
                );
            }
        }
    }

    private function definition(string $entity): array
    {
        if (!isset(self::DEFINITIONS[$entity])) {
            throw new InvalidArgumentException('Unknown ordering entity: ' . $entity);
        }
        return self::DEFINITIONS[$entity];
    }

    private function count(array $def, mixed $scope): int
    {
        [$where, $params] = $this->scopeWhere($def, $scope);
        return (int)$this->db->fetchValue(
            'SELECT COUNT(*) FROM ' . $def['table'] . ($where !== '' ? ' WHERE ' . $where : ''),
            $params
        );
    }

    private function scopeWhere(array $def, mixed $scope): array
    {
        if (!$def['scope']) {
            return ['', []];
        }
        $scope = $this->normaliseScopeValue($scope);
        if ($scope === null) {
            return [$def['scope'] . ' IS NULL', []];
        }
        return [$def['scope'] . ' = ?', [$scope]];
    }

    private function normaliseScopeValue(mixed $scope): mixed
    {
        if ($scope === null || $scope === '') {
            return null;
        }
        return is_numeric($scope) ? (int)$scope : $scope;
    }

    private function sameScope(mixed $a, mixed $b): bool
    {
        return $this->normaliseScopeValue($a) === $this->normaliseScopeValue($b);
    }
}
