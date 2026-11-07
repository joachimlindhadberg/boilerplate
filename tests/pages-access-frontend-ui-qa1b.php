<?php

declare(strict_types=1);

/** Read-only standalone regression test of protected-page frontend markup. */
// Buffer test output so simulated header() calls do not warn in CLI.
ob_start();
$root = dirname(__DIR__);
$pass = 0;
$fail = 0;
$assert = static function (bool $ok, string $description) use (&$pass, &$fail): void {
    if ($ok) { ++$pass; echo "PASS: {$description}\n"; }
    else { ++$fail; fwrite(STDERR, "FAIL: {$description}\n"); }
};

// Only local stubs: no database, real visitor sessions, or network calls.
final class Translator
{
    public static function locale(): string { return 'en'; }
    public static function get(string $key): string
    {
        return [
            'pages.access.gate_title' => 'Protected page',
            'pages.access.gate_description' => 'Enter the page password to continue.',
            'pages.access.gate_password' => 'Password',
            'pages.access.gate_button' => 'View page',
            'pages.access.gate_error' => 'The password could not be accepted.',
            'pages.access.gate_blocked' => 'Too many attempts. Try again later.',
        ][$key] ?? $key;
    }
}
function asset_url(string $path): string { return $path . '?v=test'; }
require_once $root . '/core/classes/PageAccess.php';

// Guard against regressions that accidentally touch authentication logic.
$src = (string)file_get_contents($root . '/core/classes/PageAccess.php');
$expectedSecuritySha = 'fbd0bbf00601c915f740b47690026d92e4f120ec2afe2482f990973f10b088a3';
$start = '    private static function renderGate(string $path, bool $error, bool $blocked): void';
$assert(str_contains($src, $start), 'Protected page render method retained');
$assert(hash('sha256', substr($src, 0, strpos($src, $start))) === $expectedSecuritySha,
    'All login, CSRF, rate-limit and access-grant logic is unchanged');

$_SESSION = ['boilerplate_page_access_csrf_v1' => 'token-123<&"'];
$method = new ReflectionMethod(PageAccess::class, 'renderGate');
$render = static function (bool $error, bool $blocked) use ($method): string {
    ob_start();
    $method->invoke(null, '/private&preview', $error, $blocked);
    return (string)ob_get_clean();
};
$normal = $render(false, false);
$invalid = $render(true, false);
$blocked = $render(false, true);

$assert(str_contains($normal, 'class="page-access-page"'), 'Frontend has a dedicated page scope');
$assert(str_contains($normal, '<main class="page-access"'), 'Single semantic frontend main region');
$assert(str_contains($normal, 'class="page-access__panel"'), 'Minimal access panel markup');
$assert(str_contains($normal, 'aria-labelledby="page-access-title"'), 'Panel labelled accessibly');
$assert(str_contains($normal, 'class="page-access__title" id="page-access-title"'), 'Correct h1 label');
$assert(str_contains($normal, '<label for="page-access-password">'), 'Password has explicit associated label');
$assert(str_contains($normal, 'class="page-access__form" method="post"'), 'POST access form retained');
$assert(str_contains($normal, 'name="_page_access_csrf"'), 'CSRF field retained');
$assert(str_contains($normal, 'token-123&lt;&amp;&quot;'), 'CSRF escaped at output boundary');
$assert(str_contains($normal, 'action="/private&amp;preview"'), 'Form action escaped at output boundary');
$assert(str_contains($normal, 'name="_page_access_password" required maxlength="128" autocomplete="off"'),
    'Password constraints and browser autocomplete retained');
$assert(str_contains($normal, 'class="button page-access__submit"'), 'Theme-owned button styling reused');
$assert(str_contains($normal, 'rel="stylesheet"') && str_contains($normal, '/assets/css/style.css?v=test'),
    'Frontend theme stylesheet loaded, never admin assets');
$assert(str_contains($normal, 'noindex,nofollow,noarchive'), 'Restricted page is noindex');
$assert(!str_contains($normal, 'page-access__notice'), 'No error notice on initial page');
$assert(str_contains($invalid, 'role="alert"') && str_contains($invalid, 'The password could not be accepted.'),
    'Incorrect password shown via accessible alert');
$assert(str_contains($invalid, 'aria-invalid="true" aria-describedby="page-access-notice"'),
    'Incorrect field links to accessible error');
$assert(str_contains($invalid, 'type="submit"') && !str_contains($invalid, 'type="submit" disabled'),
    'Incorrect password allows retry');
$assert(str_contains($blocked, 'Too many attempts. Try again later.'), 'Lockout message kept');
$assert(str_contains($blocked, 'type="submit" disabled'), 'Lockout submit safely disabled');
$assert(str_contains($blocked, 'aria-describedby="page-access-notice" disabled'),
    'Lockout input disabled and linked to status');
$assert(!str_contains($src, '<script') && !str_contains($normal, 'admin/assets'), 'No inline JS or admin UI');

$theme = (string)file_get_contents($root . '/public_html/assets/css/style.css');
$entry = (string)file_get_contents($root . '/public_html/assets/scss/style.scss');
$component = (string)file_get_contents($root . '/public_html/assets/scss/components/_components.page-access.scss');
$marker = '/* ===== components/components.page-access ===== */';
$assert(substr_count($entry, '@import "components/components.page-access";') === 1,
    'Theme SCSS imports access component exactly once');
$assert(substr_count($theme, $marker) === 1 && str_starts_with(explode($marker . "\n", $theme, 2)[1] ?? '', $component) && strpos($theme, $marker) < strpos($theme, '/* ===== components/components.gallery ===== */'),
    'Compiled CSS and SCSS access component are identical');
$assert(str_contains($component, 'var(--color-border') && str_contains($component, 'var(--color-surface'),
    'Theme design tokens are used');
$assert(str_contains($component, ':focus-visible'), 'Visible keyboard focus style');
$assert(str_contains($component, '@media (max-width: 480px)'), 'Narrow-screen layout supported');
$assert(str_contains($component, 'prefers-reduced-motion: reduce'), 'Reduced-motion preference supported');
$assert(!str_contains($component, '.admin-'), 'Frontend component contains no admin selectors');
$assert(!str_contains($normal, '<header class="site-header"'), 'Protected gate never renders site navigation');

printf("Pages Access Frontend UI QA1b: %d PASS / %d FAIL (read-only)\n", $pass, $fail);
ob_end_flush();
exit($fail ? 1 : 0);
