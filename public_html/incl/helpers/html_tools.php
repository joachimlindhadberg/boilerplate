<?php
/**
 * ======================================================
 * HTML TOOLS
 * ======================================================
 *
 * Ansvar:
 * - Byg HTML modifiers (id, class, attr)
 * - Byg komplette HTML attributes til tags
 */

 /**
  * MODIFIERS
  */
function add_id(string $id): array {
    return ['id' => $id];
}

function add_class(string ...$classes): array {
    return ['class' => $classes];
}

function add_attr(string $key, string $value): array {
    return ['attr' => [$key => $value]];
}

/**
 * BUILD HTML ATTRIBUTES
 */
function build_attributes(array $modifiers): string {
    $id = '';
    $classes = [];
    $attrs = [];

    foreach ($modifiers as $mod) {
        if (!is_array($mod)) continue;
        if (isset($mod['id'])) $id = $mod['id'];
        if (isset($mod['class'])) $classes = array_merge($classes, $mod['class']);
        if (isset($mod['attr'])) $attrs = array_merge($attrs, $mod['attr']);
    }

    $html = '';
    if ($id) $html .= ' id="' . htmlspecialchars($id) . '"';
    if ($classes) $html .= ' class="' . htmlspecialchars(implode(' ', $classes)) . '"';
    foreach ($attrs as $key => $value) {
        $html .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
    }

    return $html;
}
