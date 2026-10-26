<?php

declare(strict_types=1);

/**
 * Read-only capability policy. Unknown capabilities fail closed.
 * Module grants are taken from validated module.json metadata, never from
 * executable module code, and can only cover that module's own namespace.
 */
final class CapabilityRegistry
{
    private const CORE = [
        'admin.access' => ['admin', 'editor'],
        'content.manage' => ['admin', 'editor'],
        'media.manage' => ['admin', 'editor'],
        'modules.manage' => ['admin'],
        'users.manage' => ['admin'],
        'tools.manage' => ['admin'],
        'settings.manage' => ['admin'],
    ];

    /** @var array<string, array<string,list<string>>> */
    private static array $modulePolicies = [];

    public static function isKnown(string $capability): bool
    {
        $capability = strtolower(trim($capability));
        if (isset(self::CORE[$capability])) {
            return true;
        }
        return self::moduleRoles($capability) !== null;
    }

    /**
     * Plugin routes may use a Core capability or their own declared capability.
     * Never borrow another plugin's grant as an authorization shortcut.
     */
    public static function isAllowedForModule(string $slug, string $capability): bool
    {
        $capability = strtolower(trim($capability));
        $slug = strtolower(trim($slug));
        return isset(self::CORE[$capability])
            || (str_starts_with($capability, $slug . '.') && self::isKnown($capability));
    }

    public static function allows(string $role, string $capability): bool
    {
        $role = strtolower(trim($role));
        $capability = strtolower(trim($capability));
        if ($role === '' || $capability === '') {
            return false;
        }
        if (isset(self::CORE[$capability])) {
            return in_array($role, self::CORE[$capability], true);
        }
        $roles = self::moduleRoles($capability);
        return $roles !== null && in_array($role, $roles, true);
    }

    /**
     * @return list<string>|null Null means the capability isn't registered.
     */
    private static function moduleRoles(string $capability): ?array
    {
        if (!preg_match('/^([a-z0-9][a-z0-9_-]{0,99})\.([a-z][a-z0-9_.-]{0,99})$/D', $capability, $matches)) {
            return null;
        }
        $slug = $matches[1];
        if (!array_key_exists($slug, self::$modulePolicies)) {
            // ROOTPATH is defined during the normal bootstrap; never load PHP from a plugin here.
            if (!defined('ROOTPATH')) {
                return null;
            }
            $directory = ROOTPATH . '/modules/' . $slug;
            try {
                $manifest = ModuleManifestValidator::load($directory);
                self::$modulePolicies[$slug] = $manifest['capabilities'] ?? [];
            } catch (Throwable $error) {
                // Uninstalled or invalid plugins cannot grant permissions.
                self::$modulePolicies[$slug] = [];
            }
        }
        return self::$modulePolicies[$slug][$capability] ?? null;
    }
}
