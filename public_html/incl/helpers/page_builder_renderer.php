<?php
/**
 * Boilerplate controlled page-builder renderer.
 *
 * All HTML/layout is owned by code. Block data contains content/config only.
 * Frontend class names describe the rendered UI, not the CMS/Page Builder.
 */
require_once __DIR__ . "/page_builder_v2_renderer.php";

function page_builder_media_asset(array $data): array
{
    $path = (string)($data['image_path'] ?? '');
    $alt = (string)($data['image_alt'] ?? '');
    $id = max(0, (int)($data['image_id'] ?? 0));
    $width = 0;
    $height = 0;
    $srcset = '';

    if ($id > 0) {
        static $library = null;
        static $cache = [];
        try {
            if (!array_key_exists($id, $cache)) {
                $library ??= new MediaLibrary();
                $cache[$id] = $library->find($id);
            }
            $record = $cache[$id];
            if (is_array($record) && ($record['media_kind'] ?? '') === 'image') {
                $path = (string)($record['public_url'] ?? $record['file_path'] ?? $path);
                $width = max(0, (int)($record['width'] ?? 0));
                $height = max(0, (int)($record['height'] ?? 0));
                $srcset = ImageVariants::srcset($path, $width, $height);
                if ($alt === '') {
                    $alt = (string)($record['alt_text'] ?? '');
                }
            }
        } catch (Throwable $e) {
            error_log('Page Builder media lookup: ' . $e->getMessage());
        }
    }

    // Stored legacy/imported URLs must be checked again at render time.
    // Media paths are local uploads; absolute HTTP(S) URLs from an existing media
    // repository are accepted for backwards compatibility.
    $path = page_builder_public_image_url($path);
    return ['path' => $path, 'alt' => $alt, 'width' => $width, 'height' => $height, 'srcset' => $srcset];
}

/** Public image sources only: local uploads (including legacy path) or HTTP(S). */
function page_builder_public_image_url(mixed $value): string
{
    $url = page_builder_public_url($value);
    if ($url === '' || str_starts_with($url, '#')) return '';
    if (str_starts_with($url, '/')) {
        $path = (string)(parse_url($url, PHP_URL_PATH) ?: '');
        return (str_starts_with($path, '/uploads/') || str_starts_with($path, '/assets/uploads/')) ? $url : '';
    }
    return $url;
}

/** Add dimensions only when valid metadata exists. The Theme still owns size. */
function page_builder_image_dimensions(array $media): string
{
    $width = (int)($media['width'] ?? 0);
    $height = (int)($media['height'] ?? 0);
    return $width > 0 && $height > 0 && $width <= 50000 && $height <= 50000
        ? ' width="' . $width . '" height="' . $height . '"'
        : '';
}

/** Browser-driven responsive sources, without Theme CSS/layout assumptions. */
function page_builder_responsive_attributes(array $media): string
{
    $srcset = (string)($media['srcset'] ?? '');
    return $srcset !== ''
        ? ' srcset="' . htmlspecialchars($srcset, ENT_QUOTES, 'UTF-8') . '" sizes="100vw"'
        : '';
}

