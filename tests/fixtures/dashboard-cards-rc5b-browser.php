<?php
/** Synthetic HTML fixture (NO CMS bootstrap, NO database/network writes). */
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/classes/Db.php';
require_once dirname(__DIR__, 2) . '/core/classes/DashboardCards.php';
function t(string $key, array $replacements = []): string {
    static $translations = null;
    $translations ??= json_decode((string)file_get_contents(dirname(__DIR__, 2) . '/core/lang/translations.json'), true) ?: [];
    $value = (string)($translations[$key]['da'] ?? $key);
    foreach ($replacements as $name => $replacement) $value = str_replace('{' . $name . '}', (string)$replacement, $value);
    return $value;
}
function admin_e(mixed $text): string { return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_icon(string $name, string $class = ''): string {
    return '<svg class="' . admin_e($class) . '" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="6"/></svg>';
}
function admin_csrf_token(): string { return 'fixture-token-no-real-session'; }
$user = ['id'=>777,'firstname'=>'Test','welcome_login_marker'=>'abcdef0123456789abcdef0123456789'];
$dashboard = ['counts' => ['published'=>14, 'drafts'=>5, 'scheduled'=>3, 'media'=>24], 'recent_activity'=>[], 'activity_loaded'=>true, 'upcoming_schedules'=>[], 'schedules_loaded'=>true, 'overdue_items'=>[]];
$dashboardCards = ['available'=> [
    'published'=>['label'=>'dashboard.published_pages','url'=>'/admin/sider?status=published','icon'=>'file'],
    'drafts'=>['label'=>'dashboard.drafts','url'=>'/admin/sider?status=draft','icon'=>'file'],
    'scheduled'=>['label'=>'dashboard.scheduled','url'=>'/admin/scheduled-content?scope=upcoming','icon'=>'calendar'],
    'media'=>['label'=>'dashboard.media','url'=>'/admin/medier','icon'=>'image'],
    'pages_total'=>['label'=>'dashboard.cards_pages_total','url'=>'/admin/sider','icon'=>'file'],
    'forms'=>['label'=>'dashboard.cards_forms','url'=>'/admin/forms','icon'=>'file'],
    'submissions'=>['label'=>'dashboard.cards_submissions','url'=>'/admin/forms/submissions','icon'=>'inbox'],
    'galleries'=>['label'=>'dashboard.cards_galleries','url'=>'/admin/gallerier','icon'=>'image']
], 'selection'=>DashboardCards::DEFAULTS, 'counts'=> ['pages_total'=>33,'forms'=>7,'submissions'=>29,'galleries'=>8]];
$canManageContent = true;
$canManageMedia = true;
$canViewActivityLog = false;
$source = (string)file_get_contents(dirname(__DIR__, 2) . '/public_html/admin/views/dashboard.php');
$before = "require __DIR__ . '/partials/layout.php';";
if (!str_contains($source, $before)) throw new RuntimeException('Dashboard view contract changed');
$source = str_replace($before, 'echo $bodyHtml;', $source);
?><!doctype html><html lang="da"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/public_html/admin/assets/css/admin.css">
<script>window.cmsTranslate=(key)=>({"dashboard.cards_saving":"Gemmer dashboardkort…","dashboard.cards_saved":"Dashboardkortene er opdateret.","dashboard.cards_save_failed":"Kunne ikke gemme kortet. Prøv igen."})[key]||key;</script>
<script src="/public_html/admin/assets/js/admin.js" defer></script></head><body class="nf-admin">
<div class="admin-layout"><main id="admin-main" class="admin-main"><div class="admin-workspace">
<?php eval('?>' . $source); ?>
</div></main></div>
</body></html>
