<?php
/** Header navigation uses the independent core Menu Manager. */
function render_navigation(array $modifiers, Pages $pagesModel): void
{
    $modifiers[] = add_class('site-navigation');
    $modifiers[] = add_attr('data-navigation', '');
    $attr = build_attributes($modifiers);
    $slot = (string)model('settings')->get('navigation_header_menu', 'primary');
    if ($slot === '') return; // Explicitly no header menu.
    $tree = navigation_public_tree($slot, $pagesModel);
    if ($tree === null) {
        // Upgrade-safe: the old header remains available until SQL migration runs.
        $pages = array_values(array_filter($pagesModel->allPublished(), static fn(array $page): bool => !empty($page['show_in_menu'])));
        $oldTree = []; $byId = [];
        foreach ($pages as $page) {
            $oldTree[(int)($page['parent_id'] ?? 0)][] = $page;
            $byId[(int)$page['id']] = $page;
        }
        $markup = build_menu_level($oldTree, 0, false, $byId);
        if ($markup !== '') echo "<nav{$attr} aria-label=\"Main navigation\">" . $markup . '</nav>';
        return;
    }
    if (!$tree) return;
    echo '<nav' . $attr . ' aria-label="Main navigation">' . navigation_tree_html($tree, false, 'site-navigation') . '</nav>';
}

function navigation_public_tree(string $slug, Pages $pages): ?array
{
    try {
        return (new Navigation())->publicTree($slug, $pages);
    } catch (mysqli_sql_exception $e) {
        // Missing migration: keep the old header rather than taking down the site.
        if ((int)$e->getCode() === 1146) {
            error_log('Navigation tables missing; run database/migrations/20261011_navigation_v1_7_0.sql');
            return null;
        }
        throw $e;
    }
}

/** A theme owns presentation; core outputs only semantic lists and link state. */
function navigation_tree_html(array $tree, bool $subLevel, string $prefix): string
{
    if (!$tree) return '';
    $currentPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    $currentPath = rtrim($currentPath, '/') ?: '/';
    $listClass = $prefix === 'site-navigation'
        ? ($subLevel ? 'site-navigation__sublist' : 'site-navigation__list')
        : 'site-footer__navigation-list';
    $html = '<ul class="' . $listClass . '">';
    foreach ($tree as $item) {
        $href = (string)$item['href'];
        $targetPath = parse_url($href, PHP_URL_PATH);
        $isLocal = str_starts_with($href, '/') && !str_starts_with($href, '//');
        $active = $isLocal && (rtrim((string)$targetPath, '/') ?: '/') === $currentPath;
        $children = $item['children'] ?? [];
        $childHtml = navigation_tree_html($children, true, $prefix);
        $childActive = str_contains($childHtml, 'aria-current="page"');
        $itemClass = $prefix === 'site-navigation' ? 'site-navigation__item' : 'site-footer__navigation-item';
        $linkClass = $prefix === 'site-navigation' ? 'site-navigation__link' : 'site-footer__navigation-link';
        $classes = [$itemClass];
        if ($prefix === 'site-navigation' && $children) $classes[] = $itemClass . '--has-children';
        if ($prefix === 'site-navigation' && ($active || $childActive)) $classes[] = $itemClass . '--active';
        $target = ($item['target'] ?? '_self') === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '';
        $current = $active ? ' aria-current="page"' : '';
        $html .= '<li class="' . implode(' ', $classes) . '"><a class="' . $linkClass . '" href="'
            . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' . $target . $current . '>'
            . htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') . '</a>' . $childHtml . '</li>';
    }
    return $html . '</ul>';
}

function render_footer_navigation(Pages $pagesModel): void
{
    $slot = (string)model('settings')->get('navigation_footer_menu', 'footer');
    $tree = navigation_public_tree($slot, $pagesModel);
    if (!$tree) return;
    echo '<nav class="site-footer__navigation" aria-label="Footer">' . navigation_tree_html($tree, false, 'site-footer__navigation') . '</nav>';
}

