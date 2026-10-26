<?php

declare(strict_types=1);
/** z18: Users detail-only Core delete dialog; no changes to server deletion rules. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
require_once $root . '/core/classes/AdminEscaper.php';
require_once $root . '/core/classes/AdminDeleteDialog.php';
$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    if ($ok) { ++$pass; } else { ++$fail; }
};

// Synthetic scaffolding only: render the real view and Core layout without a DB.
function t(string $key, array $params = []): string { return $key; }
function admin_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_csrf_token(): string { return 'synthetic-z18-csrf'; }
function admin_user_role_label(string $role): string { return $role; }
function admin_user_status_class(string $status): string { return $status; }
function admin_user_status_label(string $status): string { return $status; }
function admin_nav_items(array $badges = []): array { return []; }
function admin_nav_badges(): array { return []; }
function admin_asset(string $path): string { return $path; }
function admin_icon(string $name, string $class = ''): string { return ''; }
final class Translator {
    public static function locale(): string { return 'da'; }
    public static function defaultLocale(): string { return 'da'; }
}

$view = (string)file_get_contents($root . '/public_html/admin/views/users/view.php');
$list = (string)file_get_contents($root . '/public_html/admin/views/users/index.php');
$controller = (string)file_get_contents($root . '/public_html/admin/users.php');
$model = (string)file_get_contents($root . '/core/classes/Users.php');
$js = (string)file_get_contents($root . '/public_html/admin/assets/js/admin.js');
$check(substr_count($view, 'AdminDeleteDialog::render(') === 1, 'User detail delegates to exactly one Core delete renderer');
$check(!str_contains($view, '<dialog class="admin-modal') && !str_contains($view, 'admin-modal__surface'), 'No duplicated hand-written delete dialog in detail view');
$check(str_contains($view, 'if (!$deleteBlocked)') && str_contains($view, 'data-admin-open-delete="user-delete-dialog"'), 'Existing conditional dialog and trigger retained');
$check(str_contains($view, "'id' => 'user-delete-dialog'") && str_contains($view, "'titleId' => 'user-delete-title'"), 'Dialog and accessible title IDs preserved');
$check(str_contains($view, "'action' => '/admin/brugere?mode=delete&id=' . \$id"), 'Original Users delete POST endpoint retained');
$check(str_contains($view, "'csrfToken' => admin_csrf_token()"), 'Server-issued CSRF token passed to Core');
$check(str_contains($view, "'confirm_delete' => 'permanent'") && str_contains($view, "'id' => \$id"), 'Explicit confirmation and original user ID posted');
$check(str_contains($view, "'subject' => \$name") && str_contains($view, "'confirmLabel' => t('Slet bruger')"), 'Detail user name and destructive label retained');
$check(str_contains($list, 'AdminDeleteDialog::render('), 'Users list Core dialog unchanged');
$check(str_contains($js, "[data-admin-open-delete]") && str_contains($js, 'openDialog(dialog)'), 'Existing delegated modal-opening mechanism retained');
$check(str_contains($controller, "define('ADMIN_REQUIRED_CAPABILITY', 'users.manage')"), 'Users server capability unchanged');
$check(str_contains($controller, "if (\$mode === 'view')") && str_contains($controller, "'deleteBlocked' => \$isSelf || \$isLastActiveAdmin"), 'Detail blocks self and last active admin server-side');
$check(str_contains($controller, "if (\$mode === 'delete')") && str_contains($controller, '$blocked = $isSelf || $isLastActiveAdmin'), 'Users server retains blocked target determination and confirmation view');
$check(str_contains($controller, "if (!admin_verify_csrf(\$_POST['csrf_token'] ?? null))"), 'Server still validates CSRF token');
$check(str_contains($controller, "if ((\$_POST['confirm_delete'] ?? '') !== 'permanent')"), 'Server still requires explicit permanent confirmation');
$check(str_contains($controller, '$userModel->deleteAdmin($id, $actorId)'), 'Actual delete remains in existing model');
$check(str_contains($model, 'public function deleteAdmin(int $id, int $actorId)'), 'Model deletion protection implementation still exists');

$render = static function (array $record, bool $blocked, int $actor = 5, string $reason = '') use ($root): string {
    $managedUser = $record;
    $actorId = $actor;
    $deleteBlocked = $blocked;
    $deleteReason = $reason;
    $notice = null;
    $activeNav = 'users';
    $user = [
        'id' => $actor,
        'firstname' => 'Synthetic',
        'lastname' => 'Administrator',
        'email' => 'synthetic@example.invalid',
        'role' => 'admin',
        'admin_language' => 'da',
    ];
    ob_start();
    try {
        require $root . '/public_html/admin/views/users/view.php';
        return (string)ob_get_clean();
    } catch (Throwable $error) {
        ob_end_clean();
        throw $error;
    }
};
$record = [
    'id' => 42, 'firstname' => '<img src=x onerror=alert(1)>',
    'lastname' => 'T & Co', 'email' => 'test@example.invalid',
    'role_name' => 'editor', 'status' => 'active',
];
$html = $render($record, false);
$check(substr_count($html, 'id="user-delete-dialog"') === 1 && substr_count($html, 'id="user-delete-title"') === 1, 'Eligible detail renders exactly one uniquely identified Core modal');
$check(str_contains($html, 'aria-labelledby="user-delete-title"'), 'Dialog title correctly associated for accessibility');
$check(str_contains($html, 'method="post" action="/admin/brugere?mode=delete&amp;id=42"'), 'Core renders POST action with correctly escaped query ampersand');
$check(str_contains($html, 'name="id" value="42"') && str_contains($html, 'name="confirm_delete" value="permanent"'), 'Actual Core form contains correct ID and explicit confirmation');
$check(str_contains($html, 'name="csrf_token" value="synthetic-z18-csrf"'), 'Actual Core form contains current CSRF token');
$check(substr_count($html, 'name="csrf_token"') >= 1, 'CSRF protection present (additional layout forms allowed)');
$check(str_contains($html, '&lt;img src=x onerror=alert(1)&gt;') && !str_contains($html, '<img src=x'), 'Untrusted user name is escaped in dialog and headings');
$check(str_contains($html, 'T &amp; Co') && !str_contains($html, 'T & Co'), 'Untrusted ampersands are escaped consistently');
$check(str_contains($html, '<strong>&lt;img src=x onerror=alert(1)&gt; T &amp; Co</strong>'), 'Core displays user name as inert confirmation subject');
$check(str_contains($html, 'data-modal-close') && str_contains($html, '>Annuller</button>'), 'Core cancel and close controls retained');
$check(str_contains($html, 'type="submit">Slet bruger</button>'), 'Core confirmation performs the only destructive submit');
$check(str_contains($html, 'data-admin-open-delete="user-delete-dialog"'), 'Detail trigger still opens original dialog ID');
$check(str_contains($html, 'id="admin-confirm-title"'), 'Global confirmation dialog coexists without nested forms');
$check($render($record, true, 5, 'Blocked') !== '' && !str_contains($render($record, true, 5, 'Blocked'), 'id="user-delete-dialog"'), 'Blocked user has no permanent-delete modal');
$blockedHtml = $render($record, true, 5, 'Blocked');
$check(str_contains($blockedHtml, 'data-admin-open-delete="user-delete-dialog" disabled'), 'Blocked user retains native disabled trigger');
$check(str_contains($blockedHtml, 'Blocked'), 'Blocked user retains server explanation');
$selfHtml = $render(['id' => 5, 'firstname' => 'Current', 'lastname' => 'Admin', 'status'=>'active', 'role_name'=>'admin'], true, 5, 'Self not removable');
$check(!str_contains($selfHtml, 'id="user-delete-dialog"') && str_contains($selfHtml, 'Self not removable'), 'Self-protection remains visible and inert');
$lastAdminHtml = $render(['id'=>42, 'firstname'=>'Last', 'lastname'=>'Admin', 'status'=>'active', 'role_name'=>'admin'], true, 5, 'Last active administrator cannot be deleted');
$check(!str_contains($lastAdminHtml, 'id="user-delete-dialog"') && str_contains($lastAdminHtml, 'Last active administrator cannot be deleted'), 'Last-admin protection remains visible and inert');
$check(str_contains($js, "const dialog = document.getElementById(button.dataset.adminOpenDelete || '');"), 'Native Core JS locates modal using unchanged button attribute');
$check(file_get_contents($root . '/public_html/admin/assets/scss/admin.scss') === file_get_contents($root . '/public_html/admin/assets/css/admin.css'), 'Core SCSS and compiled CSS remain byte-identical');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", (string)file_get_contents($root . '/public_html/incl/init.php'), $v) === 1 && version_compare($v[1], '2.4.4.75', '>='), 'z18 internal version minimum 2.4.4.75');

printf("Users Detail Core Delete Dialog V2.4.4z18: %d PASS / %d FAIL\n", $pass, $fail);
exit($fail ? 1 : 0);
