<?php
/** V2.4.4z20k – read-only Navigation Editor V2 source and real PHP markup contracts. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
$pass=0; $fail=0;
$check = static function(bool $ok, string $label) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
    $ok ? ++$pass : ++$fail;
};
$get = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$view=$get('public_html/admin/views/navigation/partials/context.php');
$editor=$get('public_html/admin/views/navigation/edit.php');
$js=$get('public_html/admin/assets/js/admin.js');
$css=$get('public_html/admin/assets/css/admin.css');
$classes=$get('core/classes/AdminIcons.php');
$server=$get('core/classes/Navigation.php');
$translation=json_decode($get('core/lang/translations.json'),true,512,JSON_THROW_ON_ERROR);
$check(str_contains($view, "admin_icon_action_button('chevron-down'"), 'Core chevron edit toggle');
$check(str_contains($view, "admin_icon_action_button('delete'"), 'Core delete icon action');
$check(str_contains($view, "'data-nav-remove' => ''"), 'Delete action preserves existing handler');
$check(str_contains($view, "'aria-expanded' => 'false'"), 'Initially collapsed aria-expanded');
$check(str_contains($view, "'aria-controls' => 'nav-fields-' . \$id"), 'Chevron references its own form panel');
$check(str_contains($view, 'id="nav-fields-<?= admin_e($id); ?>"'), 'Form panel IDs are unique per item');
$check(str_contains($view, 'data-nav-fields hidden'), 'Editor fields collapsed at first paint');
$check(str_contains($view, 'data-nav-grip draggable="true"'), 'Dedicated accessible drag handle');
$check(str_contains($view, "admin_icon('grip'"), 'Core six-dot grip SVG');
$check(str_contains($classes, "'grip' =>"), 'Only Core owns grip SVG');
$check(!str_contains($view, 'data-nav-up') && !str_contains($view, 'data-nav-down'), 'Legacy up/down controls removed');
$check(!str_contains($view, 'data-nav-close'), 'Duplicate close button removed');
$check(substr_count($view, "data-nav-remove' =>")===1, 'Exactly one delete action per item');
$check(str_contains($editor, "t('nav.editor_drag_help')"), 'Navigation help is localized');
$check(str_contains($editor, 'data-nav-move-status') && str_contains($editor, 'aria-live="polite"'), 'Status announced for keyboard and screen readers');
$check(str_contains($js, "const group = (node)"), 'Whole root subtree moves together');
$check(str_contains($js, "const moveNode = (source, target, mode)"), 'Shared operation for pointer and keyboard');
$check(str_contains($js, "mode === 'child' ? getId(target) : parentOf(target)"), 'Native parent selector is authoritative');
$check(str_contains($js, "!nodes().some(node => getId(node) === parent && parentOf(node) === '0')"), 'Parent must be root');
$check(str_contains($js, "if (moving.includes(end)) end = moving.at(-1)?.nextElementSibling;"), 'Adjacent destination cannot reference a detached node');
$check(str_contains($js, "if (moving.includes(target)) return false"), 'Dragging onto own subtree prohibited');
$check(str_contains($js, "if (hasChildren(dragSource))"), 'Root with children is not nested');
$check(str_contains($js, "event.dataTransfer.setDragImage(dragSource"), 'Drag preview shows whole card');
$check(str_contains($js, "pointerY < rect.top + rect.height / 2"), 'Vertical midpoint positioning');
$check(str_contains($js, "pointerX - dragStartX > 28"), 'Intentional horizontal drag for nesting');
$check(str_contains($js, "dropPlan.target.dataset.navDrop = dropPlan.mode"), 'Visible before/after/nest preview');
$check(str_contains($js, "clearDropTarget()"), 'Drop preview cleared after drag');
$check(str_contains($js, "event.altKey") && str_contains($js, "'ArrowRight', 'ArrowLeft'"), 'Keyboard move and level changes');
$check(str_contains($js, "if (moved) grip.focus()"), 'Keyboard focus retained after moving');
$check(str_contains($js, "editorFields.id = 'nav-fields-' + key"), 'Newly created IDs reindexed');
$check(str_contains($js, "node.querySelector('[data-nav-toggle]')?.setAttribute('aria-controls'"), 'Newly created chevron targets correct panel');
$check(str_contains($js, "source.querySelector('[data-nav-parent]').value = parent"), 'Existing backend POST parent name unchanged');
$check(str_contains($view, "?>[parent_id]\""), 'Existing parent field name retained');
$check(str_contains($view, "?>[deleted]\""), 'Soft deletion until saving retained');
$check(str_contains($js, "window.adminConfirm("), 'Shared Core confirm dialog retained');
$check(str_contains($editor, 'name="csrf_token"') && str_contains($editor, 'name="action" value="save_editor"'), 'Original editor POST and CSRF unchanged');
$check(str_contains($server, 'public function saveEditor(') && str_contains($server, '$this->db->beginTransaction()'), 'Atomic server save retained');
$check(str_contains($server, "Undermenuer skal høre til et aktivt menupunkt på hovedniveau."), 'Server enforces max 2 levels');
$check(str_contains($server, 'Require the complete original set') && str_contains($server, "parent_id=NULL WHERE menu_id=?"), 'Server integrity and parent detach remain');
$check(str_contains($css, '.admin-nav-editor__node[data-nav-drop="child"]'), 'Core visual nesting preview');
$check(str_contains($css, '.admin-nav-editor__node[data-nav-drop="before"]'), 'Core insertion preview');
$check(str_contains($css, '.admin-nav-editor__actions [data-nav-toggle][aria-expanded="true"]'), 'Core chevron rotates on open');
$check($css===$get('public_html/admin/assets/scss/admin.scss'), 'Core SCSS and compiled CSS byte-identical');
$check(str_contains($get('public_html/admin/views/partials/layout.php'), "'nav.editor_moved'"), 'Translation snapshot includes dynamic status');
foreach(['da','en','de','sv','nb'] as $lang) {
    foreach(['nav.editor_drag_help','nav.editor_drag_keyboard','nav.editor_moved'] as $key) {
        $check(trim((string)($translation[$key][$lang]??''))!=='',"Localized $key ($lang)");
    }
}
ob_start(); require __DIR__.'/fixtures/navigation-editor-v2-browser.php'; $html=(string)ob_get_clean();
$check(substr_count($html,'data-nav-item="')===5, 'Four existing items plus new-item template rendered');
$check(substr_count($html,'data-nav-toggle=""')===5, 'Every item renders exactly one Core chevron');
$check(substr_count($html,'data-nav-remove=""')===5, 'Every item renders exactly one Core delete');
$check(!str_contains($html,'data-nav-up') && !str_contains($html,'data-nav-down'), 'Real markup contains no arrow controls');
$check(str_contains($html,'data-parent="1"') && str_contains($html,'data-parent="0"'), 'Root and existing children both render');
$check(str_contains($html,'name="items[2][parent_id]"'), 'Stable parent POST input included');
$check(str_contains($html,'name="items[2][deleted]"'), 'Stable delete POST input included');
$check(str_contains($html,'aria-controls="nav-fields-2"') && str_contains($html,'id="nav-fields-2"'), 'Rendered label-to-panel association');
$check(str_contains($html,'data-nav-item="__KEY__"'), 'Existing new-item template preserved');
$check(str_contains($html,'value="1" selected'), 'Saved child parent selection preserved');
$version=$get('public_html/incl/init.php');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", $version, $matches) === 1 && version_compare($matches[1] ?? '0', '2.4.4.93', '>='), 'Release retains z20k compatibility');
echo "Navigation Editor V2 z20k: $pass PASS / $fail FAIL\n";
exit($fail ? 1 : 0);
