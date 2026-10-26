<?php

declare(strict_types=1);
/** z19: Redirects uses a single Core-owned delete confirmation; no DB writes. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
require_once $root . '/core/classes/AdminEscaper.php';
require_once $root . '/core/classes/AdminDeleteDialog.php';
require_once $root . '/core/classes/AdminListUI.php';
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    $ok ? ++$pass : ++$fail;
};
function t(string $key, array $params = []): string { return $key; }
function admin_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_csrf_token(): string { return 'synthetic-z19-csrf'; }
function admin_nav_items(array $badges = []): array { return []; }
function admin_nav_badges(): array { return []; }
function admin_asset(string $path): string { return $path; }
function admin_icon(string $name, string $class = ''): string { return ''; }
function admin_user_role_label(string $role): string { return $role; }
function admin_redirects_url(array $changes = []): string { return '/admin/vaerktoejer/redirects' . ($changes ? '?' . http_build_query($changes) : ''); }
function admin_redirect_type_label(int $type): string { return (string)$type; }
function admin_render_pagination(int $page, int $pages, callable $url, string $label): void {}
final class Translator { public static function locale(): string { return 'da'; } public static function defaultLocale(): string { return 'da'; } }
$source = (string)file_get_contents($root . '/public_html/admin/views/tools/redirects.php');
$js = (string)file_get_contents($root . '/public_html/admin/assets/js/admin.js');
$controller = (string)file_get_contents($root . '/public_html/admin/tools.php');
$model = (string)file_get_contents($root . '/core/classes/Redirects.php');
$translations = json_decode((string)file_get_contents($root . '/core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
$check(substr_count($source, 'AdminDeleteDialog::render(') === 1, 'Redirect list consumes exactly one Core delete renderer');
$check(!str_contains($source, 'data-admin-confirm-form') && !str_contains($source, 'data-admin-confirm-title'), 'Old generic confirmation forms removed');
$check(str_contains($source, 'data-admin-open-delete="redirect-list-delete-dialog"') && str_contains($source, 'type="button"'), 'Delete is an inert modal trigger, not a table form submit');
$check(str_contains($source, 'data-redirect-delete-id') && str_contains($source, 'data-redirect-delete-source'), 'Each trigger passes its own row ID and source path');
$check(str_contains($source, "'id' => 'redirect-list-delete-dialog'") && str_contains($source, "'titleId' => 'redirect-list-delete-title'"), 'Stable Core modal and ARIA heading IDs');
$check(str_contains($source, "'action' => '/admin/vaerktoejer/redirects'"), 'Core form posts to existing Redirects endpoint');
$check(str_contains($source, "'csrfToken' => admin_csrf_token()"), 'Server-issued CSRF token passed to Core');
$check(str_contains($source, "'action' => 'delete_redirect'"), 'Original delete intent preserved as a hidden POST action');
$check(str_contains($source, "'id' => ['value' => '', 'attributes' => ['data-redirect-list-delete-id' => true]]"), 'Dynamic delete ID starts empty');
$check(str_contains($source, "'subjectAttributes' => ['data-redirect-list-delete-source' => true]"), 'Dynamic redirect path is inert text placeholder');
$check(str_contains($source, "'prefix' => t('redirects.delete_prompt')") && str_contains($source, "'suffix' => t('redirects.delete_suffix')"), 'Core confirmation uses localization keys');
$check(str_contains($source, "'warning' => t('Handlingen kan ikke fortrydes.')"), 'Existing permanent deletion warning preserved through translation');
$check(strpos($source, "AdminDeleteDialog::render(") > strpos($source, '</section>') && strpos($source, "AdminDeleteDialog::render(") > strpos($source, '</div>\n<?php // One Core'), 'Modal is outside dynamic live-search region and table');
$check(str_contains($js, "if (dialog.id === 'redirect-list-delete-dialog')") && str_contains($js, "button.dataset.redirectDeleteId || ''"), 'Delegated Core click handler processes dynamic Redirects rows');
$check(str_contains($js, "dialog.querySelector('[data-redirect-list-delete-id]')") && str_contains($js, "dialog.querySelector('[data-redirect-list-delete-source]')"), 'Core click handler targets exact hidden field and label');
$check(str_contains($js, "source.textContent = button.dataset.redirectDeleteSource || '';"), 'Untrusted source path never injected as HTML');
$check(str_contains($js, 'if (!/^[1-9][0-9]*$/.test(id)) return;'), 'Handler rejects malformed or missing redirect IDs');
$check(str_contains($controller, "define('ADMIN_REQUIRED_CAPABILITY', 'tools.manage')"), 'Redirects capability unchanged');
$check(str_contains($controller, "if (!admin_verify_csrf(\$_POST['csrf_token'] ?? null))"), 'Server still validates CSRF');
$check(str_contains($controller, "elseif (\$action === 'delete_redirect')") && str_contains($controller, '\$redirects->delete(\$deleteId)') === false && str_contains($controller, '$redirects->delete($deleteId)'), 'Existing server route delegates to original deletion model');
$check(str_contains($model, 'public function delete(int $id): bool') && str_contains($model, 'DELETE FROM redirects WHERE id = ?'), 'Original database deletion implementation unchanged');
foreach (['redirects.delete', 'redirects.delete_prompt', 'redirects.delete_suffix'] as $key) {
    foreach (['da', 'en', 'de', 'sv', 'nb'] as $locale) {
        $check(is_string($translations[$key][$locale] ?? null) && trim($translations[$key][$locale]) !== '', $key . ' translated: ' . $locale);
    }
}
$render = static function (array $items) use ($root): string {
    $redirectsData = ['items' => $items, 'search' => '', 'status' => '', 'page' => 1, 'pages' => 1, 'total' => count($items), 'from' => count($items) ? 1 : 0, 'to' => count($items)];
    $form = [];
    $notice = null; $errors = []; $editRecord = null;
    $user = ['id' => 1, 'firstname' => 'Test', 'lastname' => 'Admin', 'role' => 'admin', 'email' => 'test@example.invalid', 'admin_language' => 'da'];
    $activeNav = 'tools';
    ob_start();
    try { require $root . '/public_html/admin/views/tools/redirects.php'; return (string)ob_get_clean(); }
    catch (Throwable $e) { ob_end_clean(); throw $e; }
};
$mk = static fn(int $id, string $source): array => ['id'=>$id, 'source_path'=>$source, 'target_url'=>'/new-' . $id, 'redirect_type'=>301, 'is_active'=>1, 'is_automatic'=>0, 'hit_count'=>0, 'last_hit_at'=>null, 'page_title'=>''];
$html = $render([$mk(21, '/first'), $mk(42, '/<img src=x onerror=alert(1)>')]);
$check(substr_count($html, 'id="redirect-list-delete-dialog"') === 1 && substr_count($html, 'id="redirect-list-delete-title"') === 1, 'Two rows emit exactly one native Core delete modal');
$check(str_contains($html, 'aria-labelledby="redirect-list-delete-title"'), 'Modal is accessibly labelled');
$check(substr_count($html, 'data-admin-open-delete="redirect-list-delete-dialog"') === 2, 'Each table row opens the same Core modal');
$check(str_contains($html, 'data-redirect-delete-id="21"') && str_contains($html, 'data-redirect-delete-id="42"'), 'Each table action preserves its own redirect ID');
$check(str_contains($html, 'data-redirect-delete-source="/&lt;img src=x onerror=alert(1)&gt;"'), 'Untrusted redirect path is escaped as a data attribute');
$check(!str_contains($html, '<img src=x onerror'), 'Malicious redirect path cannot inject HTML');
$check(str_contains($html, 'method="post" action="/admin/vaerktoejer/redirects"'), 'Native dialog uses correct POST action');
$check(substr_count($html, 'name="action" value="delete_redirect"') === 1 && str_contains($html, 'name="id" value="" data-redirect-list-delete-id'), 'Core form submits delete intent and blank dynamic ID');
$check(str_contains($html, 'name="csrf_token" value="synthetic-z19-csrf"'), 'Core form includes actual issued CSRF token');
$check(str_contains($html, '<strong data-redirect-list-delete-source></strong>'), 'Core renders safe dynamic text placeholder');
$check(str_contains($html, '>Annuller</button>') && str_contains($html, 'data-modal-close') && str_contains($html, 'type="submit">redirects.delete</button>'), 'Native Core Cancel, Close and Delete controls preserved');
$check(!str_contains($html, 'data-admin-confirm-form'), 'Rendered delete workflow does not use generic confirmation');
$empty = $render([]);
$check(substr_count($empty, 'id="redirect-list-delete-dialog"') === 1 && !str_contains($empty, 'data-redirect-delete-id="'), 'Empty table renders inert shared modal without fabricated delete actions');
$check(file_get_contents($root . '/public_html/admin/assets/scss/admin.scss') === file_get_contents($root . '/public_html/admin/assets/css/admin.css'), 'Admin SCSS/CSS byte-identical');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", (string)file_get_contents($root . '/public_html/incl/init.php'), $v) === 1 && version_compare($v[1], '2.4.4.76', '>='), 'Internal CMS z19 version is 2.4.4.76');

printf("Redirects Core Delete Dialog V2.4.4z19: %d PASS / %d FAIL\n", $pass, $fail);
exit($fail ? 1 : 0);
