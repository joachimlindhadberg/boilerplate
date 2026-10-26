<?php

declare(strict_types=1);

/**
 * Read-only, host-facing checks for a brand-new CMS installation.
 * No connection attempts, SQL statements, temp files, permission changes or repairs.
 * A PASS is a local PHP-filesystem observation, not proof that Apache applies .htaccess.
 */
final class InstallerPreflight
{
    /** @return list<array{id:string,label:string,ok:bool,detail:string,required:bool}> */
    public static function checks(string $root, string $public, string $documentRoot): array
    {
        $checks = [];
        $add = static function (string $id, string $label, bool $ok, string $detail, bool $required = true) use (&$checks): void {
            $checks[] = compact('id', 'label', 'ok', 'detail', 'required');
        };

        $add('php', 'PHP runtime (8.3+)', PHP_VERSION_ID >= 80300,
            'Select PHP 8.3 or newer in your hosting control panel.');
        foreach (['mysqli', 'mbstring', 'fileinfo', 'openssl', 'json', 'session', 'filter', 'hash'] as $extension) {
            $add('ext-' . $extension, 'PHP extension: ' . $extension, extension_loaded($extension),
                'Enable the ' . $extension . ' extension for this website in your hosting PHP settings.');
        }
        $add('webroot', 'Private CMS files outside document root',
            FirstInstall::hasPrivateWebroot($root, $public, $documentRoot),
            'Point this domain to the public CMS directory and keep core/, database/, storage/ and .env in its parent.');
        $add('schema', 'Installation schema is readable', is_file(FirstInstall::SCHEMA_FILE) && is_readable(FirstInstall::SCHEMA_FILE),
            'Upload the complete database/install/empty-schema.sql file to the private CMS directory.');
        $add('root-write', 'Private project root is writable by PHP', is_dir($root) && !is_link($root) && is_writable($root),
            'PHP must be able to create the private .env file during installation. Adjust ownership/permissions in your host panel.');
        $storage = $root . '/storage';
        $storageOk = !is_link($storage) && (
            (is_dir($storage) && is_writable($storage)) ||
            (!file_exists($storage) && is_writable($root))
        );
        $add('storage', 'Private storage is available', $storageOk,
            'Keep storage/ private and writable by PHP, or let the installer create it from a writable project root.');
        foreach (['uploads'] as $relative) {
            $path = $public . '/' . $relative;
            $add('upload-' . str_replace('/', '-', $relative), $relative . '/ is writable',
                is_dir($path) && !is_link($path) && is_writable($path),
                'Create the public ' . $relative . '/ directory and allow PHP to write uploads there.');
        }
        foreach (['.htaccess', 'admin/.htaccess', 'uploads/.htaccess'] as $relative) {
            $add('apache-' . str_replace('/', '-', $relative), $relative . ' is present',
                is_file($public . '/' . $relative) && !is_link($public . '/' . $relative) && is_readable($public . '/' . $relative),
                'Upload the original ' . $relative . ' file, including hidden dotfiles, without removing its security directives.');
        }
        // Legacy media storage is not required for a clean install, but if an
        // existing site carries it forward, do not accept an unprotected tree.
        $legacy = $public . '/assets/uploads';
        if (file_exists($legacy) || is_link($legacy)) {
            $legacyPolicy = $legacy . '/.htaccess';
            $currentPolicy = $public . '/uploads/.htaccess';
            $policyMatches = is_file($legacyPolicy) && !is_link($legacyPolicy)
                && is_readable($legacyPolicy) && is_file($currentPolicy)
                && is_readable($currentPolicy)
                && hash_equals((string)hash_file('sha256', $currentPolicy), (string)hash_file('sha256', $legacyPolicy));
            $add('legacy-upload-security', 'Legacy upload security policy matches',
                is_dir($legacy) && !is_link($legacy) && $policyMatches,
                'If restoring existing assets/uploads/ media, copy the original uploads/.htaccess policy there. Do not expose legacy media without matching policy.');
        }
        // These are operational capabilities. Present as unchecked, not as verified PASS.
        $add('apache-effective', 'Apache rewrite and security rules', false,
            'Cannot be verified from PHP alone. Host must permit .htaccess overrides and mod_rewrite; test access restrictions before go-live.', false);
        $add('database-privileges', 'Database CREATE / INDEX / TRIGGER privileges', false,
            'A connection test cannot safely guarantee all DDL/trigger rights. Confirm with your hosting provider; an install can leave partial tables.', false);
        $add('backup', 'PharData and zlib for backups', class_exists('PharData', false) && extension_loaded('zlib'),
            'Enable Phar and zlib if you need the integrated backup/restore feature.', false);
        return $checks;
    }

    /** @param list<array{id:string,label:string,ok:bool,detail:string,required:bool}> $checks */
    public static function ready(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['required'] && !$check['ok']) return false;
        }
        return true;
    }

    /** Must run before db-test and, crucially, before any installation DDL. */
    public static function requireReady(string $root, string $public, string $documentRoot): void
    {
        if (!self::ready(self::checks($root, $public, $documentRoot))) {
            throw new DomainException('Hosting requirements are not met. Review the checks below before continuing. No database changes were made by this request.');
        }
    }
}
