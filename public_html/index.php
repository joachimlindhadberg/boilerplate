<?php
require_once __DIR__ . '/incl/init.php';

// Best-effort shared-hosting fallback; a real cron is recommended for idle sites.
ScheduledContent::runOnTraffic();

// Gate restricted HTML before metadata, modules or theme output.
if (PageAccess::guard(model('pages'))) exit;

if (handle_system_endpoint()) exit;
if (handle_core_redirect()) exit;
if (handle_maintenance_mode()) exit;

// Active modules boot before normal frontend output so modules that need
// request/session preparation can do so without running after headers.
cms_module_manager()->bootActiveModules();

// Public HTML varies by authenticated cookie. Validate login before rendering,
// without starting a session for ordinary anonymous visitors.
FrontendAdminBar::prepareRequest($settings);

get_page('header');
get_page('content');
get_page('footer');
