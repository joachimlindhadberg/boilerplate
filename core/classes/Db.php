<?php
/**
 * Db class
 *
 * Central database wrapper using mysqli + prepared statements
 *
 * @package Boilerplate
 * @since 1.0
 */

class Db
{
    protected mysqli $db;

    /** PHP request-local connection, never a persistent MySQL connection. */
    private static ?mysqli $requestConnection = null;
    private static bool $transactionActive = false;
    /** Track only live wrappers: explicitly closing one must not break others. */
    private static ?WeakMap $liveWrappers = null;
    private bool $closed = false;
    /** Capture on this wrapper: other services share the mysqli handle. */
    private int $lastInsertId = 0;

    /**
     * Create database connection
     *
     * Expects DbConf class with constants:
     * HOST, USER, PASS, NAME
     */
    public function __construct()
    {
        // Several CMS services are instantiated during one request. Sharing
        // the handle avoids repeating the MySQL handshake and charset setup.
        // The backup exporter deliberately owns a separate snapshot handle.
        if (self::$requestConnection !== null) {
            $this->db = self::$requestConnection;
            self::registerWrapper($this);
            return;
        }

        try {
            $connection = new mysqli(
                DbConf::host(),
                DbConf::user(),
                DbConf::pass(),
                DbConf::name(),
                DbConf::port()
            );
            if ($connection->connect_errno) {
                throw new RuntimeException('Database connection failed: ' . $connection->connect_error);
            }
            if (!$connection->set_charset('utf8mb4')) {
                throw new RuntimeException('Could not set database charset to utf8mb4.');
            }
        } catch (mysqli_sql_exception | RuntimeException $error) {
            error_log('Database connection failed: ' . $error->getMessage());
            throw new RuntimeException(
                AppConfig::isProduction() ? 'Database connection failed.' : $error->getMessage(),
                0,
                $error
            );
        }

        $this->db = self::$requestConnection = $connection;
        self::registerWrapper($this);
    }

    private static function registerWrapper(self $wrapper): void
    {
        self::$liveWrappers ??= new WeakMap();
        self::$liveWrappers[$wrapper] = true;
    }

    private function assertOpen(): void
    {
        if ($this->closed) {
            throw new LogicException('This Db wrapper has already been closed.');
        }
    }

    /** Run a prepared INSERT / UPDATE / DELETE statement. */
    public function query(string $sql, array $params = []): void
    {
        $stmt = $this->prepare($sql);
        try {
            $this->executeStatement($stmt, $params);
            $this->lastInsertId = (int)$this->db->insert_id;
            self::invalidateCachesAfterWrite($sql);
        } finally {
            $stmt->close();
        }
    }

    /**
     * CLI migration only: MySQL compound CREATE TRIGGER statements cannot
     * reliably be executed through every MySQL prepared-statement driver.
     * This never accepts user input and supports no generic dynamic SQL.
     */
    public function createMigrationTrigger(string $ddl): void
    {
        $this->assertOpen();
        if (PHP_SAPI !== 'cli' || !preg_match('/^CREATE TRIGGER `media_ref_[a-z_]+` (?:BEFORE) (?:INSERT|UPDATE|DELETE) ON `[a-z_]+` FOR EACH ROW BEGIN /D', $ddl)) {
            throw new LogicException('Only fixed, CLI-only media-reference trigger DDL is permitted.');
        }
        // mysqli::query sends a single compound statement to MySQL; SQL
        // client DELIMITER directives must never be included here.
        $this->db->query($ddl);
    }

    /** Fetch all rows, releasing both the result and statement on error. */
    public function fetchArray(string $sql, array $params = []): array
    {
        $stmt = $this->prepare($sql);
        try {
            $this->executeStatement($stmt, $params);
            $result = $stmt->get_result();
            try {
                return $result->fetch_all(MYSQLI_ASSOC);
            } finally {
                $result->free();
            }
        } finally {
            $stmt->close();
        }
    }

