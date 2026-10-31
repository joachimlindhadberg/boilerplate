<?php

declare(strict_types=1);

/**
 * F3d.2b: cooperative CLI writer admission through the EXISTING F1c lock.
 * Hold the returned object for the entire CLI job, including cleanup.
 * This does NOT block external SQL writers or third-party processes that do
 * not opt in. The caller must fail closed if acquisition is unsuccessful.
 */
final class UpdateCliWriterAdmission
{
    public static function acquire(string $root): UpdateMaintenanceGate
    {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('CLI writer admission is CLI-only.');
        }
        require_once __DIR__ . '/UpdateMaintenanceGate.php';
        $gate = new UpdateMaintenanceGate($root);
        if (!$gate->acquireReader()) {
            throw new RuntimeException('Maintenance admission busy.');
        }
        return $gate;
    }
}
