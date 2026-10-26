<?php

declare(strict_types=1);

/**
 * Read-only, administrator-only diagnostic report.
 *
 * Never serializes environment variables, credentials, database rows, user
 * records, file contents, absolute paths, client IPs or HTTP headers.
 */
final class SiteHealthReport
{
    /** @return string UTF-8 plain text, intentionally suitable for support. */
    public static function build(array $health, ?Db $db = null): string
    {
        $tr = static fn(string $key, array $args = []): string =>
            function_exists('t') ? t('health_report.' . $key, $args) : 'health_report.' . $key;
        $clean = static function (mixed $value): string {
            $string = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$value);
            if (!is_string($string) || trim($string) === '') return '-';
            // Defensive redaction if a site URL was configured with Basic Auth.
            $string = preg_replace('~(https?://)[^/@\s]+@~i', '$1[redacted]@', $string) ?? $string;
            return trim($string);
        };
        $yesNo = static fn(bool $value): string => $tr($value ? 'yes' : 'no');
        $lines = [];
        $advice = [];
        $lines[] = 'BOILERPLATE CMS — ' . $tr('title');
        $lines[] = str_repeat('=', 62);
        $lines[] = $tr('generated') . ': ' . gmdate('Y-m-d H:i:s') . ' UTC';
        $lines[] = $tr('language') . ': ' . (class_exists(Translator::class) ? Translator::locale() : 'en');
        $lines[] = $tr('privacy');
        $lines[] = '';

        $lines[] = '[' . $tr('summary') . ']';
        $counts = (array)($health['counts'] ?? []);
        $lines[] = $tr('overall') . ': ' . $tr('status.' . self::safeStatus($health['overall'] ?? 'warning'));
        foreach (['good', 'warning', 'error'] as $level) {
            $lines[] = $tr('status.' . $level) . ': ' . max(0, (int)($counts[$level] ?? 0));
        }
        $lines[] = '';

        $lines[] = '[' . $tr('existing_checks') . ']';
        foreach ((array)($health['checks'] ?? []) as $check) {
            if (!is_array($check)) continue;
            $level = self::safeStatus($check['status'] ?? 'warning');
            $label = $clean($check['label'] ?? '-');
            $detail = $clean($check['detail'] ?? '-');
            $lines[] = '[' . strtoupper($level) . '] ' . $label . ': ' . $detail;
            if ($level !== 'good') $advice[] = $label . ': ' . $detail;
        }
        $lines[] = '';

        $lines[] = '[' . $tr('runtime') . ']';
        self::field($lines, 'PHP', PHP_VERSION);
        self::field($lines, 'PHP SAPI', PHP_SAPI);
        self::field($lines, 'Timezone', date_default_timezone_get());
        foreach (['memory_limit', 'upload_max_filesize', 'post_max_size', 'max_execution_time'] as $option) {
            self::field($lines, $option, $clean(ini_get($option) ?: '-'));
        }
        foreach (['mysqli', 'fileinfo', 'openssl', 'mbstring', 'zlib', 'json'] as $extension) {
            self::field($lines, 'ext.' . $extension, $yesNo(extension_loaded($extension)));
        }
        self::field($lines, 'OPcache', $yesNo(function_exists('opcache_get_status') && is_array(@opcache_get_status(false))));
        $imageEditor = function_exists('imagecreatetruecolor') || class_exists('Imagick');
        self::field($lines, 'Image editor (GD or Imagick)', $yesNo($imageEditor));
        if (!$imageEditor) $advice[] = 'Enable the PHP GD or Imagick extension to generate optimized responsive image sizes. Original uploads still work.';
        $postMax = self::sizeBytes((string)ini_get('post_max_size'));
        if ($postMax > 0 && $postMax < AdminTools::MAX_IMPORT_BYTES + 262144) {
            $advice[] = $tr('recommend.post_max');
        }
        $lines[] = '';

