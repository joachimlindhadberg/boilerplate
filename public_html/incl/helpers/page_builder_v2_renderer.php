<?php
/**
 * Page Builder V2 public markup. Data/validation remain in PageBlocks and
 * all public presentation lives exclusively in the frontend Theme.
 */

/** Do not mint temporary IDs during rendering: anchors must be stored in block_data. */
function page_builder_section_id_attribute(array $data): string
{
    $id = $data['section_id'] ?? null;
    return is_string($id) && preg_match('/^section-[0-9]{6}$/D', $id) === 1
        ? ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"'
        : '';
}

/** URLs imported outside the editor must still be safe at render time. */
function page_builder_public_url(mixed $value): string
{
    $url = trim((string)$value);
    if ($url === '' || str_contains($url, '\\') || preg_match('/[\\x00-\\x20\\x7F]/', $url)) return '';
    if (str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_contains($url, "\\")) return $url;
    if (str_starts_with($url, '#')) return $url;
    return preg_match('~^https?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

function render_page_builder_v2_block(array $block): void
{
    $type = (string)($block['block_type'] ?? '');
    $data = is_array($block['data'] ?? null) ? $block['data'] : [];
    $defaultTheme = $type === 'hero' ? 'dark' : 'light';
    $theme = in_array(($data['theme'] ?? $defaultTheme), ['light', 'soft', 'dark'], true) ? ($data['theme'] ?? $defaultTheme) : $defaultTheme;
    $containerWidth = PageBlocks::normalContainerWidth((string)($data['container_width'] ?? 'standard'));
    $e = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $columns = $type === 'grid' ? (int)($data['column_count'] ?? 2) : 1;
    $componentHtml = null;
    if ($type === 'component') {
        $componentHtml = page_builder_component_markup((string)($data['component'] ?? ''), $data);
        if ($componentHtml === '' && ($data['kicker'] ?? '') === '' && ($data['title'] ?? '') === '') return;
    }

    // Exact public structure: section > container > site-grid > site-grid--item.
    // The semantic data-* attributes allow the Theme to keep existing visual treatments.
    echo '<section' . page_builder_section_id_attribute($data) . ' class="block-section" data-theme="' . $e($theme) . '" data-content="' . $e($type) . '" data-container-width="' . $e($containerWidth) . '">';
    echo '<div class="container"><div class="site-grid site-grid--' . $columns . '"';
    if ($type === 'grid') {
        $gap = (string)($data['gap'] ?? 'normal');
        if (!isset(PageBlocks::gridGaps()[$gap])) $gap = 'normal';
        echo ' data-gap="' . $e($gap) . '"';
    }
    echo '>';

    if ($type === 'grid') {
        $items = is_array($data['columns'] ?? null) ? array_values($data['columns']) : [];
        for ($i = 0; $i < $columns; $i++) {
            echo '<div class="site-grid--item">';
            $children = is_array($items[$i]['blocks'] ?? null) ? $items[$i]['blocks'] : [];
            // Malformed imported content is still rendered safely, never dropped.
            foreach ($children as $child) {
                if (!is_array($child) || ($child['status'] ?? 'active') !== 'active') continue;
                render_page_builder_grid_child($child, $theme);
            }
            echo '</div>';
        }
    } else {
        echo '<div class="site-grid--item">';
        render_page_builder_v2_content($type, $data, $theme, $componentHtml);
        echo '</div>';
    }

    echo '</div></div></section>';
}

/** Renders content, never a second section, container or top-level layout row. */
function render_page_builder_v2_content(string $type, array $data, string $theme, ?string $componentHtml = null): void
{
    $e = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $heading = static function (array $data, bool $hero = false) use ($e): void {
        $prefix = $hero ? 'hero' : 'section';
        if (($data['kicker'] ?? '') !== '') echo '<p class="' . $prefix . '__kicker">' . $e((string)$data['kicker']) . '</p>';
        if (($data['title'] ?? '') !== '') {
            $tag = $hero ? 'h1' : 'h2';
            echo '<' . $tag . ' class="' . $prefix . '__title">' . $e((string)$data['title']) . '</' . $tag . '>';
        }
    };

    if ($type === 'hero') {
        $media = page_builder_media_asset($data);
        if ($media['path'] !== '') {
            echo '<div class="hero__background"><img src="' . $e($media['path']) . '" alt="' . $e($media['alt']) . '"' . page_builder_image_dimensions($media) . page_builder_responsive_attributes($media) . '></div>';
            echo '<div class="hero__overlay" aria-hidden="true"></div>';
        }
        $heading($data, true);
        if (($data['lead'] ?? '') !== '') echo '<p class="hero__lead">' . $e((string)$data['lead']) . '</p>';
        $pLabel = (string)($data['primary_label'] ?? '');
        $sLabel = (string)($data['secondary_label'] ?? '');
        $pUrl = page_builder_public_url($data['primary_url'] ?? '');
        $sUrl = page_builder_public_url($data['secondary_url'] ?? '');
        if (($pLabel !== '' && $pUrl !== '') || ($sLabel !== '' && $sUrl !== '')) {
            echo '<div class="hero__actions">';
            if ($pLabel !== '' && $pUrl !== '') echo '<a class="button button--light" href="' . $e($pUrl) . '">' . $e($pLabel) . '</a>';
            if ($sLabel !== '' && $sUrl !== '') echo '<a class="text-link" href="' . $e($sUrl) . '">' . $e($sLabel) . ' <span aria-hidden="true">→</span></a>';
            echo '</div>';
        }
        return;
    }

    if ($type === 'intro') {
        echo '<div class="grid grid--cols-2 grid--gap-large">';
        echo '<div class="grid__column section__header">';
        $heading($data);
        echo '</div><div class="grid__column rich-text">' . page_builder_paragraphs((string)($data['body'] ?? '')) . '</div></div>';
        return;
    }

    if ($type === 'text') {
        $split = ($data['layout'] ?? 'split') !== 'stacked';
        if ($split) echo '<div class="grid grid--cols-2 grid--gap-large"><div class="grid__column section__header">';
        elseif (($data['kicker'] ?? '') !== '' || ($data['title'] ?? '') !== '') echo '<div class="section__header">';
        $heading($data);
        if ($split) echo '</div><div class="grid__column rich-text">';
        elseif (($data['kicker'] ?? '') !== '' || ($data['title'] ?? '') !== '') echo '</div><div class="rich-text">';
        else echo '<div class="rich-text">';
        echo page_builder_paragraphs((string)($data['body'] ?? '')) . '</div>';
        if ($split) echo '</div>';
        return;
    }

    if ($type === 'text_image') {
        $side = ($data['image_side'] ?? 'right') === 'left' ? 'left' : 'right';
        $media = page_builder_media_asset($data);
        echo '<div class="grid grid--cols-2 grid--gap-large media-block media-block--image-' . $side . '">';
        if ($media['path'] !== '') echo '<figure class="grid__column media-block__media"><img src="' . $e($media['path']) . '" alt="' . $e($media['alt']) . '" loading="lazy" decoding="async"' . page_builder_image_dimensions($media) . page_builder_responsive_attributes($media) . '></figure>';
        else echo '<div class="grid__column media-block__media media-block__media--empty" aria-hidden="true"></div>';
        echo '<div class="grid__column media-block__content">';
        $heading($data);
        echo '<div class="rich-text">' . page_builder_paragraphs((string)($data['body'] ?? '')) . '</div></div></div>';
        return;
    }

    if ($type === 'cta') {
        echo '<div class="cta"><div class="cta__content">';
        $heading($data);
        if (($data['body'] ?? '') !== '') echo '<div class="cta__body">' . page_builder_paragraphs((string)$data['body']) . '</div>';
        echo '</div>';
        $url = page_builder_public_url($data['button_url'] ?? '');
        if (($data['button_label'] ?? '') !== '' && $url !== '') {
            echo '<a class="' . ($theme === 'dark' ? 'button button--light' : 'button') . '" href="' . $e($url) . '">' . $e((string)$data['button_label']) . '</a>';
        }
        echo '</div>';
        return;
    }

    if ($type === 'component') {
        $component = (string)($data['component'] ?? '');
        if (($data['kicker'] ?? '') !== '' || ($data['title'] ?? '') !== '') {
            echo '<div class="section__header">';
            $heading($data);
            echo '</div>';
        }
        echo $componentHtml ?? page_builder_component_markup($component, $data);
    }
}
