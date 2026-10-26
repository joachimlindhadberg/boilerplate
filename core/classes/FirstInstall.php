<?php

declare(strict_types=1);

/**
 * Explicit fresh-site installation. NEVER an upgrade or a database repair tool.
 * Do not call through ordinary CMS bootstrap, which requires installed tables.
 */
final class FirstInstall
{
    public const SCHEMA_FILE = __DIR__ . '/../../database/install/empty-schema.sql';

    public static function configured(string $root): bool
    {
        // An existing config ALWAYS wins, even if its DB is inaccessible.
        if (file_exists($root . '/.env')) return true;
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
            if (getenv($key) !== false || isset($_ENV[$key]) || isset($_SERVER[$key])) return true;
        }
        return false;
    }

    /**
     * The keyless browser installer requires exactly the configured public webroot.
     * This is a layout/safety check, NOT proof of who owns an unconfigured site.
     */
    public static function hasPrivateWebroot(string $root, string $public, string $documentRoot): bool
    {
        $privateReal = realpath($root);
        $publicReal = realpath($public);
        $documentReal = $documentRoot !== '' ? realpath($documentRoot) : false;
        return is_string($privateReal) && is_string($publicReal) && is_string($documentReal)
            && $privateReal !== $publicReal
            && dirname($publicReal) === $privateReal
            && $documentReal === $publicReal;
    }

    /** @return array{host:string,port:int,name:string,user:string,pass:string} */
    public static function validateDb(array $data): array
    {
        $values = [];
        foreach (['host', 'name', 'user', 'pass'] as $key) {
            if (!is_string($data[$key] ?? null)) throw new InvalidArgumentException('Invalid database connection details.');
            $value = $data[$key];
            if ($value === '' || strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/', $value)
                || $value !== trim($value)) {
                throw new InvalidArgumentException('Database connection details contain empty or invalid values.');
            }
            $values[$key] = $value;
        }
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/D', $values['name'])) {
            throw new InvalidArgumentException('The database name contains unsupported characters.');
        }
        $port = filter_var($data['port'] ?? '3306', FILTER_VALIDATE_INT, ['options' => ['min_range'=>1,'max_range'=>65535]]);
        if ($port === false) throw new InvalidArgumentException('The database port is invalid.');
        return ['host'=>$values['host'], 'port'=>(int)$port, 'name'=>$values['name'],
            'user'=>$values['user'], 'pass'=>$values['pass']];
    }

    /** Opens a dedicated connection, without the installed CMS Db singleton. */
    public static function connect(array $config): mysqli
    {
        if (!extension_loaded('mysqli')) throw new RuntimeException('The mysqli PHP extension is required.');
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = new mysqli($config['host'], $config['user'], $config['pass'], $config['name'], $config['port']);
        $db->set_charset('utf8mb4');
        return $db;
    }

    public static function assertEmpty(mysqli $db): void
    {
        // Reject tables AND views; never attempt to reuse a partial installation.
        $result = $db->query('SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        $count = (int)$result->fetch_assoc()['n'];
        $result->free();
        if ($count !== 0) {
            throw new DomainException('The database already contains tables or views. Choose an empty database. Existing data was not changed.');
        }
    }

    /** Read-only capability check. Runs before any non-transactional install DDL. */
    public static function assertInnoDbSupported(mysqli $db): void
    {
        $result = $db->query("SELECT SUPPORT FROM INFORMATION_SCHEMA.ENGINES WHERE ENGINE = 'InnoDB'");
        $row = $result->fetch_assoc();
        $result->free();
        if (!is_array($row) || !in_array(strtoupper((string)($row['SUPPORT'] ?? '')), ['YES', 'DEFAULT'], true)) {
            throw new DomainException('This database server must support InnoDB to install Boilerplate CMS. No tables were created.');
        }
    }

    public static function validateSite(array $post): array
    {
        $name = is_string($post['site_name'] ?? null) ? trim($post['site_name']) : '';
        $url = is_string($post['site_url'] ?? null) ? trim($post['site_url']) : '';
        $first = is_string($post['first'] ?? null) ? trim($post['first']) : '';
        $last = is_string($post['last'] ?? null) ? trim($post['last']) : '';
        $email = is_string($post['email'] ?? null) ? strtolower(trim($post['email'])) : '';
        $password = is_string($post['password'] ?? null) ? $post['password'] : '';
        if ($name === '' || strlen($name) > 191 || $first === '' || strlen($first) > 100
            || $last === '' || strlen($last) > 100 || preg_match('/[\x00-\x1F\x7F]/', $name . $first . $last)) {
            throw new InvalidArgumentException('Enter a valid site name and administrator name.');
        }
        $origin = SiteUrl::normalizeOrigin($url);
        if ($origin === null) throw new InvalidArgumentException('The website URL must be a complete http(s) origin without a path.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            throw new InvalidArgumentException('The administrator email address is invalid.');
        }
        if (strlen($password) < 12 || strlen($password) > 1024 || trim($password) === '') {
            throw new InvalidArgumentException('The administrator password must be at least 12 characters.');
        }
        return ['site_name'=>$name, 'site_url'=>$origin, 'first'=>$first, 'last'=>$last,
            'email'=>$email, 'password'=>$password];
    }

    /** Execute a checked, fixed SQL baseline; no user-provided SQL. */
    private static function createSchema(mysqli $db): void
    {
        $sql = file_get_contents(self::SCHEMA_FILE);
        if (!is_string($sql) || !str_contains($sql, 'CREATE TABLE `users`')
            || !str_contains($sql, 'CREATE TABLE `schema_migrations`')) {
            throw new RuntimeException('The installation schema is missing or incomplete.');
        }
        $sql = preg_replace('/^--[^\r\n]*\r?\n/m', '', $sql);
        if (!is_string($sql)) throw new RuntimeException('The installation schema could not be read.');
        $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: []));
        if (count($statements) < 50) throw new RuntimeException('The installation schema is incomplete.');
        foreach ($statements as $statement) {
            if (!preg_match('/^(?:SET NAMES utf8mb4|CREATE TABLE `[a-z_]+`|ALTER TABLE `[a-z_]+`)/i', $statement)) {
                throw new RuntimeException('Unexpected installation SQL; no further statements will be executed.');
            }
            $db->query($statement);
        }
    }

    /** Keep trigger definitions equivalent to the protected historical migration. */
    private static function createTriggers(mysqli $db): void
    {
        $dirty = 'UPDATE media_reference_state SET dirty = 1, updated_at = UTC_TIMESTAMP() WHERE id = 1';
        $specs = [
            ['media_ref_blocks_insert', 'page_blocks', 'BEFORE INSERT', $dirty],
            ['media_ref_blocks_update', 'page_blocks', 'BEFORE UPDATE', $dirty],
            ['media_ref_blocks_delete', 'page_blocks', 'BEFORE DELETE', $dirty],
            ['media_ref_pages_insert', 'pages', 'BEFORE INSERT', $dirty],
            ['media_ref_pages_update', 'pages', 'BEFORE UPDATE', 'IF NOT (OLD.page_content <=> NEW.page_content) THEN ' . $dirty . '; END IF'],
            ['media_ref_pages_delete', 'pages', 'BEFORE DELETE', $dirty],
            ['media_ref_media_insert', 'media', 'BEFORE INSERT', $dirty],
            ['media_ref_media_path_update', 'media', 'BEFORE UPDATE', 'IF NOT (OLD.file_path <=> NEW.file_path) THEN ' . $dirty . '; END IF'],
            ['media_ref_media_delete', 'media', 'BEFORE DELETE', "BEGIN DECLARE index_dirty tinyint DEFAULT 1; SELECT dirty INTO index_dirty FROM media_reference_state WHERE id = 1; IF index_dirty <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Media reference index is stale; rebuild before deleting media'; END IF; END"],
        ];
        foreach ($specs as [$name, $table, $timing, $body]) {
            $db->query('CREATE TRIGGER `' . $name . '` ' . $timing . ' ON `' . $table . '` FOR EACH ROW '
                . (str_starts_with($body, 'BEGIN') ? $body : 'BEGIN ' . $body . '; END'));
        }
    }

    /** Seal the fresh baseline with verified migration checksums; never modify migration files. */
    private static function stampMigrations(mysqli $db, string $root): void
    {
        $files = glob($root . '/database/updates/*.php') ?: [];
        foreach (glob($root . '/modules/*/migrations/*.php') ?: [] as $file) $files[] = $file;
        // The baseline is explicitly reconciled with THESE six migrations.
        // Any future migration MUST receive an updated baseline review; never
        // silently mark an unknown migration applied without running it.
        $expected = [
            'core:20261013_000_core_schema',
            'core:20261013_010_media_reference_index',
            'core:20261016_000_pages_author_audit',
            'core:20261019_200_dashboard_cards',
            'module:forms:20261013_100_forms_schema',
            'module:gallery:20261013_100_gallery_schema',
            'module:gallery:20261019_100_gallery_lightbox',
        ];
        $actual = [];
        foreach ($files as $file) {
            $actual[] = str_contains($file, '/database/updates/') ? 'core:' . basename($file, '.php')
                : 'module:' . basename(dirname(dirname($file))) . ':' . basename($file, '.php');
        }
        sort($actual);
        sort($expected);
        if ($actual !== $expected) throw new RuntimeException('The installation baseline must be reconciled with the migration files.');
        $insert = $db->prepare('INSERT INTO schema_migrations (migration_id, checksum_sha256, applied_at) VALUES (?, ?, NOW())');
        foreach ($files as $file) {
            if (is_link($file) || !preg_match('/^[0-9]{8}_[a-z0-9_-]+\.php$/D', basename($file))) {
                throw new RuntimeException('Invalid migration file.');
            }
            $id = str_contains($file, '/database/updates/') ? 'core:' . basename($file, '.php')
                : 'module:' . basename(dirname(dirname($file))) . ':' . basename($file, '.php');
            $hash = hash_file('sha256', $file);
            if (!is_string($hash)) throw new RuntimeException('Could not calculate the migration checksum.');
            $insert->bind_param('ss', $id, $hash);
            $insert->execute();
        }
        $insert->close();
    }

    private static function seed(mysqli $db, array $site): void
    {
        $db->query("INSERT INTO roles (id, role_name) VALUES (1,'admin'),(2,'editor'),(3,'user')");
        $settings = [
            'site_name' => $site['site_name'], 'site_url' => $site['site_url'],
            'site_tagline' => '', 'admin_email' => $site['email'],
            'notification_email' => $site['email'], 'contact_notification_email' => $site['email'],
            'date_format' => 'd-m-Y', 'time_format' => 'H:i', 'timezone' => 'Europe/Copenhagen',
            'language' => 'en_US', 'charset' => 'utf-8', 'posts_per_page' => '10',
            'maintenance_mode' => '0', 'theme' => 'default', 'seo_title_separator' => '|',
            'seo_indexing' => '0', 'session_timeout' => '1800', 'remember_me_days' => '30',
            'remember_cookie_name' => 'boilerplate_remember',
            'navigation_header_menu' => 'primary', 'navigation_footer_menu' => 'footer',
            'admin_language' => 'en',
        ];
        $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value, autoload) VALUES (?, ?, 1)');
        foreach ($settings as $key => $value) {
            $stmt->bind_param('ss', $key, $value);
            $stmt->execute();
        }
        $stmt->close();
        $db->query("INSERT INTO navigation_menus (name, slug) VALUES ('Main menu', 'primary'), ('Footer menu', 'footer')");
        $page = $db->prepare("INSERT INTO pages (page_title, page_slug, page_description, page_content, content_mode, page_status, show_in_menu) VALUES (?, 'forside', '', ?, 'legacy', 'published', 1)");
        $title = 'Home';
        $content = '<section><div class="container"><h1>' . htmlspecialchars($site['site_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1></div></section>';
        $page->bind_param('ss', $title, $content);
        $page->execute();
        $page->close();
        $passwordHash = password_hash($site['password'], PASSWORD_DEFAULT);
        $user = $db->prepare("INSERT INTO users (role_id, firstname, lastname, email, password, weekly_hours, status, must_change_password, created_at, updated_at) VALUES (1, ?, ?, ?, ?, 0, 'active', 0, NOW(), NOW())");
        $user->bind_param('ssss', $site['first'], $site['last'], $site['email'], $passwordHash);
        $user->execute();
        $user->close();
        $db->query('INSERT INTO media_reference_state (id, dirty, updated_at) VALUES (1, 1, UTC_TIMESTAMP())');
        foreach (['example', 'forms', 'gallery'] as $slug) {
            $manifestPath = dirname(__DIR__, 2) . '/modules/' . $slug . '/module.json';
            $manifest = json_decode((string)@file_get_contents($manifestPath), true);
            if (!is_array($manifest)) continue;
            $name = (string)($manifest['name'] ?? $slug);
            $version = (string)($manifest['version'] ?? '');
            $stmt = $db->prepare('INSERT INTO modules (module_slug, module_name, installed_version, is_active) VALUES (?, ?, ?, 0)');
            $stmt->bind_param('sss', $slug, $name, $version);
            $stmt->execute();
            $stmt->close();
        }
    }

    private static function envText(array $db, string $siteUrl): string
    {
        // AppConfig supports paired single-quoted literal values; no interpolation.
        $values = [
            'APP_ENV' => in_array(strtolower((string)(parse_url($siteUrl, PHP_URL_HOST) ?: '')), ['localhost', '127.0.0.1', '[::1]'], true) ? 'development' : 'production',
            'APP_URL' => $siteUrl,
            // Public UI preference namespace; this is not an authentication secret.
            'CMS_SITE_INSTANCE_ID' => bin2hex(random_bytes(16)),
            'CANONICAL_REDIRECT' => 'true',
            'DB_HOST' => $db['host'], 'DB_PORT' => (string)$db['port'],
            'DB_NAME' => $db['name'], 'DB_USER' => $db['user'], 'DB_PASS' => $db['pass'],
        ];
        $result = "# Generated by Boilerplate CMS first-time installer. Keep private.\n";
        foreach ($values as $key => $value) {
            if (strpbrk($value, "\r\n\x00") !== false) throw new RuntimeException('The configuration contains invalid control characters.');
            $result .= $key . "='" . $value . "'\n";
        }
        return $result;
    }

    /**
     * Explicit destructive-to-empty-DB operation. Never invoked by normal page loads.
     * MySQL DDL commits independently: on partial failure, refuse automatic retries.
     */
    public static function install(string $root, array $config, array $site): void
    {
        if (self::configured($root)) throw new DomainException('The installation is already configured.');
        $storage = $root . '/storage';
        if (!is_dir($storage) && !@mkdir($storage, 0700) && !is_dir($storage)) {
            throw new RuntimeException('Could not create the private storage directory.');
        }
        if (!is_writable($storage)) throw new RuntimeException('The private storage directory must be writable by PHP.');
        $lockFile = $storage . '/installer.lock';
        $lock = fopen($lockFile, 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Another installation is currently running.');
        @chmod($lockFile, 0600);
        try {
            if (self::configured($root)) throw new DomainException('The installation is already configured.');
            if (!is_file(self::SCHEMA_FILE)) throw new RuntimeException('The installation baseline is missing.');
            // tempnam() may fall back to the system temp directory when $root is unwritable.
            // Require the probe to be created in the actual .env destination before any DDL.
            $probe = @tempnam($root, '.cms-install-check-');
            if ($probe === false) throw new RuntimeException('The project root must be writable during installation.');
            try {
                if (realpath(dirname($probe)) !== realpath($root)) {
                    throw new RuntimeException('The project root must be writable during installation.');
                }
            } finally {
                @unlink($probe);
            }
            // Validate and generate the complete config before irreversible MySQL DDL.
            $env = self::envText($config, $site['site_url']);
            $handle = self::connect($config);
            try {
                self::assertEmpty($handle);
                self::assertInnoDbSupported($handle);
                self::createSchema($handle);
                self::createTriggers($handle);
                // Seed and registry writes are transactional; schema/trigger
                // DDL itself cannot be rolled back in MySQL.
                $handle->begin_transaction();
                try {
                    self::seed($handle, $site);
                    self::stampMigrations($handle, $root);
                    $handle->commit();
                } catch (Throwable $error) {
                    $handle->rollback();
                    throw $error;
                }
                // Sanity checks before publishing the config.
                $check = $handle->query("SELECT (SELECT COUNT(*) FROM users) AS users, (SELECT COUNT(*) FROM schema_migrations) AS migrations, (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME LIKE 'media_ref_%') AS triggers")->fetch_assoc();
                if ((int)$check['users'] !== 1 || (int)$check['migrations'] < 5 || (int)$check['triggers'] !== 9) {
                    throw new RuntimeException('Installation verification failed.');
                }
                $envHandle = @fopen($root . '/.env', 'x');
                if ($envHandle === false) throw new RuntimeException('Could not create .env. The database was created; contact an administrator and do not rerun the installer.');
                @chmod($root . '/.env', 0600);
                if (fwrite($envHandle, $env) !== strlen($env)) {
                    fclose($envHandle);
                    throw new RuntimeException('Could not write the complete configuration. Inspect .env securely.');
                }
                fflush($envHandle);
                fclose($envHandle);
            } finally {
                $handle->close();
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
