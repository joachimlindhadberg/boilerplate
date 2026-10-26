<?php

declare(strict_types=1);
/** RC5a narrow source regression test. No database or HTTP requests. */
$root = dirname(__DIR__);
$view = (string)file_get_contents($root . '/public_html/admin/views/dashboard.php');
$controller = (string)file_get_contents($root . '/public_html/admin/index.php');
$login = (string)file_get_contents($root . '/public_html/admin/views/login.php');
$rootRules = (string)file_get_contents($root . '/public_html/.htaccess');
$adminRules = (string)file_get_contents($root . '/public_html/admin/.htaccess');
$count=0;
$ok=static function (bool $value,string $message) use (&$count):void { if(!$value){fwrite(STDERR,"FAIL: $message\n");exit(1);}++$count;echo "PASS: $message\n";};
$ok(str_contains($view,'<form method="post" action="/admin/index.php?mode=dashboard-cards"'), 'Card form POST targets explicit PHP endpoint, not a physical directory');
$ok(!str_contains($view,'action="/admin?mode=dashboard-cards"'), 'Ambiguous directory URL cannot return');
$ok(!preg_match('~<form[^>]+method="post"[^>]+action="/admin(?:\?|")~i', $view), 'No POST form can accidentally use /admin directory URL');
$ok((bool)preg_match('~<form method="post" action="/admin/index\.php\?mode=dashboard-cards"(?:(?!</form>).)*name="csrf_token"~s', $view), 'Save POST retains CSRF field');
$ok((bool)preg_match('~<form method="post" action="/admin/index\.php\?mode=dashboard-cards"(?:(?!</form>).)*name="cards\[\]"~s', $view), 'Save POST retains four metric selections');
$ok(str_contains($controller,"\$moduleRoute === '' && \$mode === 'dashboard-cards'"), 'Direct admin index route still dispatches to dashboard action');
$ok(str_contains($controller,"!== 'POST'"), 'Controller still explicitly enforces POST method');
$ok(str_contains($controller,'DashboardCards::validate(') && str_contains($controller,'admin_verify_csrf('), 'Server-side validation and CSRF retained');
$ok(str_contains($controller,'$service->save((int)$auth->id(), $selection)'), 'Preferences still saved for authenticated user only');
$ok(str_contains($controller,"admin_redirect('/admin?cards=saved')"), 'Successful save still redirects via GET to dashboard');
$ok(str_contains($login,'action="/admin/index.php?mode=authenticate"'), 'Login already uses matching stable direct action endpoint');
$ok(str_contains($rootRules,'RewriteRule ^admin/?$ admin/index.php'), 'Existing public rewrite to admin index retained');
$ok(str_contains($adminRules, 'DirectoryIndex index.php'), 'Directory index preserved for Dashboard GET');
$ok(!str_contains($rootRules, 'R=301') || str_contains($rootRules,'RewriteRule ^admin/?$ admin/index.php [L]'), 'No newly introduced redirect for explicit PHP action');
$ok(substr_count($view,'action="/admin/index.php?mode=dashboard-cards"') === 1,'Exactly one Cards Save form exists');
echo "Dashboard Cards Save Routing RC5a: $count PASS / 0 FAIL\n";
