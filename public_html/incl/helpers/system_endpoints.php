<?php
function handle_system_endpoint(): bool
{
    $path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    if ($path === '/robots.txt') { render_robots_txt(); return true; }
    if ($path === '/sitemap.xml') { render_sitemap_xml(); return true; }

    $moduleAction = trim((string)($_POST['_module_action'] ?? ''));
    if ($moduleAction !== '' && function_exists('cms_module_manager')) {
        try {
            if (cms_module_manager()->handleFrontendAction($moduleAction, [
                'method' => strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')),
                'path' => $path,
            ])) {
                return true;
            }
        } catch (Throwable $e) {
            error_log('Module frontend action failed [' . $moduleAction . ']: ' . $e->getMessage());
            if (!AppConfig::isProduction()) {
                throw $e;
            }
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'The form could not be processed. Please try again.';
            return true;
        }
    }

    return false;
}

function maintenance_mode_enabled(): bool
{
    $value = filter_var(model('settings')->get('maintenance_mode', 0), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $value ?? ((string)model('settings')->get('maintenance_mode', '0') === '1');
}

function seo_indexing_enabled(): bool
{
    if (maintenance_mode_enabled()) return false;
    $value = filter_var(model('settings')->get('seo_indexing', 1), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $value ?? ((string)model('settings')->get('seo_indexing', '1') !== '0');
}

function handle_maintenance_mode(): bool
{
    if (!maintenance_mode_enabled()) return false;
    $settings = model('settings');
    $siteName = trim((string)$settings->get('site_name', 'Boilerplate')) ?: 'Boilerplate';
    $message = trim((string)$settings->get('maintenance_message', 'The website is temporarily unavailable for maintenance.'));
    http_response_code(503);
    header('Retry-After: 3600');
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Content-Type: text/html; charset=UTF-8');
    $e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="' . $e(str_replace('_', '-', (string)$settings->get('language', 'en_US'))) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Maintenance · '.$e($siteName).'</title></head><body><main><h1>We will be back soon.</h1><p>'.$e($message).'</p></main></body></html>';
    return true;
}

function render_robots_txt(): void
{
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: public, max-age=3600');
    if (!seo_indexing_enabled()) { echo "User-agent: *\nDisallow: /\n"; return; }
    $origin = get_site_origin();
    echo "User-agent: *\nAllow: /\nDisallow: /incl/\nDisallow: /assets/scss/\nSitemap: {$origin}/sitemap.xml\n";
}

function render_sitemap_xml(): void
{
    if (!seo_indexing_enabled()) { http_response_code(404); header('Content-Type: text/plain; charset=UTF-8'); echo 'Not found'; return; }
    header('Content-Type: application/xml; charset=UTF-8');
    header('Cache-Control: public, max-age=3600');
    $origin = get_site_origin();
    $urls = [];
    $pagesModel = model('pages');
    foreach ($pagesModel->allPublished() as $page) {
        $location = $pagesModel->buildPublicUrl($page);
        $urls[$origin . $location] = sitemap_date($page['updated'] ?? null);
    }
    if (function_exists('cms_module_manager')) {
        foreach (cms_module_manager()->sitemapEntries() as $entry) {
            $loc = $origin . $entry['path'];
            if (!array_key_exists($loc, $urls)) {
                $urls[$loc] = $entry['lastmod'];
            }
        }
    }
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    foreach ($urls as $loc => $lastmod) {
        echo "  <url>\n    <loc>" . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
        if ($lastmod !== null) echo '    <lastmod>' . htmlspecialchars($lastmod, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
        echo "  </url>\n";
    }
    echo "</urlset>\n";
}

function sitemap_date(mixed $value): ?string
{
    if (!is_string($value) || trim($value) === '') return null;
    try { return (new DateTimeImmutable($value))->format('Y-m-d'); } catch (Throwable) { return null; }
}
