<?php
/** QA1: source/invariant checks for contextual Page Builder section insertion.
 * Read-only; does not test a browser, HTTP or database persistence.
 */
declare(strict_types=1);
$root = dirname(__DIR__);
$php = file_get_contents($root . '/public_html/admin/incl/page-builder-functions.php');
$js = file_get_contents($root . '/public_html/admin/assets/js/admin.js');
$scss = file_get_contents($root . '/public_html/admin/assets/scss/admin.scss');
$css = file_get_contents($root . '/public_html/admin/assets/css/admin.css');
$view = file_get_contents($root . '/public_html/admin/views/pages/partials/form-fields.php');
$locale = json_decode((string)file_get_contents($root . '/core/lang/translations.json'), true);
$ok = 0; $fail = 0;
$check = function (bool $test, string $label) use (&$ok, &$fail): void {
    if ($test) { ++$ok; echo "PASS: {$label}\n"; }
    else { ++$fail; echo "FAIL: {$label}\n"; }
};
$check(str_contains($php, 'data-builder-insert-after'), 'Insert control rendered for persisted and template sections');
$check(str_contains($php, 'admin_icon(\'plus\'') && str_contains($php, 'type="button" class="admin-page-row__insert-after"'), 'Core plus icon and non-submitting button');
$check(str_contains($php, 'page_builder_v2.insert_after') && str_contains($php, 'page_builder_v2.insert_here'), 'Accessible translated button labels');
$check(str_contains($view, 'data-builder-add') && str_contains($view, 'data-page-layout-dialog'), 'Existing bottom Add Content and modal preserved');
$check(str_contains($js, 'const showLayoutChoices = (trigger, afterBlock = null) =>'), 'Single chooser handles append and contextual insert');
$check(str_contains($js, "builderForm.addEventListener('click', (event) => {\n      const insert = event.target.closest('[data-builder-insert-after]');"), 'Delegated click supports newly inserted rows');
$check(str_contains($js, 'const anchor = afterBlock?.parentElement === list ? afterBlock : null;'), 'Insertion anchor checked against current builder list');
$check(str_contains($js, "if (anchor) anchor.insertAdjacentHTML('afterend', html);") && str_contains($js, "else list.insertAdjacentHTML('beforeend', html);"), 'Insert after selected section or append normally');
$check(str_contains($js, 'const added = anchor ? anchor.nextElementSibling : blocks().at(-1);'), 'Focus/summary targets the inserted row');
$check(str_contains($js, 'newGalleryDefaults(added);') && str_contains($js, 'reindex();'), 'Existing section initialization and reindex retained');
$check(str_contains($js, 'if (blocks().length >= 60)'), 'Existing 60-section limit remains enforced');
$check(str_contains($js, 'const anchor = insertAfterBlock;') && str_contains($js, 'addBlock(type, anchor);'), 'Chooser preserves insertion position for all selections');
$check(str_contains($js, 'const templates = {') && str_contains($js, "'16': 'grid-6'"), 'All six standard column layouts preserved');
$check(str_contains($js, "layoutDialog?.addEventListener('close', () => {") && str_contains($js, 'insertAfterBlock = null;') && str_contains($js, 'layoutReturnFocus.focus({ preventScroll: true });'), 'Cancel and Escape clear insertion state and restore focus');
$check(str_contains($js, "const handle = event.target.closest('[data-builder-drag]');") && str_contains($php, 'data-builder-drag draggable="true"') && str_contains($php, 'data-builder-block data-block-type'), 'Grip-only drag mechanism preserved');
$check(str_contains($js, "list.insertBefore(draggedBlock, next);") && str_contains($js, "list.appendChild(draggedBlock);"), 'Original drag-sort targets preserved');
$check(str_contains($scss, ':last-child [data-builder-insert-after] { display:none; }'), 'No duplicate insertion control after final row');
$check(str_contains($scss, ':focus-within .admin-page-row__insert-after') && str_contains($scss, '@media (hover:none)'), 'Keyboard and touch controls remain available');
$check(str_contains($scss, 'opacity:0; pointer-events:none;') && str_contains($scss, '@media (prefers-reduced-motion:reduce)'), 'Minimal presentation and reduced motion supported');
$check($scss === $css, 'Admin SCSS and compiled CSS byte-identical');
$check(is_array($locale) && isset($locale['page_builder_v2.insert_after'], $locale['page_builder_v2.insert_here']) &&
    count(array_intersect(['da','en','de','sv','nb'], array_keys($locale['page_builder_v2.insert_after']))) === 5 &&
    count(array_intersect(['da','en','de','sv','nb'], array_keys($locale['page_builder_v2.insert_here']))) === 5,
    'Both new labels translated in five locales');
$check(str_contains($js, "const blocks = () => list ? [...list.querySelectorAll(':scope > [data-builder-block]')]") &&
    str_contains($php, '<div class="admin-page-row__insert-wrap">'), 'Insert button remains inside row, not a drag-sort sibling');
$check(str_contains($js, 'list.replaceChildren(...incoming);') && str_contains($js, 'list.insertBefore(next, block);'), 'Template replacement and keyboard sort retain existing paths');
$check(!str_contains($js, 'fetch(`/admin/page-builder/insert') && !str_contains($php, 'formaction='), 'No new API endpoint or save behavior');
echo "RESULT: {$ok} PASS / {$fail} FAIL (read-only source QA; no HTTP, browser or MySQL)\n";
exit($fail === 0 ? 0 : 1);
