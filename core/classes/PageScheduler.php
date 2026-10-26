<?php
/**
 * Scheduled page publication. Times are persisted as UTC (DATETIME) and
 * displayed in the site's configured timezone. No module CSS or routes.
 * This service can be reused by other content types via a future provider API.
 */
final class PageScheduler
{
    private Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? new Db();
    }

    public static function timezone(): DateTimeZone
    {
        return new DateTimeZone(date_default_timezone_get());
    }

    public static function localInput(?string $utc): string
    {
        if (!$utc) return '';
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
            ->setTimezone(self::timezone())->format('Y-m-d\TH:i');
    }

    public static function localLabel(?string $utc): string
    {
        if (!$utc) return '—';
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
            ->setTimezone(self::timezone())->format('d.m.Y H:i');
    }

    /**
     * Accept exactly YYYY-MM-DDTHH:MM local time. Reject nonexistent DST
     * local times (e.g. Europe/Copenhagen spring-forward).
     */
    public static function parseLocal(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $raw)) {
            throw new InvalidArgumentException('Indtast dato og klokkeslæt i et gyldigt format.');
        }
        $tz = self::timezone();
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $raw, $tz);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d\TH:i') !== $raw) {
            throw new InvalidArgumentException('Tidspunktet findes ikke i den valgte tidszone. Kontrollér sommertid.');
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** Validate a complete edit. Caller must store returned UTC values. */
    public static function validate(string $status, string $publishInput, string $unpublishInput, string $contentMode, array $blocks): array
    {
        $publish = self::parseLocal($publishInput);
        $unpublish = self::parseLocal($unpublishInput);
        $now = gmdate('Y-m-d H:i:s');
        if ($publish !== null && $status !== 'draft') {
            throw new InvalidArgumentException('Planlagt publicering kræver status Kladde.');
        }
        if ($unpublish !== null && $status !== 'published' && $publish === null) {
            throw new InvalidArgumentException('Planlagt afpublicering kræver en publiceret side eller en kommende publicering.');
        }
        if ($publish !== null && $publish <= $now) {
            throw new InvalidArgumentException('Publiceringstidspunktet skal ligge i fremtiden.');
        }
        if ($unpublish !== null && $unpublish <= $now) {
            throw new InvalidArgumentException('Afpubliceringstidspunktet skal ligge i fremtiden.');
        }
        if ($publish !== null && $unpublish !== null && $unpublish <= $publish) {
            throw new InvalidArgumentException('Afpublicering skal ligge efter publicering.');
        }
        if ($publish !== null && $contentMode === 'builder') {
            $active = array_filter($blocks, static fn(array $block): bool => ($block['status'] ?? 'active') === 'active');
            if (!$active) {
                throw new InvalidArgumentException('En planlagt Page Builder-side skal have mindst én aktiv sektion.');
            }
        }
        return [$publish, $unpublish];
    }

    public function getForPage(int $pageId): array
    {
        $rows = $this->db->fetchArray(
            'SELECT publish_at_utc, unpublish_at_utc FROM page_schedules WHERE page_id = ? LIMIT 1', [$pageId]
        );
        return $rows[0] ?? ['publish_at_utc' => null, 'unpublish_at_utc' => null];
    }

    /** Caller owns the page row lock + transaction. Null clears the schedule. */
    public function saveForPage(int $pageId, ?string $publishUtc, ?string $unpublishUtc): void
    {
        if ($publishUtc === null && $unpublishUtc === null) {
            $this->db->query('DELETE FROM page_schedules WHERE page_id = ?', [$pageId]);
            return;
        }
        $existing = $this->db->fetchValue('SELECT COUNT(*) FROM page_schedules WHERE page_id = ?', [$pageId]);
        if ((int)$existing > 0) {
            $this->db->query(
                'UPDATE page_schedules SET publish_at_utc = ?, unpublish_at_utc = ?, updated_at = UTC_TIMESTAMP() WHERE page_id = ?',
                [$publishUtc, $unpublishUtc, $pageId]
            );
        } else {
            $this->db->query(
                'INSERT INTO page_schedules (page_id, publish_at_utc, unpublish_at_utc, updated_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
                [$pageId, $publishUtc, $unpublishUtc]
            );
        }
    }

    /**
     * Idempotent, serialized worker. A missed complete publication window
     * expires into draft without temporarily exposing expired content.
     * Return number of due rows processed (including stale schedules).
     */
    public function runDue(int $limit = 25): int
    {
        $limit = max(1, min(100, $limit));
        // Cheap indexed preflight: idle websites avoid acquiring a global lock.
        $due = $this->db->fetchValue(
            'SELECT page_id FROM page_schedules WHERE '
            . '(publish_at_utc IS NOT NULL AND publish_at_utc <= UTC_TIMESTAMP()) OR '
            . '(unpublish_at_utc IS NOT NULL AND unpublish_at_utc <= UTC_TIMESTAMP()) LIMIT 1'
        );
        if ($due === null) return 0;
        $lockName = 'cms_pages_' . substr(hash('sha256', DbConf::name()), 0, 32);
        $gotLock = (int)$this->db->fetchValue('SELECT GET_LOCK(?, 0)', [$lockName]);
        if ($gotLock !== 1) return 0;
        try {
            $rows = $this->db->fetchArray(
                'SELECT page_id FROM page_schedules WHERE '
                . '(publish_at_utc IS NOT NULL AND publish_at_utc <= UTC_TIMESTAMP()) OR '
                . '(unpublish_at_utc IS NOT NULL AND unpublish_at_utc <= UTC_TIMESTAMP()) '
                . 'ORDER BY COALESCE(publish_at_utc, unpublish_at_utc), page_id LIMIT ' . $limit
            );
            $done = 0;
            $revisions = new PageRevisions($this->db);
            foreach ($rows as $row) {
                $pageId = (int)$row['page_id'];
                $this->db->beginTransaction();
                try {
                    $locked = $this->db->fetchArray('SELECT page_status FROM pages WHERE id = ? FOR UPDATE', [$pageId]);
                    if (!$locked) {
                        $this->db->query('DELETE FROM page_schedules WHERE page_id = ?', [$pageId]);
                        $this->db->commit();
                        $done++;
                        continue;
                    }
                    $schedule = $this->getForPage($pageId);
                    $publish = $schedule['publish_at_utc'] ?? null;
                    $unpublish = $schedule['unpublish_at_utc'] ?? null;
                    $now = gmdate('Y-m-d H:i:s');
                    $publishDue = $publish !== null && $publish <= $now;
                    $unpublishDue = $unpublish !== null && $unpublish <= $now;
                    if (!$publishDue && !$unpublishDue) {
                        $this->db->commit(); // Editor rescheduled after the preflight.
                        continue;
                    }
                    $status = (string)$locked[0]['page_status'];
                    $newStatus = $status;
                    $action = null;
                    if ($unpublishDue) {
                        // Expired schedules must never briefly expose the page.
                        if ($status === 'published') {
                            $newStatus = 'draft';
                            $action = 'scheduled_unpublish';
                        }
                        $publish = null;
                        $unpublish = null;
                    } elseif ($publishDue) {
                        if ($status === 'draft') {
                            $newStatus = 'published';
                            $action = 'scheduled_publish';
                        }
                        $publish = null;
                    }
                    if ($action !== null) {
                        if ($revisions->count($pageId) === 0) {
                            $revisions->record($pageId, null, 'baseline');
                        }
                        $this->db->query('UPDATE pages SET page_status = ?, updated = NOW(), updated_by = NULL WHERE id = ?', [$newStatus, $pageId]);
                        $revisions->record($pageId, null, $action);
                        // Scheduled actions belong to the system, not the last editor.
                        try {
                            $title = (string)$this->db->fetchValue('SELECT page_title FROM pages WHERE id = ?', [$pageId]);
                            (new ActivityLog($this->db))->record(
                                $action === 'scheduled_publish' ? 'published' : 'unpublished',
                                'page', $pageId, $title, null
                            );
                        } catch (Throwable $logError) {
                            error_log('Scheduled activity: ' . $logError->getMessage());
                        }
                    }
                    $this->saveForPage($pageId, $publish, $unpublish);
                    $this->db->commit();
                    $done++;
                } catch (Throwable $e) {
                    $this->db->rollback();
                    throw $e;
                }
            }
            return $done;
        } finally {
            $this->db->fetchValue('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    /** Request-driven fallback for shared hosting with no cron configuration. */
    public static function runOnTraffic(): void
    {
        try {
            (new self())->runDue(10);
        } catch (Throwable $e) {
            // Missing migration or temporary DB trouble must not take down a public site.
            error_log('PageScheduler: ' . $e->getMessage());
        }
    }
}
