<?php
$siteName = trim((string)$settings->get('site_name', 'Boilerplate')) ?: 'Boilerplate';
$page = get_current_page($pagesModel);
$title = trim((string)(!empty($page['meta_title']) ? $page['meta_title'] : ($page['page_title'] ?? $siteName)));
$description = trim((string)(!empty($page['meta_description']) ? $page['meta_description'] : ($page['page_description'] ?? '')));
if ($description === '') $description = (string)$settings->get('seo_default_description', 'Generisk CMS boilerplate.');
$canonical = get_current_url();
$indexing = filter_var($settings->get('seo_indexing', 1), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($indexing === null) $indexing = (string)$settings->get('seo_indexing', '1') !== '0';
$robots = (http_response_code() === 404 || !$indexing) ? 'noindex, nofollow' : 'index, follow';
?>
<!doctype html>
<html lang="<?php get_page('site_locale'); ?>">
<head>
  <meta charset="<?php get_page('site_charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
  <meta name="robots" content="<?= htmlspecialchars($robots, ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('/assets/css/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
<?php FrontendAdminBar::render($page); ?>
<header class="site-header">
  <div class="container site-header__row">
    <a class="site-header__brand" href="/"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></a>
    <?php ob_start(); get_page('navigation', add_id('site-navigation')); $headerNavigation = (string)ob_get_clean(); ?>
    <?php if (trim($headerNavigation) !== ''): ?>
    <button class="site-navigation-toggle" type="button" aria-expanded="false" aria-controls="site-navigation" aria-label="Åbn navigation" data-navigation-toggle>
      <span class="site-navigation-toggle__icon" aria-hidden="true"></span>
    </button>
    <?= $headerNavigation; ?>
    <?php endif; ?>
  </div>
</header>
<main class="site-main">
