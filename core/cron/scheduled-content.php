<?php
/** Run every minute from hosting cron: php /absolute/path/core/cron/scheduled-content.php */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
define('ROOTPATH', dirname(__DIR__, 2));
require_once ROOTPATH . '/core/classes/Autoload.php';
// Parse CMS timezone without loading HTTP/bootstrap globals.
$settings = new Settings();
$timezone = (string)$settings->get('timezone', 'Europe/Copenhagen');
try { date_default_timezone_set($timezone); } catch (Throwable $e) { date_default_timezone_set('UTC'); }
try {
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
}