        $lines[] = '[' . $tr('security') . ']';
        $env = class_exists(AppConfig::class) ? AppConfig::environment() : 'unknown';
        self::field($lines, 'APP_ENV', $clean($env));
        $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
        self::field($lines, 'HTTPS ' . $tr('request'), $yesNo($https));
        foreach (['session.cookie_httponly', 'session.cookie_secure', 'session.use_strict_mode', 'display_errors', 'expose_php'] as $option) {
            $enabled = filter_var(ini_get($option), FILTER_VALIDATE_BOOLEAN);
            self::field($lines, $option, $yesNo($enabled));
            if ($option === 'session.cookie_httponly' && !$enabled) $advice[] = $tr('recommend.httponly');
            if ($option === 'session.use_strict_mode' && !$enabled) $advice[] = $tr('recommend.strict_mode');
            if ($env === 'production' && $option === 'display_errors' && $enabled) $advice[] = $tr('recommend.display_errors');
            if ($env === 'production' && $option === 'session.cookie_secure' && !$enabled) $advice[] = $tr('recommend.secure_cookie');
        }
        if ($env === 'production' && !$https) $advice[] = $tr('recommend.https');
        $lines[] = '';

        $lines[] = '[' . $tr('files') . ']';
        foreach ([
            'public .htaccess' => defined('PUBLICPATH') ? PUBLICPATH . '/.htaccess' : '',
            'public uploads' => defined('PUBLICPATH') ? PUBLICPATH . '/uploads' : '',
        ] as $label => $path) {
            if ($path === '') continue;
            if (!file_exists($path)) {
                self::field($lines, $label, $tr('missing'));
                if ($label === 'public .htaccess') $advice[] = $tr('recommend.htaccess');
                continue;
            }
            $perms = @fileperms($path);
            $mode = $perms === false ? '-' : substr(sprintf('%04o', $perms & 0777), -4);
            $readable = is_readable($path);
            self::field($lines, $label, $tr('permissions') . ': ' . $mode . '; ' . $tr('readable') . ': ' . $yesNo($readable));
            if (!$readable && $label === 'public .htaccess') $advice[] = $tr('recommend.htaccess');
            if (is_dir($path)) self::field($lines, $label . ' (' . $tr('writable') . ')', $yesNo(is_writable($path)));
        }
        // Legacy storage appears only for upgrades/restore; never report its
        // absence as a failure on new installations.
        if (defined('PUBLICPATH') && (file_exists(PUBLICPATH . '/assets/uploads') || is_link(PUBLICPATH . '/assets/uploads'))) {
            $legacy = PUBLICPATH . '/assets/uploads';
            self::field($lines, 'legacy uploads', is_dir($legacy) && !is_link($legacy) ? $tr('readable') . ': ' . $yesNo(is_readable($legacy)) : $tr('missing'));
            if (!is_dir($legacy) || is_link($legacy) || !is_file($legacy . '/.htaccess') || is_link($legacy . '/.htaccess')
                || !is_readable($legacy . '/.htaccess') || !is_file(PUBLICPATH . '/uploads/.htaccess')
                || !is_readable(PUBLICPATH . '/uploads/.htaccess')
                || !hash_equals((string)hash_file('sha256', PUBLICPATH . '/uploads/.htaccess'), (string)hash_file('sha256', $legacy . '/.htaccess'))) {
                $advice[] = 'Legacy assets/uploads/ must retain its upload .htaccess protection; do not expose legacy files without it.';
            }
        }
        if (defined('ROOTPATH') && is_file(ROOTPATH . '/.env')) {
            $envPerms = @fileperms(ROOTPATH . '/.env');
            self::field($lines, '.env ' . $tr('world_readable'), $yesNo($envPerms !== false && (bool)($envPerms & 0004)));
            if ($envPerms !== false && ($envPerms & 0004)) $advice[] = $tr('recommend.env_perms');
        }
        if (defined('ROOTPATH')) {
            $free = @disk_free_space(ROOTPATH);
            if (is_float($free) || is_int($free)) {
                self::field($lines, $tr('disk_free'), round($free / (1024 ** 3), 2) . ' GiB');
                if ($free < 1024 ** 3) $advice[] = $tr('recommend.disk');
            }
        }
        $lines[] = '';

