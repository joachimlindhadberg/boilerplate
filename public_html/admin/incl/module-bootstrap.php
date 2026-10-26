<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/incl/init.php';
require_once __DIR__ . '/functions.php';

header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$auth = new Auth();

if (!$auth->check()) {
    admin_redirect('/admin');
}

if (!$auth->canAccessAdmin()) {
    http_response_code(403);
    admin_render('forbidden', ['user' => $auth->user()]);
    exit;
}

$requiredCapability = defined('ADMIN_REQUIRED_CAPABILITY')
    ? (string)ADMIN_REQUIRED_CAPABILITY
    : 'content.manage';

if (!$auth->can($requiredCapability)) {
    http_response_code(403);
    admin_render('forbidden', ['user' => $auth->user()]);
    exit;
}

if ($auth->mustChangePassword()) {
    admin_redirect('/admin/index.php?mode=change-password');
}
