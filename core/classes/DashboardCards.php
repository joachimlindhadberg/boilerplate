<?php

declare(strict_types=1);

/**
 * Core-owned, per-user dashboard metric selection.
 * Only fixed, server-defined metrics can be selected; no module SQL or URL from POST.
 * No database writes occur outside the explicit authenticated save action.
 */
final class DashboardCards
{
    public const DEFAULTS = ['published', 'drafts', 'scheduled', 'media'];

    private const METRICS = [
        'published'   => ['dashboard.published_pages', '/admin/sider?status=published', 'file', 'content.manage'],
        'drafts'      => ['dashboard.drafts', '/admin/sider?status=draft', 'file', 'content.manage'],
        'scheduled'   => ['dashboard.scheduled', '/admin/scheduled-content?scope=upcoming', 'calendar', 'content.manage'],
        'media'       => ['dashboard.media', '/admin/medier', 'image', 'media.manage'],
        'pages_total' => ['dashboard.cards_pages_total', '/admin/sider', 'file', 'content.manage'],
        'forms'       => ['dashboard.cards_forms', '/admin/forms', 'file', 'content.manage'],
        'submissions' => ['dashboard.cards_submissions', '/admin/forms/submissions', 'inbox', 'content.manage'],
        'galleries'   => ['dashboard.cards_galleries', '/admin/gallerier', 'image', 'content.manage'],
    ];

    private Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? new Db();
    }

    /** @return array<string,array{label:string,url:string,icon:string}> */
    public static function available(string $role, array $activeModules): array
    {
        $options = [];
        foreach (self::METRICS as $key => [$label, $url, $icon, $capability]) {
            if (!CapabilityRegistry::allows($role, $capability)) continue;
            if (in_array($key, ['forms', 'submissions'], true) && !in_array('forms', $activeModules, true)) continue;
            if ($key === 'galleries' && !in_array('gallery', $activeModules, true)) continue;
            $options[$key] = ['label' => $label, 'url' => $url, 'icon' => $icon];
        }
        return $options;
    }

    /** @return list<string> */
    public function activeModules(): array
    {
        $rows = $this->db->fetchArray("SELECT module_slug FROM modules WHERE is_active = 1 AND module_slug IN ('forms','gallery')");
        return array_values(array_map(static fn(array $row): string => (string)$row['module_slug'], $rows));
    }

    /** Only read this user's own row, never accept a user ID from the request. */
    public function load(int $userId, array $available): array
    {
        $json = $this->db->fetchValue('SELECT cards_json FROM user_dashboard_cards WHERE user_id = ?', [$userId]);
        $stored = is_string($json) ? json_decode($json, true) : null;
        return self::sanitize($stored, $available);
    }

    /** If an option was retired/disabled, replace only that slot with a safe default. */
    public static function sanitize(mixed $stored, array $available): array
    {
        $result = [];
        $stored = is_array($stored) && array_is_list($stored) ? $stored : [];
        // Keep each selected card in its original slot when possible.
        for ($index = 0; $index < 4; $index++) {
            $candidate = $stored[$index] ?? null;
            if (is_string($candidate) && isset($available[$candidate]) && !in_array($candidate, $result, true)) {
                $result[] = $candidate;
                continue;
            }
            foreach (array_merge([self::DEFAULTS[$index]], self::DEFAULTS, array_keys($available)) as $fallback) {
                if (isset($available[$fallback]) && !in_array($fallback, $result, true)) {
                    $result[] = $fallback;
                    break;
                }
            }
        }
        return $result;
    }

    /** Reject duplicate, missing, unknown or unauthorized keys. */
    public static function validate(mixed $payload, array $available): array
    {
        if (!is_array($payload) || !array_is_list($payload) || count($payload) !== 4) {
            throw new InvalidArgumentException('Select exactly four dashboard metrics.');
        }
        foreach ($payload as $key) {
            if (!is_string($key) || !isset($available[$key])) {
                throw new InvalidArgumentException('Unavailable dashboard metric.');
            }
        }
        if (count(array_unique($payload)) !== 4) {
            throw new InvalidArgumentException('Dashboard metrics must be different.');
        }
        return $payload;
    }

    public function save(int $userId, array $selection): void
    {
        if ($userId < 1) throw new InvalidArgumentException('Invalid current user.');
        $json = json_encode($selection, JSON_THROW_ON_ERROR);
        $this->db->query(
            'INSERT INTO user_dashboard_cards (user_id, cards_json, updated_at) VALUES (?, ?, UTC_TIMESTAMP()) '
            . 'ON DUPLICATE KEY UPDATE cards_json = ?, updated_at = UTC_TIMESTAMP()',
            [$userId, $json, $json]
        );
    }

    /** Collect only additional requested metrics; a failed provider displays unavailable (—), not zero. */
    public function extraCounts(array $selection, array $activeModules): array
    {
        $counts = [];
        $queries = [
            'pages_total' => 'SELECT COUNT(*) FROM pages',
            'forms' => 'SELECT COUNT(*) FROM module_forms',
            'submissions' => 'SELECT COUNT(*) FROM module_form_submissions',
            'galleries' => 'SELECT COUNT(*) FROM module_galleries',
        ];
        foreach ($selection as $key) {
            if (!isset($queries[$key])) continue;
            if (in_array($key, ['forms', 'submissions'], true) && !in_array('forms', $activeModules, true)) continue;
            if ($key === 'galleries' && !in_array('gallery', $activeModules, true)) continue;
            try {
                $counts[$key] = (int)$this->db->fetchValue($queries[$key]);
            } catch (Throwable $error) {
                error_log('Dashboard count [' . $key . ']: ' . $error->getMessage());
                $counts[$key] = null;
            }
        }
        return $counts;
    }
}
