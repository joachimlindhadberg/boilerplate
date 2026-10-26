<?php

/**
 * Optional module extension for public, read-only content routes.
 * Core page URLs take precedence. Return null for paths the module does not own.
 * Modules provide page metadata and semantic content, never theme CSS.
 */
interface ModulePublicPageInterface
{
    public function resolvePublicPage(string $path): ?array;
    public function renderPublicPage(array $page): void;
}
