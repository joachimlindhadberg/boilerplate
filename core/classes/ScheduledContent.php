<?php

declare(strict_types=1);

/** Unified read-only schedule registry. Providers never contribute HTML. */
final class ScheduledContent
{
    private Db $db;
    private ModuleManager $modules;

    public function __construct(?Db $db = null, ?ModuleManager $modules = null)
    {
        $this->db = $db ?? new Db();
        $this->modules = $modules ?? new ModuleManager(null, $this->db);
    }

    /** @return array<int,ModuleScheduledContentProviderInterface> */
    private function providers(): array
    {
        return $this->modules->scheduledContentProviders();
    }

    /** All future/due actions from Pages and active module providers. */
    public function count(string $scope = 'upcoming'): int
    {
        self::assertScope($scope);
        $pred = $scope === 'upcoming' ? '> UTC_TIMESTAMP()' : '< UTC_TIMESTAMP() - INTERVAL 10 MINUTE';
        $sql = 'SELECT (SELECT COUNT(*) FROM page_schedules s INNER JOIN pages p ON p.id=s.page_id WHERE s.publish_at_utc IS NOT NULL AND s.publish_at_utc ' . $pred . ')'
            . ' + (SELECT COUNT(*) FROM page_schedules s INNER JOIN pages p ON p.id=s.page_id WHERE s.unpublish_at_utc IS NOT NULL AND s.unpublish_at_utc ' . $pred . ')';
        $count = (int)$this->db->fetchValue($sql);
        foreach ($this->providers() as $provider) $count += $provider->scheduledContentCount($scope);
        return $count;
    }

    /**
     * Sorted globally, with no mutable actions. Consumers paginate the result.
     * Each provider supplies at most $limit rows; the merged list is bounded.
     * @return array<int,array{type:string,title:string,action_type:string,due_at_utc:string,edit_url:string}>
     */
    public function items(string $scope = 'upcoming', int $limit = 100): array
    {
        self::assertScope($scope);
        $limit = max(1, min(2000, $limit));
        $predicate = $scope === 'upcoming' ? '> UTC_TIMESTAMP()' : '< UTC_TIMESTAMP() - INTERVAL 10 MINUTE';
        $order = $scope === 'upcoming' ? 'ASC' : 'DESC';
        $rows = $this->db->fetchArray(
            "SELECT x.page_id,x.page_title,x.action_type,x.due_at_utc FROM ("
            . "SELECT p.id AS page_id,p.page_title,'publish' AS action_type,s.publish_at_utc AS due_at_utc "
            . "FROM page_schedules s INNER JOIN pages p ON p.id=s.page_id WHERE s.publish_at_utc IS NOT NULL AND s.publish_at_utc {$predicate} "
            . "UNION ALL SELECT p.id AS page_id,p.page_title,'unpublish' AS action_type,s.unpublish_at_utc AS due_at_utc "
            . "FROM page_schedules s INNER JOIN pages p ON p.id=s.page_id WHERE s.unpublish_at_utc IS NOT NULL AND s.unpublish_at_utc {$predicate}"
            . ") x ORDER BY x.due_at_utc {$order},x.page_id ASC LIMIT {$limit}"
        );
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'type' => 'page', 'title' => (string)$row['page_title'],
                'action_type' => (string)$row['action_type'], 'due_at_utc' => (string)$row['due_at_utc'],
                'edit_url' => '/admin/sider?mode=edit&id=' . (int)$row['page_id'],
            ];
        }
        foreach ($this->providers() as $provider) {
            foreach ($provider->scheduledContent($scope, $limit) as $item) {
                if (!is_array($item) || !in_array($item['action_type'] ?? null, ['publish','unpublish'], true)
                    || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', (string)($item['due_at_utc'] ?? ''))
                    || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', (string)($item['type'] ?? ''))
                    || !str_starts_with((string)($item['edit_url'] ?? ''), '/admin/')) continue;
                $items[] = [
                    'type' => (string)$item['type'], 'title' => (string)($item['title'] ?? ''),
                    'action_type' => (string)$item['action_type'], 'due_at_utc' => (string)$item['due_at_utc'],
                    'edit_url' => (string)$item['edit_url'],
                ];
            }
        }
        usort($items, static function (array $a, array $b) use ($scope): int {
            $cmp = strcmp($a['due_at_utc'], $b['due_at_utc']);
            if ($cmp !== 0) return $scope === 'upcoming' ? $cmp : -$cmp;
            return strcmp($a['type'] . $a['edit_url'] . $a['action_type'], $b['type'] . $b['edit_url'] . $b['action_type']);
        });
        return array_slice($items, 0, $limit);
    }

    /** Keep every content-specific worker independent; one error is logged and reported. */
    public function runDue(int $limit = 25): int
    {
        $limit = max(1, min(100, $limit));
        $done = (new PageScheduler($this->db))->runDue($limit);
        foreach ($this->providers() as $provider) {
            $done += $provider->runScheduledContent($limit);
        }
        return $done;
    }

    public static function runOnTraffic(): void
    {
        try { (new self())->runDue(10); }
        catch (Throwable $e) { error_log('Unified scheduler: ' . $e->getMessage()); }
    }

    /** A fallback for future module types that have no core translation yet. */
    public static function typeLabel(string $type): string
    {
        $key = 'schedule.type.' . $type;
        $label = function_exists('t') ? t($key) : $key;
        return $label !== $key ? $label : ucfirst(str_replace(['_', '-'], ' ', $type));
    }

    private static function assertScope(string $scope): void
    {
        if (!in_array($scope, ['upcoming', 'overdue'], true)) {
            throw new InvalidArgumentException('Invalid scheduled-content scope.');
        }
    }
}
