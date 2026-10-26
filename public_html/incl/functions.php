<?php
/**
 * Shared frontend helper bootstrap for the Boilerplate CMS.
 */
require_once __DIR__ . '/helpers/models.php';
require_once __DIR__ . '/helpers/auth_helpers.php';
require_once __DIR__ . '/helpers/html_tools.php';
require_once __DIR__ . '/helpers/component_renderer.php';
require_once __DIR__ . '/helpers/page_builder_renderer.php';
require_once __DIR__ . '/helpers/url_helpers.php';
require_once __DIR__ . '/helpers/page_handler.php';
require_once __DIR__ . '/helpers/navigation_builder.php';
require_once __DIR__ . '/helpers/system_endpoints.php';
require_once __DIR__ . '/helpers/redirect_handler.php';

function get_page(string $component, ...$modifiers): void
{
    component_renderer($component, $modifiers);
}