/** One render/capture for a valid active public component; no empty wrappers. */
function page_builder_component_markup(string $component, array $data = []): string
{
    if ($component === '' || !array_key_exists($component, PageBlocks::componentOptions())) return '';
    ob_start();
    try {
        $modifiers = [];
        if (preg_match('/^gallery:gallery-[1-9][0-9]*$/D', $component)) {
            foreach (['gallery_mode','gallery_columns','gallery_fullscreen','gallery_autoplay','gallery_interval','gallery_layout','gallery_lightbox'] as $key) {
                if (array_key_exists($key, $data)) $modifiers[$key] = $data[$key];
            }
        }
        component_renderer($component, $modifiers);
        $html = (string)ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    return trim($html) === '' ? '' : $html;
}

function render_page_builder_blocks(int $pageId): void
{
    $blocks = model('page_blocks')->getForPage($pageId, true);
    foreach ($blocks as $block) {
        render_page_builder_block($block);
    }
}

/**
 * Page Builder V2 frontend contract. Existing single-content sections are 100% Rows;
 * grid rows explicitly marked v2 support 1–6 equal-width columns. Legacy grids stay on the old renderer
 * to avoid losing custom column counts, gaps and split widths.
 */
function render_page_builder_block(array $block): void
{
    $type = (string)($block['block_type'] ?? '');
    $data = is_array($block['data'] ?? null) ? $block['data'] : [];
    if (!array_key_exists($type, PageBlocks::definitions())) return;
    if ($type === 'grid' && (
        ($data['layout_version'] ?? '') !== 'v2'
        || !in_array((int)($data['column_count'] ?? 2), [1, 2, 3, 4, 5, 6], true)
        || ($data['preset'] ?? 'equal') !== 'equal'
    )) {
        render_page_builder_legacy_block($block);
        return;
    }
    render_page_builder_v2_block($block);
}

function render_page_builder_legacy_block(array $block): void
{
    $type = (string)($block['block_type'] ?? '');
    $data = is_array($block['data'] ?? null) ? $block['data'] : [];
    $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $themeValue = (string)($data['theme'] ?? 'light');
    $theme = in_array($themeValue, ['light', 'soft', 'dark'], true) ? $themeValue : 'light';
    $sectionClass = 'section theme-' . $theme;

    if ($type === 'hero') {
        $media = page_builder_media_asset($data);
        $image = $media['path'];
        echo '<section class="hero' . ($image !== '' ? ' hero--has-image' : '') . '">';
        if ($image !== '') {
            echo '<div class="hero__background"><img src="' . $e($image) . '" alt="' . $e($media['alt']) . '"' . page_builder_image_dimensions($media) . page_builder_responsive_attributes($media) . '></div><div class="hero__overlay" aria-hidden="true"></div>';
        }
        echo '<div class="container hero__content">';
        if (($data['kicker'] ?? '') !== '') echo '<p class="hero__kicker">' . $e((string)$data['kicker']) . '</p>';
        if (($data['title'] ?? '') !== '') echo '<h1 class="hero__title">' . $e((string)$data['title']) . '</h1>';
        if (($data['lead'] ?? '') !== '') echo '<p class="hero__lead">' . $e((string)$data['lead']) . '</p>';
        $primaryLabel = (string)($data['primary_label'] ?? '');
        $primaryUrl = page_builder_public_url($data['primary_url'] ?? '');
        $secondaryLabel = (string)($data['secondary_label'] ?? '');
        $secondaryUrl = page_builder_public_url($data['secondary_url'] ?? '');
        if (($primaryLabel !== '' && $primaryUrl !== '') || ($secondaryLabel !== '' && $secondaryUrl !== '')) {
            echo '<div class="hero__actions">';
            if ($primaryLabel !== '' && $primaryUrl !== '') echo '<a class="button button--light" href="' . $e($primaryUrl) . '">' . $e($primaryLabel) . '</a>';
            if ($secondaryLabel !== '' && $secondaryUrl !== '') echo '<a class="text-link" href="' . $e($secondaryUrl) . '">' . $e($secondaryLabel) . ' <span aria-hidden="true">→</span></a>';
            echo '</div>';
        }
        echo '</div></section>';
        return;
    }

    if ($type === 'intro') {
        echo '<section class="' . $sectionClass . '"><div class="container"><div class="grid grid--cols-2 grid--gap-large">';
        echo '<div class="grid__column section__header">';
        if (($data['kicker'] ?? '') !== '') echo '<p class="section__kicker">' . $e((string)$data['kicker']) . '</p>';
        if (($data['title'] ?? '') !== '') echo '<h2 class="section__title">' . $e((string)$data['title']) . '</h2>';
        echo '</div><div class="grid__column rich-text">' . page_builder_paragraphs((string)($data['body'] ?? '')) . '</div>';
        echo '</div></div></section>';
        return;
    }

    if ($type === 'text') {
        $layout = (($data['layout'] ?? 'split') === 'stacked') ? 'stacked' : 'split';
        echo '<section class="' . $sectionClass . '"><div class="container">';
        if ($layout === 'split') {
            echo '<div class="grid grid--cols-2 grid--gap-large">';
            echo '<div class="grid__column section__header">';
            if (($data['kicker'] ?? '') !== '') echo '<p class="section__kicker">' . $e((string)$data['kicker']) . '</p>';
            if (($data['title'] ?? '') !== '') echo '<h2 class="section__title">' . $e((string)$data['title']) . '</h2>';
            echo '</div><div class="grid__column rich-text">' . page_builder_paragraphs((string)($data['body'] ?? '')) . '</div></div>';
        } else {
            if (($data['kicker'] ?? '') !== '' || ($data['title'] ?? '') !== '') {
                echo '<div class="section__header">';
                if (($data['kicker'] ?? '') !== '') echo '<p class="section__kicker">' . $e((string)$data['kicker']) . '</p>';
                if (($data['title'] ?? '') !== '') echo '<h2 class="section__title">' . $e((string)$data['title']) . '</h2>';
                echo '</div>';
            }
            echo '<div class="rich-text">' . page_builder_paragraphs((string)($data['body'] ?? '')) . '</div>';
        }
        echo '</div></section>';
        return;
    }

    if ($type === 'text_image') {
        $side = (($data['image_side'] ?? 'right') === 'left') ? 'left' : 'right';
        echo '<section class="' . $sectionClass . '"><div class="container"><div class="grid grid--cols-2 grid--gap-large media-block media-block--image-' . $side . '">';
        $media = page_builder_media_asset($data);
        $image = $media['path'];
        if ($image !== '') {
            echo '<figure class="grid__column media-block__media"><img src="' . $e($image) . '" alt="' . $e($media['alt']) . '" loading="lazy" decoding="async"' . page_builder_image_dimensions($media) . page_builder_responsive_attributes($media) . '></figure>';
        } else {
            echo '<div class="grid__column media-block__media media-block__media--empty" aria-hidden="true"></div>';
        }
        echo '<div class="grid__column media-block__content">';
        if (($data['kicker'] ?? '') !== '') echo '<p class="section__kicker">' . $e((string)$data['kicker']) . '</p>';
        if (($data['title'] ?? '') !== '') echo '<h2 class="section__title">' . $e((string)$data['title']) . '</h2>';
        echo '<div class="rich-text">' . page_builder_paragraphs((string)($data['body'] ?? '')) . '</div></div>';
        echo '</div></div></section>';
        return;
    }

    if ($type === 'cta') {
        echo '<section class="' . $sectionClass . '"><div class="container cta">';
        echo '<div class="cta__content">';
        if (($data['kicker'] ?? '') !== '') echo '<p class="section__kicker">' . $e((string)$data['kicker']) . '</p>';
        if (($data['title'] ?? '') !== '') echo '<h2 class="section__title">' . $e((string)$data['title']) . '</h2>';
        if (($data['body'] ?? '') !== '') echo '<div class="cta__body">' . page_builder_paragraphs((string)$data['body']) . '</div>';
        echo '</div>';
        $url = page_builder_public_url($data['button_url'] ?? '');
        if (($data['button_label'] ?? '') !== '' && $url !== '') {
            $buttonClass = $theme === 'dark' ? 'button button--light' : 'button';
            echo '<a class="' . $buttonClass . '" href="' . $e($url) . '">' . $e((string)$data['button_label']) . '</a>';
        }
        echo '</div></section>';
        return;
    }

    if ($type === 'grid') {
        $count = max(2, min(6, (int)($data['column_count'] ?? 2)));
        $preset = (string)($data['preset'] ?? 'equal');
        if (!isset(PageBlocks::gridPresets($count)[$preset])) $preset = 'equal';
        $gap = (string)($data['gap'] ?? 'normal');
        if (!isset(PageBlocks::gridGaps()[$gap])) $gap = 'normal';
        $columns = is_array($data['columns'] ?? null) ? array_values($data['columns']) : [];
        while (count($columns) < $count) $columns[] = ['blocks' => []];
        $columns = array_slice($columns, 0, $count);

        $layoutClass = $preset === 'equal' ? 'grid--equal' : 'grid--split-' . $preset;
        echo '<section' . page_builder_section_id_attribute($data) . ' class="' . $sectionClass . '" data-container-width="' . $e(PageBlocks::normalContainerWidth((string)($data['container_width'] ?? 'standard'))) . '"><div class="container">';
        echo '<div class="grid grid--cols-' . $count . ' ' . $e($layoutClass) . ' grid--gap-' . $e($gap) . '">';
        foreach ($columns as $column) {
            echo '<div class="grid__column">';
            $children = is_array($column['blocks'] ?? null) ? $column['blocks'] : [];
            foreach ($children as $child) {
                if (($child['status'] ?? 'active') !== 'active') continue;
                render_page_builder_grid_child($child, $theme);
            }
            echo '</div>';
        }
        echo '</div></div></section>';
        return;
    }

    if ($type === 'component') {
        $component = (string)($data['component'] ?? '');
        $componentHtml = page_builder_component_markup($component, $data);
        if ($componentHtml === '' && ($data['kicker'] ?? '') === '' && ($data['title'] ?? '') === '') return;
        echo '<section class="' . $sectionClass . '"><div class="container">';
        if (($data['kicker'] ?? '') !== '' || ($data['title'] ?? '') !== '') {
            echo '<div class="section__header">';
            if (($data['kicker'] ?? '') !== '') echo '<p class="section__kicker">' . $e((string)$data['kicker']) . '</p>';
            if (($data['title'] ?? '') !== '') echo '<h2 class="section__title">' . $e((string)$data['title']) . '</h2>';
            echo '</div>';
        }
        echo $componentHtml;
        echo '</div></section>';
    }
}

function render_page_builder_grid_child(array $block, string $sectionTheme = 'light'): void
{
    $type = (string)($block['type'] ?? '');
    if (!isset(PageBlocks::gridChildDefinitions()[$type])) return;
    $data = is_array($block['data'] ?? null) ? $block['data'] : [];
    $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $typeClass = str_replace('_', '-', $type);
    $componentHtml = null;
    if ($type === 'component') {
        $componentHtml = page_builder_component_markup((string)($data['component'] ?? ''), $data);
        if ($componentHtml === '' && ($data['kicker'] ?? '') === '' && ($data['title'] ?? '') === '') return;
    }
    if ($type === 'image' && page_builder_media_asset($data)['path'] === '') return;

    echo '<div class="block block--' . $e($typeClass) . '">';

    if ($type === 'text') {
        if (($data['kicker'] ?? '') !== '') echo '<p class="block__kicker">' . $e((string)$data['kicker']) . '</p>';
        if (($data['title'] ?? '') !== '') echo '<h3 class="block__title">' . $e((string)$data['title']) . '</h3>';
        if (($data['body'] ?? '') !== '') echo '<div class="block__content">' . page_builder_paragraphs((string)$data['body']) . '</div>';
    } elseif ($type === 'image') {
        $media = page_builder_media_asset($data);
        $image = $media['path'];
        if ($image !== '') {
            echo '<figure class="block__figure"><img src="' . $e($image) . '" alt="' . $e($media['alt']) . '" loading="lazy" decoding="async"' . page_builder_image_dimensions($media) . page_builder_responsive_attributes($media) . '>';
            if (($data['caption'] ?? '') !== '') echo '<figcaption class="block__caption">' . $e((string)$data['caption']) . '</figcaption>';
            echo '</figure>';
        }
    } elseif ($type === 'text_image') {
        $media = page_builder_media_asset($data);
        $image = $media['path'];
        $position = (($data['image_position'] ?? 'top') === 'bottom') ? 'bottom' : 'top';
        if ($position === 'top' && $image !== '') {
            echo '<figure class="block__figure"><img src="' . $e($image) . '" alt="' . $e($media['alt']) . '" loading="lazy" decoding="async"' . page_builder_image_dimensions($media) . page_builder_responsive_attributes($media) . '></figure>';
        }
        if (($data['kicker'] ?? '') !== '') echo '<p class="block__kicker">' . $e((string)$data['kicker']) . '</p>';
        if (($data['title'] ?? '') !== '') echo '<h3 class="block__title">' . $e((string)$data['title']) . '</h3>';
        if (($data['body'] ?? '') !== '') echo '<div class="block__content">' . page_builder_paragraphs((string)$data['body']) . '</div>';
        if ($position === 'bottom' && $image !== '') {
            echo '<figure class="block__figure block__figure--bottom"><img src="' . $e($image) . '" alt="' . $e($media['alt']) . '" loading="lazy" decoding="async"' . page_builder_image_dimensions($media) . page_builder_responsive_attributes($media) . '></figure>';
        }
    } elseif ($type === 'cta') {
        if (($data['kicker'] ?? '') !== '') echo '<p class="block__kicker">' . $e((string)$data['kicker']) . '</p>';
        if (($data['title'] ?? '') !== '') echo '<h3 class="block__title">' . $e((string)$data['title']) . '</h3>';
        if (($data['body'] ?? '') !== '') echo '<div class="block__content">' . page_builder_paragraphs((string)$data['body']) . '</div>';
        $url = page_builder_public_url($data['button_url'] ?? '');
        if (($data['button_label'] ?? '') !== '' && $url !== '') {
            $buttonClass = $sectionTheme === 'dark' ? 'button button--light' : 'button';
            echo '<a class="' . $buttonClass . ' block__button" href="' . $e($url) . '">' . $e((string)$data['button_label']) . '</a>';
        }
    } elseif ($type === 'component') {
        if (($data['kicker'] ?? '') !== '') echo '<p class="block__kicker">' . $e((string)$data['kicker']) . '</p>';
        if (($data['title'] ?? '') !== '') echo '<h3 class="block__title">' . $e((string)$data['title']) . '</h3>';
        $component = (string)($data['component'] ?? '');
        echo $componentHtml ?? page_builder_component_markup($component, $data);
    }

    echo '</div>';
}

function page_builder_paragraphs(string $text): string
{
    $text = trim($text);
    if ($text === '') return '';
    $paragraphs = preg_split('/\\R{2,}/u', $text) ?: [$text];
    $html = '';
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') continue;
        $html .= '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')) . '</p>';
    }
    return $html;
}
