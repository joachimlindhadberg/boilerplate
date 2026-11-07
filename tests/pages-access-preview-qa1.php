<?php

declare(strict_types=1);

/** Read-only security and integration contract checks; no database writes. */
$root = dirname(__DIR__);
require_once $root . '/core/classes/PageAccess.php';
$pass = 0;
$fail = 0;
$assert = static function (bool $condition, string $label) use (&$pass, &$fail): void {
    if ($condition) { ++$pass; echo "PASS: $label\n"; }
    else { ++$fail; fwrite(STDERR, "FAIL: $label\n"); }
};
$source = static fn(string $rel): string => (string)file_get_contents($root . '/' . $rel);

$assert(!PageAccess::validNewPassword('12345678901'), 'Short passwords rejected');
$assert(PageAccess::validNewPassword('twelvechars!'), '12+ character passwords accepted');
$assert(!PageAccess::validNewPassword(str_repeat('x', 129)), 'Oversized passwords rejected');
$assert(!PageAccess::validNewPassword("12chars456789\n"), 'Control characters rejected');
$access = $source('core/classes/PageAccess.php');
$assert(str_contains($access, 'password_hash($newPassword, PASSWORD_DEFAULT)'), 'Password hashes are stored, never plaintext');
$assert(str_contains($access, 'password_verify($value, $hash)'), 'Password validation uses password_verify');
$assert(str_contains($access, 'hash_equals($_SESSION[self::CSRF_KEY], $token)'), 'Public gate has session CSRF validation');
$assert(str_contains($access, '$limiter->hit($scope, $identifier, 5, 900)'), 'Public gate uses atomic rate limiter');
$assert(str_contains($access, 'session_regenerate_id(true)'), 'Successful access rotates session ID');
$assert(str_contains($access, "'expires' => time() + self::GRANT_TTL"), 'Page access grants expire');
$assert(str_contains($access, 'hash(\'sha256\', $hash)'), 'Existing grants are invalidated when the page password changes');
$assert(str_contains($access, "header('X-Robots-Tag: noindex"), 'Restricted HTTP responses set noindex headers');
$assert(str_contains($access, "header('Cache-Control: private, no-store"), 'Restricted HTTP responses cannot be cached publicly');
$assert(!str_contains($access, '$_GET[\'access\']'), 'No URL query-string access tokens');
$assert(str_contains($access, "Auth();") && str_contains($access, "can('content.manage')"), 'Editor preview requires verified CMS authorization');
$assert(str_contains($source('public_html/index.php'), "if (PageAccess::guard(model('pages'))) exit;"), 'Access enforcement before frontend rendering');
$handler = $source('public_html/incl/helpers/page_handler.php');
$assert(str_contains($handler, 'PageAccess::canDisplay($page)'), 'Frontend fails closed before exposing content');
$assert(!str_contains($handler, "if (!is_logged_in())"), 'Old unrestricted login-only Protected gate removed');
$header = $source('public_html/incl/header.php');
$assert(str_contains($header, "['draft', 'protected']"), 'Restricted pages have noindex meta');
$pages = $source('public_html/admin/pages.php');
$assert(substr_count($pages, 'PageAccess::save(') === 2, 'Both create and edit save access in Pages transaction');
$assert(str_contains($pages, 'PageAccess::validNewPassword'), 'Admin validates password length before saving');
$form = $source('public_html/admin/views/pages/partials/form-fields.php');
$assert(str_contains($form, 'data-page-access-controls'), 'Protected password settings are contextual');
$assert(str_contains($form, 'autocomplete="new-password"'), 'Old password is not echoed in the form');
$assert(str_contains($form, 'page_access_clear'), 'Admin can revoke existing access');
$js = $source('public_html/admin/assets/js/admin-page-workflow.js');
$assert(str_contains($js, 'Pages Publishing Visibility QA1'), 'Approved publishing visibility logic retained');
$assert(str_contains($js, 'Pages Access & Preview QA1'), 'Pages access UI updates on status changes');
$assert(str_contains($js, 'input.disabled = !active'), 'Irrelevant Protected settings excluded from POST');
$migration = $source('database/updates/20261105_000_page_access.php');
$assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS page_access'), 'Explicit idempotent migration');
$assert(str_contains($migration, 'ON DELETE CASCADE'), 'Page deletion revokes password data');
$assert(str_contains($source('public_html/incl/helpers/system_endpoints.php'), 'allPublished()'), 'Sitemap includes only public Published pages');
$assert(str_contains($source('core/classes/Navigation.php'), "!== 'published'"), 'Navigation excludes Draft and Protected pages');
$catalog = json_decode($source('core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
foreach (['pages.access.gate_title','pages.access.gate_description','pages.access.password_label','pages.access.gate_blocked'] as $key) {
    $assert(isset($catalog[$key]) && count($catalog[$key]) === 5, 'Core translation for all languages: ' . $key);
}


// Protected-media scope must be explicit without adding Media Library settings.
$note = $catalog['pages.access.password_note'] ?? null;
$assert(is_array($note) && array_keys($note) === ['en', 'da', 'de', 'sv', 'nb'],
    'Protected-media notice is translated into all five CMS languages');
$controlStart = strpos($form, '<div data-page-access-controls');
$controlEnd = $controlStart === false ? false : strpos($form, 'data-page-schedule-field="publish"', $controlStart);
$assert($controlStart !== false && $controlEnd !== false
    && substr_count(substr($form, $controlStart, $controlEnd - $controlStart), "t('pages.access.password_note')") === 1,
    'Media disclosure stays inside contextual Protected controls');
foreach ([
    'en' => ['Images and files', 'direct URLs'],
    'da' => ['Billeder og filer', 'direkte URL'],
    'de' => ['Bilder und Dateien', 'direkten URLs'],
    'sv' => ['Bilder och filer', 'direkta URL'],
    'nb' => ['Bilder og filer', 'direkte URL'],
] as $locale => $parts) {
    $copy = is_array($note) ? (string)($note[$locale] ?? '') : '';
    $assert(str_contains($copy, $parts[0]) && str_contains($copy, $parts[1]),
        'Public media URLs explicitly disclosed: ' . $locale);
}

printf("Pages Access & Preview QA1: %d PASS / %d FAIL (read-only)\n", $pass, $fail);
exit($fail ? 1 : 0);
