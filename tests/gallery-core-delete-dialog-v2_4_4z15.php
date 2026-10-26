<?php

declare(strict_types=1);
/** z15: Gallery's permanent delete modal delegates to Core; no server behavior changes. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
$pass = 0; $fail = 0;
$check = static function (bool $condition, string $label) use (&$pass, &$fail): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    $condition ? ++$pass : ++$fail;
};
function t(string $key): string { return str_starts_with($key, 'gallery:') ? substr($key, 8) : $key; }
function admin_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_csrf_token(): string { return 'synthetic-gallery-z15-token'; }
function admin_icon(string $icon, string $class = ''): string { return AdminIcons::render($icon, $class); }
function admin_icon_action_button(string $name, string $label, array $attributes = [], string $tone = 'neutral'): string { return AdminIcons::actionButton($name, $label, $attributes, $tone); }

$dialogSrc = (string)file_get_contents($root . '/modules/gallery/admin/views/partials/delete-dialog.php');
$editorSrc = (string)file_get_contents($root . '/modules/gallery/admin/views/partials/gallery-editor.php');
$jsSrc = (string)file_get_contents($root . '/modules/gallery/admin/views/partials/gallery-builder-script.php');
$ctrl = (string)file_get_contents($root . '/modules/gallery/admin/controllers/GalleryAdminController.php');
$repo = (string)file_get_contents($root . '/modules/gallery/GalleryRepository.php');
$module = (string)file_get_contents($root . '/modules/gallery/GalleryModule.php');

$check(substr_count($dialogSrc, 'AdminDeleteDialog::render(') === 1, 'Gallery has exactly one Core permanent-delete renderer');
$check(!str_contains($dialogSrc, '<dialog') && !str_contains($dialogSrc, 'admin-modal__surface'), 'Old hand-written delete modal markup is gone');
$check(str_contains($dialogSrc, "'dialogAttributes' => ['data-gallery-delete-dialog' => true]"), 'Original Gallery data attribute preserved');
$check(str_contains($dialogSrc, "'id' => 'gallery-delete-dialog'") && str_contains($dialogSrc, "'titleId' => 'gallery-delete-title'"), 'Stable accessible delete dialog ID and title');
$check(str_contains($dialogSrc, 'if ($isEdit && $galleryId > 0):'), 'New or unsaved galleries never have permanent-delete dialogs');
$check(str_contains($dialogSrc, "'fields' => ['id' => \$galleryId, 'gallery_action' => 'delete']"), 'Previously named submit button is now an explicit hidden delete action');
$check(str_contains($dialogSrc, "'action' => '/admin/gallerier?screen=edit&id=' . \$galleryId"), 'Delete targets the original controller route with raw query separators');
$check(str_contains($dialogSrc, "'csrfToken' => admin_csrf_token()"), 'Core renderer receives a server-issued CSRF token');
$check(str_contains($dialogSrc, "'subject' => (string)(\$galleryData['name'] ?? '')"), 'Core dialog identifies the gallery being deleted');
$check(str_contains($editorSrc, 'data-gallery-delete-open') && str_contains($editorSrc, "require __DIR__ . '/delete-dialog.php'"), 'Existing editor trigger and partial include stay in place');
$check(str_contains($jsSrc, "document.querySelector('[data-gallery-delete-dialog]')") && str_contains($jsSrc, "document.querySelector('[data-gallery-delete-open]')?.addEventListener('click',()=>openDialog(deleteDialog));"), 'Gallery JavaScript continues to open original modal hook');
$check(str_contains($ctrl, 'admin_verify_csrf(') && str_contains($ctrl, "\$_POST['gallery_action'] ?? 'save'") && str_contains($ctrl, "\$action === 'delete' && \$id > 0") && str_contains($ctrl, '$repository->deleteGallery($id)'), 'Existing POST/CSRF controller and action dispatch retained');
$check(str_contains($repo, '$usage = $this->pageBuilderUsage($id)') && str_contains($repo, "if (\$usage['count'] > 0)") && str_contains($repo, "'ok' => false"), 'Gallery repository still prevents deletion of referenced galleries');
$check(str_contains($repo, 'DELETE FROM module_galleries WHERE id = ?') && str_contains($repo, 'function pageBuilderUsage('), 'Only original repository executes data deletion');
$check(str_contains($module, "'capability' => 'content.manage'") && str_contains($module, "'methods' => ['GET', 'POST']"), 'Existing Gallery route capability and methods unchanged');

$partial = $root . '/modules/gallery/admin/views/partials/delete-dialog.php';
$render = static function (bool $editing, int $id, string $name) use ($partial): string {
    $isEdit = $editing; $galleryId = $id; $galleryData = ['name' => $name];
    ob_start();
    try { require $partial; return (string)ob_get_clean(); }
    catch (Throwable $e) { ob_end_clean(); throw $e; }
};
$hostile = '<img src=x onerror=alert(1)>';
$html = $render(true, 42, $hostile);
$check(substr_count($html, '<dialog ') === 1 && substr_count($html, '</dialog>') === 1, 'Existing gallery emits exactly one Core modal');
$check(str_contains($html, 'id="gallery-delete-dialog" aria-labelledby="gallery-delete-title" data-gallery-delete-dialog'), 'Core modal retains Gallery discovery and accessible label');
$check(str_contains($html, 'method="post" action="/admin/gallerier?screen=edit&amp;id=42"'), 'Gallery delete POST action is escaped exactly once');
$check(!str_contains($html, '&amp;amp;') && !str_contains($html, 'action="/admin/gallerier?screen=edit&id=42"'), 'Gallery delete form has no double encoding or raw ampersand');
$check(str_contains($html, 'name="id" value="42"') && str_contains($html, 'name="gallery_action" value="delete"'), 'Hidden Gallery delete intent is carried independently of Core submit button');
$check(str_contains($html, 'name="csrf_token" value="synthetic-gallery-z15-token"'), 'POST form includes CSRF token');
$check(str_contains($html, 'type="submit"') && str_contains($html, 'name="gallery_action" value="delete"'), 'Core default submit triggers delete, not Gallery save');
$check(str_contains($html, 'data-modal-close') && str_contains($html, 'Slet galleri'), 'Cancel/close and permanent-delete labels preserved');
$check(!str_contains($html, '<img ') && str_contains($html, '&lt;img src=x onerror=alert(1)&gt;'), 'User gallery name escaped as text, never injected as HTML');
$check(str_contains($html, 'Billederne i Medier bliver ikke slettet.') && str_contains($html, 'Galleriet kan kun slettes'), 'Original usage guidance and retained images warning still visible');
$check($render(false, 0, 'Nyt') === '' && $render(true, 0, 'Ikke gemt') === '', 'No delete dialog for new or unsaved galleries');
$check(str_contains($render(true, 7, 'Test & navn'), '<strong>Test &amp; navn</strong>'), 'Core escapes special characters in gallery subject');

// Render the real full editor, not only the dialog partial, to ensure no nested forms.
$galleryData = ['name' => $hostile, 'description' => '', 'status' => 'active'];
$items=[]; $errors=[]; $usage=['count'=>0,'page_ids'=>[]]; $isEdit=true; $galleryId=42;
ob_start(); require $root . '/modules/gallery/admin/views/partials/gallery-editor.php'; $full = (string)ob_get_clean();
$check(preg_match_all('/<dialog[^>]*data-gallery-delete-dialog(?:\s|>)/', $full) === 1 && preg_match_all('/<dialog[^>]*data-gallery-media-dialog(?:\s|>)/', $full) === 1, 'Editor still renders one delete modal and one media picker');
$check(substr_count($full, 'name="gallery_action" value="delete"') === 1 && str_contains($full, 'name="gallery_action" value="save"'), 'Save and Delete intent remain independent');
$check(strpos($full, '</form>') !== false && strpos($full, '</form>') < strpos($full, 'id="gallery-delete-dialog"'), 'Delete dialog remains outside the editor save form');
$check(str_contains($full, 'data-gallery-delete-open') && str_contains($full, 'data-gallery-items'), 'Builder and delete trigger remain intact');
$check(!str_contains($full, '<img src=x onerror=alert(1)>'), 'Editor with malicious title cannot produce executable markup');

$isEdit = false; $galleryId=0; $galleryData=['name'=>'', 'status'=>'active'];
ob_start(); require $root . '/modules/gallery/admin/views/partials/gallery-editor.php'; $new = (string)ob_get_clean();
$check(preg_match_all('/<dialog[^>]*data-gallery-delete-dialog(?:\s|>)/', $new) === 0 && !str_contains($new, '<button class="admin-button admin-button--danger-outline" type="button" data-gallery-delete-open'), 'Create screen has no permanent-delete UI');

$check(file_get_contents($root.'/public_html/admin/assets/scss/admin.scss') === file_get_contents($root.'/public_html/admin/assets/css/admin.css'), 'Core SCSS and compiled CSS remain byte-identical');
$version = (string)file_get_contents($root.'/public_html/incl/init.php');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", $version, $m)===1 && version_compare($m[1], '2.4.4.72', '>='), 'Retains minimum internal z15 version 2.4.4.72');

printf("Gallery Core Delete Dialog z15: %d PASS / %d FAIL\n", $pass, $fail);
exit($fail ? 1 : 0);
