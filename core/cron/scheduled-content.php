<?php
/** Run every minute from hosting cron: php /absolute/path/core/cron/scheduled-content.php */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
define('ROOTPATH', dirname(__DIR__, 2));
require_once ROOTPATH . '/core/classes/Autoload.php';
// CLI cron must obey the SAME admission lock as frontend and administration.
// Otherwise scheduled SQL writes can race with a future update/restore.
$gate = null;
try {
    $gate = new UpdateMaintenanceGate(ROOTPATH);
    if (!$gate->acquireReader()) {
        throw new RuntimeException('CMS maintenance is in progress; retry scheduled content later.');
    }
    // Parse CMS timezone without loading HTTP/bootstrap globals.
    $settings = new Settings();
    $timezone = (string)$settings->get('timezone', 'Europe/Copenhagen');
    try { date_default_timezone_set($timezone); } catch (Throwable $e) { date_default_timezone_set('UTC'); }
    $scheduler = new ScheduledContent();
    $total = 0;
    do {
        $count = $scheduler->runDue(100);
        $total += $count;
    } while ($count === 100 && $total < 1000);
    echo "Processed {$total} scheduled action(s).\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Scheduled content job failed: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    if ($gate instanceof UpdateMaintenanceGate) $gate->release();
}
