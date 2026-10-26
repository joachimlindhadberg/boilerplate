<?php

declare(strict_types=1);
/** z16: Media's permanent deletion uses Core-owned modal; metadata/upload flows remain distinct. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    $ok ? ++$pass : ++$fail;
};
function t(string $key): string { return $key; }
function admin_csrf_token(): string { return 'synthetic-z16-csrf'; }

$detail = (string) file_get_contents($root . '/public_html/admin/views/media/partials/detail-dialog.php');
$delete = (string) file_get_contents($root . '/public_html/admin/views/media/partials/delete-dialog.php');
$index = (string) file_get_contents($root . '/public_html/admin/views/media/index.php');
$js = (string) file_get_contents($root . '/public_html/admin/assets/js/admin.js');
$controller = (string) file_get_contents($root . '/public_html/admin/media.php');
$library = (string) file_get_contents($root . '/core/classes/MediaLibrary.php');
$lang = json_decode((string)file_get_contents($root . '/core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);

$check(substr_count($delete, 'AdminDeleteDialog::render(') === 1, 'Single Core delete renderer in Media');
$check(!str_contains($delete, '<dialog') && !str_contains($delete, 'admin-modal__surface'), 'No duplicate hand-written modal markup');
$check(str_contains($delete, "'id' => 'media-delete-dialog'") && str_contains($delete, "'titleId' => 'media-delete-title'"), 'Stable accessible dialog and heading IDs');
$check(str_contains($delete, "'action' => '/admin/medier?mode=delete'"), 'POST points to existing media delete endpoint');
$check(str_contains($delete, "'csrfToken' => admin_csrf_token()"), 'Core renderer obtains CSRF token from server');
$check(str_contains($delete, "'id' => ['value' => '', 'attributes' => ['data-media-delete-id' => true]]"), 'Selected media ID is passed using Core hidden field');
$check(str_contains($delete, "'subjectAttributes' => ['data-media-delete-name' => true]"), 'Media name uses Core-owned inert text placeholder');
$check(str_contains($delete, "'warning' => t('ui.media_delete_warning')"), 'Existing irreversible-deletion warning preserved');
$check(str_contains($delete, "'confirmLabel' => t('Slet medie')"), 'Delete confirmation has explicit destructive label');
$check(str_contains($detail, 'data-admin-open-delete="media-delete-dialog"'), 'Media detail Delete is a Core modal trigger');
$check(str_contains($detail, 'type="button" data-admin-open-delete='), 'Media detail Delete cannot submit its metadata form');
$check(!str_contains($detail, 'id="media-detail-delete-form"') && !str_contains($detail, 'data-admin-confirm-form'), 'Legacy generic confirmation/delete form removed');
$check(str_contains($detail, 'id="media-detail-update-form"') && str_contains($detail, 'form="media-detail-update-form"'), 'Metadata Save form remains independent');
$check(str_contains($detail, 'data-media-detail-dialog') && str_contains($detail, 'data-media-detail-id') && str_contains($detail, 'data-media-detail-heading'), 'Existing media selection hooks preserved');
$check(str_contains($index, "require __DIR__ . '/partials/detail-dialog.php';") && str_contains($index, "require __DIR__ . '/partials/delete-dialog.php';"), 'Media index includes both detail and Core delete partials');
$check(strpos($index, "/partials/detail-dialog.php'") < strpos($index, "/partials/delete-dialog.php'"), 'Core delete dialog is sibling after detail, not nested within another dialog');
$check(str_contains($js, "if (dialog.id === 'media-delete-dialog')"), 'Delegated Core handler recognizes Media delete dialog');
$check(str_contains($js, "const id = mediaDetailDialog?.querySelector('[data-media-detail-id]')?.value || '';"), 'Delete handler reads currently selected ID at click time');
$check(str_contains($js, "if (!/^[1-9][0-9]*$/.test(id)) return;"), 'Invalid and empty media IDs cannot open confirmation');
$check(str_contains($js, "dialog.querySelector('[data-media-delete-id]')") && str_contains($js, "idInput.value = id;"), 'Core form receives selected ID only after validation');
$check(str_contains($js, "dialog.querySelector('[data-media-delete-name]')") && str_contains($js, "name.textContent = selectedName.textContent || '';"), 'Name assigned using safe textContent, never innerHTML');
$check(str_contains($js, "document.addEventListener('click', (event) => {") && str_contains($js, "[data-admin-open-delete]"), 'Delegated listener works with dynamic Core live list');
$check(!str_contains($js, "mediaDetailDialog.querySelector('[data-media-delete-id]')"), 'Media detail no longer mutates a delete form inside the editor');
$check(str_contains($controller, "const ADMIN_REQUIRED_CAPABILITY = 'media.manage'"), 'Server-side media.manage permission unchanged');
$check(str_contains($controller, "if (!admin_verify_csrf(\$_POST['csrf_token'] ?? null))"), 'Server continues to verify CSRF for all media POST actions');
$check(str_contains($controller, "if (\$mode === 'delete')") && str_contains($controller, '\$mediaLibrary->delete(\$id)') === false && str_contains($controller, '$mediaLibrary->delete($id)'), 'Original server delete route delegates to MediaLibrary');
$check(str_contains($library, '$this->db->beginTransaction()') && str_contains($library, "if (\$usage['count'] > 0)"), 'MediaLibrary still checks in-use references in DB transaction');
$check(str_contains($library, 'DELETE FROM media WHERE id = ?') && str_contains($library, '$this->db->commit()'), 'Repository still owns actual deletion');
$check(str_contains($library, 'ImageVariants::deleteArtifacts($this->publicUrl($path))'), 'Filesystem cleanup remains inside existing library');
$check(str_contains($controller, "notice.media_in_use_one") && str_contains($controller, "notice.media_in_use_many"), 'Existing reference-in-use error messages unchanged');
$check(str_contains($controller, "if (\$mode === 'upload')") && str_contains($controller, "if (\$mode === 'update')"), 'Upload and metadata endpoints stay separate');

$partial = $root . '/public_html/admin/views/media/partials/delete-dialog.php';
ob_start(); require $partial; $html = (string)ob_get_clean();
$check(substr_count($html, '<dialog ') === 1 && substr_count($html, '</dialog>') === 1, 'Partial renders exactly one native Core dialog');
$check(str_contains($html, 'id="media-delete-dialog" aria-labelledby="media-delete-title"'), 'Core emits accessible dialog heading association');
$check(str_contains($html, 'method="post" action="/admin/medier?mode=delete"'), 'Core form uses original POST route');
$check(str_contains($html, 'name="csrf_token" value="synthetic-z16-csrf"'), 'CSRF token is rendered once');
$check(substr_count($html, 'name="csrf_token"') === 1, 'No duplicated CSRF field');
$check(str_contains($html, 'name="id" value="" data-media-delete-id'), 'Hidden dynamic ID is blank until selected');
$check(str_contains($html, '<strong data-media-delete-name></strong>'), 'Dynamic media name initially inert, not injected as HTML');
$check(str_contains($html, 'data-modal-close') && str_contains($html, 'type="submit"'), 'Core provides cancel, close and submit controls');
$check(!str_contains($html, 'data-admin-confirm-form') && !str_contains($html, 'onclick='), 'No second confirmation or inline onclick handlers');
$check(substr_count($detail, '<form ') === 1 && substr_count($detail, '</form>') === 1, 'Detail editor contains only metadata form, no nested delete form');
$check(!str_contains($detail, 'form="media-detail-delete-form"'), 'Old delete submitter is removed from detail footer');
$check(str_contains($detail, 'data-media-detail-preview') && str_contains($detail, 'data-media-alt-field') && str_contains($detail, 'data-media-detail-description'), 'Media preview, alt text and description preserved');
foreach (['da', 'en', 'de', 'sv', 'nb'] as $locale) {
    $check(is_string($lang['ui.media_delete_prompt'][$locale] ?? null) && trim($lang['ui.media_delete_prompt'][$locale]) !== '', 'New media confirmation prompt localized for ' . $locale);
    $check(is_string($lang['ui.media_delete_warning'][$locale] ?? null), 'Existing deletion warning retained for ' . $locale);
}
$check(file_get_contents($root . '/public_html/admin/assets/scss/admin.scss') === file_get_contents($root . '/public_html/admin/assets/css/admin.css'), 'Core SCSS/CSS remain synchronized');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", (string)file_get_contents($root . '/public_html/incl/init.php'), $m)===1 && version_compare($m[1], '2.4.4.73', '>='), 'Internal CMS version at least 2.4.4.73');

printf("Media Core Delete Dialog V2.4.4z16: %d PASS / %d FAIL\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
