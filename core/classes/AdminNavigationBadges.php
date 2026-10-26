<?php

declare(strict_types=1);

/** Global menu badge policy. Core owns authorization, aggregation and rendering. */
final class AdminNavigationBadges
{
    /** @return array<string,int> Only positive, authorized badge counts. */
    public static function collect(ModuleManager $manager, string $role): array
    {
        $badges = [];

        // Reuse ModuleManager's manifest/installed-version comparison, never
        // claim a remote update or count broken/incompatible module files.
        if (Auth::roleCan($role, 'modules.manage')) {
            try {
                $updates = 0;
                foreach ($manager->allModules() as $module) {
                    if (!empty($module['valid']) && !empty($module['available'])
                        && !empty($module['compatible']) && !empty($module['update_available'])) {
                        $updates++;
                    }
                }
                if ($updates > 0) {
                    $badges['modules'] = $updates;
                }
            } catch (Throwable $e) {
                error_log('Core navigation module update badge unavailable: ' . $e->getMessage());
            }
        }

        // Only active modules registered in the real Core menu may supply a
        // counter, and only for menu keys this role is authorized to access.
        try {
            $authorized = [];
            foreach ($manager->adminMenuItems() as $item) {
                $key = (string)($item['key'] ?? '');
                $capability = (string)($item['capability'] ?? '');
                if ($key !== '' && $capability !== '' && Auth::roleCan($role, $capability)) {
                    $authorized[$key] = true;
                }
            }
            if ($authorized) {
                foreach ($manager->adminNavigationBadgeCounts(array_keys($authorized)) as $key => $value) {
                    if (isset($authorized[$key]) && is_int($value) && $value > 0) {
                        $badges[$key] = $value;
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('Core navigation module badge unavailable: ' . $e->getMessage());
        }
        return $badges;
    }

    /** Safe Core-owned badge markup. A zero count never renders a badge. */
    public static function render(int $count, string $description): string
    {
        if ($count <= 0) {
            return '';
        }
        $display = $count > 99 ? '99+' : (string)$count;
        $label = (string)$count . ' ' . trim($description);
        return '<span class="admin-nav__badge" data-admin-nav-count aria-label="'
            . AdminEscaper::text($label) . '">' . $display . '</span>';
    }
}
