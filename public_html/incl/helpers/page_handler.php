<?php
function get_current_page(Pages $pagesModel): array
{
    static $cached;
    if ($cached) return $cached;

    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $page = $pagesModel->findByPublicPath($requestUri);
    if (!$page && function_exists('cms_module_manager')) {
        $path = (string)(parse_url($requestUri, PHP_URL_PATH) ?: '/');
        $page = cms_module_manager()->resolvePublicPage($path);
    }

    // Drafts and unknown statuses are never public. A future preview feature
    // should use an explicit authenticated preview route instead of exposing
    // draft URLs through the normal frontend router.
    if (!$page || !in_array((string)($page['page_status'] ?? ''), ['published', 'protected'], true)) {
        http_response_code(404);
        return $cached = [
            'page_title' => '404 – Siden findes ikke',
            'page_description' => '',
            'page_content' => $pagesModel->get404Content(),
            'content_mode' => 'legacy',
        ];
    }

    if (($page['page_status'] ?? '') === 'protected') {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!is_logged_in()) {
            header('Location: /admin');
            exit;
        }
    }

    return $cached = $page;
}

function render_page_content(Pages $pagesModel): void
{
    $page = get_current_page($pagesModel);
    if (isset($page['_module_public']) && cms_module_manager()->renderPublicPage($page)) {
        return;
    }
    if (($page['content_mode'] ?? 'legacy') === 'builder' && !empty($page['id'])) {
        render_page_builder_blocks((int)$page['id']);
        return;
    }
    echo parse_dynamic_components((string)($page['page_content'] ?? ''));
}

function parse_dynamic_components(string $content): string
{
    $map = [
        'breadcrumbs' => fn() => capture_output(fn() => get_page('breadcrumbs')),
    ];
    foreach ($map as $key => $callback) {
        $content = str_replace('[[' . $key . ']]', $callback(), $content);
    }
    return $content;
}

function capture_output(callable $fn): string
{
    ob_start();
    $fn();
    return (string)ob_get_clean();
}
