<?php
/**
 * ------------------------------------------------------------
 * File: Settings.php
 * ------------------------------------------------------------
 *
 * Functions:
 * - Load autoload settings (cache)
 * - hent én setting
 * - hent alle settings
 * - opret / opdater setting
 * - slet setting
 *
 * Author: Joachim Lind Udholm Hadberg
 * ------------------------------------------------------------
 */

class Settings {

    protected Db $db;

    /**
     * Runtime cache (autoload)
     */
    protected array $cache = [];
    private int $cacheGeneration = -1;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? new Db();

        // load autoload settings automatically
        $this->loadAutoload();
    }

    /* ------------------------------------------------------------
       LOAD AUTOLOAD SETTINGS (WORDPRESS STYLE)
    ------------------------------------------------------------ */
    protected function loadAutoload(): void
    {
        $fetch = function (): array {
            $rows = $this->db->fetchArray(
                'SELECT setting_key, setting_value FROM settings WHERE autoload = 1'
            );
            $values = [];
            foreach ($rows as $row) {
                $values[$row['setting_key']] = $this->decode($row['setting_value']);
            }
            return $values;
        };

        // Uncommitted values must not enter shared memoization. After COMMIT
        // or ROLLBACK Db invalidates this namespace for all Settings objects.
        $this->cache = Db::transactionActive()
            ? $fetch()
            : RequestCache::remember('settings', 'autoload', $fetch);
        $this->cacheGeneration = RequestCache::generation('settings');
    }

    private function refreshIfInvalidated(): void
    {
        if ($this->cacheGeneration !== RequestCache::generation('settings')) {
            $this->loadAutoload();
        }
    }

    /* ------------------------------------------------------------
       HENT SETTING
    ------------------------------------------------------------ */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->refreshIfInvalidated();

        // cached?
        if (array_key_exists($key, $this->cache)) {

            return $this->cache[$key];
        }

        // fallback DB lookup
        $sql = "
            SELECT setting_value
            FROM settings
            WHERE setting_key = ?
            LIMIT 1
        ";

        $result = $this->db->fetchArray($sql, [$key]);

        if (!$result) {

            return $default;
        }

        $value = $this->decode($result[0]['setting_value']);

        // Within a transaction a later rollback must not poison the cache.
        if (!Db::transactionActive()) {
            $this->cache[$key] = $value;
        }

        return $value;
    }

    /* ------------------------------------------------------------
       HENT ALLE SETTINGS
    ------------------------------------------------------------ */
    public function all(): array
    {
        $sql = "
            SELECT setting_key, setting_value
            FROM settings
            ORDER BY setting_key ASC
        ";

        $rows = $this->db->fetchArray($sql);

        $settings = [];

        foreach ($rows as $row) {

            $settings[$row['setting_key']]
                = $this->decode($row['setting_value']);
        }

        return $settings;
    }

    /* ------------------------------------------------------------
       OPRET / OPDATER SETTING
    ------------------------------------------------------------ */
    public function set(
        string $key,
        mixed $value,
        int $autoload = 1
    ): void {
        $this->refreshIfInvalidated();
        $encoded = $this->encode($value);

        // Avoid deprecated VALUES(col) upsert syntax on modern MySQL.
        // Settings writes are admin-only, so a simple existence check keeps
        // the SQL portable and easy to reason about.
        $exists = (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM settings WHERE setting_key = ?",
            [$key]
        ) > 0;

        if ($exists) {
            $this->db->query(
                "UPDATE settings SET setting_value = ?, autoload = ? WHERE setting_key = ?",
                [$encoded, $autoload, $key]
            );
        } else {
            $this->db->query(
                "INSERT INTO settings (setting_key, setting_value, autoload) VALUES (?, ?, ?)",
                [$key, $encoded, $autoload]
            );
        }

        // Db::query() invalidated other live Settings instances. This one can
        // retain the just-written value without an extra roundtrip.
        $this->cacheGeneration = RequestCache::generation('settings');
        if ($autoload === 1 && !Db::transactionActive()) {
            $this->cache[$key] = $value;
        } else {
            unset($this->cache[$key]);
        }
    }

    /* ------------------------------------------------------------
       SLET SETTING
    ------------------------------------------------------------ */
    public function delete(string $key): void
    {
        $this->refreshIfInvalidated();
        $sql = "
            DELETE FROM settings
            WHERE setting_key = ?
        ";

        $this->db->query($sql, [$key]);
        $this->cacheGeneration = RequestCache::generation('settings');
        unset($this->cache[$key]);
    }

    /* ------------------------------------------------------------
       JSON ENCODE
    ------------------------------------------------------------ */
    protected function encode(mixed $value): string
    {

        // arrays + objects
        if (is_array($value) || is_object($value)) {

            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE
            );
        }

        return (string)$value;
    }

    /* ------------------------------------------------------------
       JSON DECODE
    ------------------------------------------------------------ */
    protected function decode(?string $value): mixed
    {

        if ($value === null) {

            return null;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {

            return $decoded;
        }

        return $value;
    }
}