        $lines[] = '[' . $tr('database') . ']';
        if ($db !== null) {
            try {
                self::field($lines, 'MySQL / MariaDB', $clean($db->fetchValue('SELECT VERSION()')));
                self::field($lines, 'Connection charset', $clean($db->fetchValue('SELECT @@character_set_connection')));
                self::field($lines, 'Connection collation', $clean($db->fetchValue('SELECT @@collation_connection')));
                $tableCount = $db->fetchValue('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
                self::field($lines, $tr('table_count'), (int)$tableCount);
                foreach (['pages', 'page_blocks', 'media', 'modules'] as $table) {
                    try {
                        self::field($lines, $table . ' (' . $tr('rows') . ')', (int)$db->fetchValue('SELECT COUNT(*) FROM `' . $table . '`'));
                    } catch (Throwable $e) {
                        self::field($lines, $table, $tr('unavailable'));
                    }
                }
            } catch (Throwable $e) {
                $lines[] = $tr('database_unavailable');
                $advice[] = $tr('recommend.database');
            }
        } else {
            $lines[] = $tr('database_unavailable');
        }
        $lines[] = '';

        $lines[] = '[' . $tr('modules') . ']';
        if ($db !== null && class_exists(ModuleManager::class)) {
            try {
                $modules = (new ModuleManager(null, $db))->allModules();
                if (!$modules) $lines[] = $tr('none');
                foreach ($modules as $module) {
                    $slug = preg_replace('/[^a-z0-9_-]/i', '', (string)($module['slug'] ?? ''));
                    $version = preg_replace('/[^a-z0-9._-]/i', '', (string)($module['version'] ?? ''));
                    $status = !empty($module['active']) ? $tr('active') : $tr('inactive');
                    if (empty($module['valid']) || empty($module['available'])) {
                        $status = $tr('invalid');
                        $advice[] = $tr('recommend.module') . ': ' . $slug;
                    }
                    $lines[] = '- ' . $slug . ' (' . $version . '): ' . $status;
                }
            } catch (Throwable $e) {
                $lines[] = $tr('unavailable');
            }
        } else {
            $lines[] = $tr('unavailable');
        }
        $lines[] = '';

        $lines[] = '[' . $tr('media_index') . ']';
        if ($db !== null && class_exists(MediaReferenceIndex::class)) {
            try {
                $state = (new MediaReferenceIndex($db))->status();
                self::field($lines, $tr('media_index_dirty'), $yesNo((bool)($state['dirty'] ?? false)));
                self::field($lines, $tr('media_index_references'), (int)($state['references'] ?? 0));
                if (!empty($state['dirty'])) $advice[] = $tr('recommend.media_index');
            } catch (Throwable $e) {
                $lines[] = $tr('unavailable');
            }
        } else {
            $lines[] = $tr('unavailable');
        }
        $lines[] = '';

        $lines[] = '[' . $tr('recommendations') . ']';
        if (!$advice) $lines[] = $tr('no_recommendations');
        else foreach (array_values(array_unique($advice)) as $i => $note) $lines[] = ($i + 1) . '. ' . $clean($note);
        $lines[] = '';
        $lines[] = $tr('privacy_footer');
        return implode("\n", $lines) . "\n";
    }

    private static function safeStatus(mixed $value): string
    {
        return in_array($value, ['good', 'warning', 'error'], true) ? $value : 'warning';
    }

    private static function field(array &$lines, string $label, string|int|float $value): void
    {
        $lines[] = $label . ': ' . $value;
    }

    /** Convert simple php.ini memory size string to bytes without side effects. */
    private static function sizeBytes(string $value): int
    {
        $number = (float)$value;
        if ($number <= 0) return 0;
        $unit = strtolower(substr(trim($value), -1));
        return (int)($number * match ($unit) {
            'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1,
        });
    }
}
