<?php

declare(strict_types=1);

/**
 * Optional capability-scoped main-menu counters for active modules.
 * Keys must match this module's registered adminMenu() keys. Counts describe
 * pending work, not totals; a missing/zero count renders no badge.
 */
interface ModuleAdminNavigationBadgeProviderInterface
{
    /** @param array<int,string> $allowedKeys Only keys the current role may view.
     *  @return array<string,int> Non-negative badge counts keyed by menu ID.
     */
    public function adminNavigationBadges(array $allowedKeys): array;
}
