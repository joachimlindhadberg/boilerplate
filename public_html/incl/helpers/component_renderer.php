<?php
function component_renderer(string $component, array $modifiers = []): void
{
    $pagesModel = model('pages');
    $settings = model('settings');

    switch ($component) {
        case 'header': include __DIR__ . '/../header.php'; break;
        case 'footer': include __DIR__ . '/../footer.php'; break;
        case 'navigation': render_navigation($modifiers, $pagesModel); break;
        case 'content': render_page_content($pagesModel); break;
        case 'title':
            $page = get_current_page($pagesModel);
            echo htmlspecialchars((string)(!empty($page['meta_title']) ? $page['meta_title'] : ($page['page_title'] ?? 'Boilerplate')), ENT_QUOTES, 'UTF-8');
            break;
        case 'description':
            $page = get_current_page($pagesModel);
            $description = (string)(!empty($page['meta_description']) ? $page['meta_description'] : ($page['page_description'] ?? ''));
            if ($description === '') $description = (string)$settings->get('seo_default_description', 'A flexible content management system.');
            echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
            break;
        case 'social_image':
            echo htmlspecialchars(get_site_origin() . '/assets/images/favicon.svg', ENT_QUOTES, 'UTF-8');
            break;
        case 'breadcrumbs': render_breadcrumbs($pagesModel); break;
        case 'site_charset': echo htmlspecialchars((string)$settings->get('charset', 'UTF-8')); break;
        case 'site_locale': echo htmlspecialchars(str_replace('_', '-', (string)$settings->get('language', 'en_US'))); break;
        default:
            if (function_exists('cms_module_manager') && cms_module_manager()->renderComponent($component, $modifiers)) {
                break;
            }
            echo '<!-- Unknown component: ' . htmlspecialchars($component, ENT_QUOTES, 'UTF-8') . ' -->';
    }
}