/** Use a custom menu from a theme template without leaking presentation into core. */
function render_named_navigation(string $slug, Pages $pagesModel, string $ariaLabel = 'Navigation'): void
{
    if (!preg_match('/^[a-z0-9-]{1,100}$/', $slug)) return;
    $tree = navigation_public_tree($slug, $pagesModel);
    if (!$tree) return;
    echo '<nav aria-label="' . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '">'
        . navigation_tree_html($tree, false, 'site-navigation') . '</nav>';
}

/** Legacy rendering for sites that have not executed the V1.7.0 migration yet. */
function build_full_page_url(array $page, array $byId): string
{
    if (($page['page_slug'] ?? '') === 'forside') return '/';
    $segments = [(string)$page['page_slug']];
    $parent = (int)($page['parent_id'] ?? 0);
    $seen = [];
    while ($parent > 0 && isset($byId[$parent]) && !isset($seen[$parent])) {
        $seen[$parent] = true;
        if (($byId[$parent]['page_slug'] ?? '') !== 'forside') array_unshift($segments, (string)$byId[$parent]['page_slug']);
        $parent = (int)($byId[$parent]['parent_id'] ?? 0);
    }
    return '/' . implode('/', $segments);
}

function build_menu_level(array $tree, int $parentId, bool $dropdown, array $byId): string
{
    if (!isset($tree[$parentId])) return '';
    $segments = get_url_segments();
    $currentSlug = (string)(end($segments) ?: 'forside');
    $ulClass = $dropdown ? 'site-navigation__sublist' : 'site-navigation__list';
    $html = '<ul class="' . $ulClass . '">';
    foreach ($tree[$parentId] as $page) {
        $id = (int)$page['id'];
        $hasChildren = isset($tree[$id]);
        $url = build_full_page_url($page, $byId);
        $title = htmlspecialchars((string)$page['page_title'], ENT_QUOTES, 'UTF-8');
        $active = ((string)$page['page_slug'] === $currentSlug);
        $childHtml = $hasChildren ? build_menu_level($tree, $id, true, $byId) : '';
        $childActive = str_contains($childHtml, 'site-navigation__item--active');
        $itemClasses = ['site-navigation__item'];
        if ($hasChildren) $itemClasses[] = 'site-navigation__item--has-children';
        if ($active || $childActive) $itemClasses[] = 'site-navigation__item--active';
        $html .= '<li class="' . implode(' ', $itemClasses) . '"><a class="site-navigation__link" href="'
            . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $title . '</a>' . $childHtml . '</li>';
    }
    return $html . '</ul>';
}

function render_breadcrumbs(Pages $pagesModel): void
{
    $segments = get_url_segments();
    if ($segments === ['forside'] || $segments === []) return;
    $pages = $pagesModel->allPublished();
    $bySlug = [];
    foreach ($pages as $page) $bySlug[(string)$page['page_slug']] = $page;

    $html = '<nav class="breadcrumbs" aria-label="Breadcrumbs"><div class="container"><ol class="breadcrumbs__list">';
    $homeLabel = htmlspecialchars((string)($bySlug['forside']['page_title'] ?? 'Home'), ENT_QUOTES, 'UTF-8');
    $html .= '<li class="breadcrumbs__item"><a class="breadcrumbs__link" href="/">' . $homeLabel . '</a></li>';
    $path = '';
    $lastIndex = count($segments) - 1;
    foreach ($segments as $index => $slug) {
        if ($slug === 'forside' || !isset($bySlug[$slug])) continue;
        $path .= '/' . rawurlencode($slug);
        $title = htmlspecialchars((string)$bySlug[$slug]['page_title'], ENT_QUOTES, 'UTF-8');
        if ($index === $lastIndex) {
            $html .= '<li class="breadcrumbs__item breadcrumbs__item--current" aria-current="page">' . $title . '</li>';
        } else {
            $html .= '<li class="breadcrumbs__item"><a class="breadcrumbs__link" href="' . $path . '">' . $title . '</a></li>';
        }
    }
    echo $html . '</ol></div></nav>';
}