    /** Fetch first column of first row, releasing resources on error. */
    public function fetchValue(string $sql, array $params = [])
    {
        $stmt = $this->prepare($sql);
        try {
            $this->executeStatement($stmt, $params);
            $result = $stmt->get_result();
            try {
                $row = $result->fetch_row();
                return $row[0] ?? null;
            } finally {
                $result->free();
            }
        } finally {
            $stmt->close();
        }
    }

    /**
     * Get last inserted ID
     */
    public function getInsertId(): int
    {
        $this->assertOpen();
        return $this->lastInsertId;
    }

    /**
     * Prepare SQL statement
     */
    protected function prepare(string $sql): mysqli_stmt
    {
        $this->assertOpen();
        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new Exception('SQL prepare failed: ' . $this->db->error);
        }

        return $stmt;
    }

    /** Bind locally against the statement being executed (no shared mutable statement). */
    private function executeStatement(mysqli_stmt $stmt, array $params): void
    {
        if ($params !== []) {
            $types = '';
            $values = [];
            foreach ($params as $value) {
                $types .= $this->getType($value);
                $values[] = $value;
            }
            $stmt->bind_param($types, ...$values);
        }
        $stmt->execute();
    }

    /**
     * Determine parameter type
     */
    protected function getType($value): string
    {
        return match (true) {
            is_int($value)   => 'i',
            is_float($value) => 'd',
            is_null($value)  => 's',
            default          => 's',
        };
    }

    /**
     * Successful SQL mutations invalidate only relevant request caches.
     * This also covers writes performed outside Settings/Navigation models,
     * for example imports and direct admin SQL.
     */
    private static function invalidateCachesAfterWrite(string $sql): void
    {
        if (!preg_match('/^\s*(?:INSERT\s+(?:IGNORE\s+)?INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM)\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?/i', $sql, $match)) {
            return;
        }
        $table = strtolower($match[1]);
        if ($table === 'settings') {
            RequestCache::invalidate('settings');
        }
        if (in_array($table, ['navigation_items', 'navigation_menus', 'pages'], true)) {
            RequestCache::invalidate('navigation');
        }
    }

    /** True only while the current request-local handle owns a transaction. */
    public static function transactionActive(): bool
    {
        return self::$transactionActive;
    }

    /**
     * Transactions use the request-local handle. Callers that need independent
     * transaction boundaries must explicitly create a dedicated mysqli handle.
     * Do not memoize read results across transactional writes/rollbacks.
     */
    public function beginTransaction(): void
    {
        $this->assertOpen();
        if (self::$transactionActive) {
            throw new LogicException('A database transaction is already active in this request.');
        }
        if (!$this->db->begin_transaction()) {
            throw new RuntimeException('Could not start database transaction.');
        }
        self::$transactionActive = true;
    }

    public function commit(): void
    {
        $this->assertOpen();
        if (!$this->db->commit()) {
            throw new RuntimeException('Could not commit database transaction.');
        }
        self::$transactionActive = false;
        RequestCache::invalidate('settings');
        RequestCache::invalidate('navigation');
    }

    public function rollback(): void
    {
        $this->assertOpen();
        try {
            $this->db->rollback();
        } finally {
            self::$transactionActive = false;
            RequestCache::invalidate('settings');
            RequestCache::invalidate('navigation');
        }
    }

    /**
     * Release this wrapper. Other live Db objects keep using the request
     * connection; the mysqli handle is only closed if no wrapper needs it.
     * Ordinary HTTP code need not call close() (PHP tears it down at request end).
     */
    public function close(): void
    {
        if ($this->closed) return;
        if (self::$transactionActive) {
            throw new LogicException('Cannot close a database wrapper during a transaction.');
        }
        $this->closed = true;
        if (self::$liveWrappers !== null) {
            unset(self::$liveWrappers[$this]);
        }
        if (self::$liveWrappers === null || count(self::$liveWrappers) === 0) {
            self::$requestConnection?->close();
            self::$requestConnection = null;
            RequestCache::invalidate('settings');
            RequestCache::invalidate('navigation');
        }
    }
}
