<?php

/**
 * Safe runtime context handed to one active module.
 */
class ModuleContext
{
    private array $manifest;
    private string $basePath;
    private ?ModuleHooks $hooks;

    public function __construct(array $manifest, string $basePath, ?ModuleHooks $hooks = null)
    {
        $this->manifest = $manifest;
        $this->hooks = $hooks;
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
    }

    public function slug(): string
    {
        return (string)($this->manifest['slug'] ?? '');
    }

    public function name(): string
    {
        return (string)($this->manifest['name'] ?? $this->slug());
    }

    public function version(): string
    {
        return (string)($this->manifest['version'] ?? '');
    }

    public function manifest(): array
    {
        return $this->manifest;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /** Add a request-local action listener. An inactive module cannot register hooks. */
    public function onAction(string $name, callable $callback, int $priority = 10): void
    {
        if ($this->hooks === null) throw new LogicException('Module hooks are unavailable in this context.');
        $this->hooks->addAction($this->slug(), $name, $callback, $priority);
    }

    /** Add a request-local filter. Return the filtered value from the callback. */
    public function onFilter(string $name, callable $callback, int $priority = 10): void
    {
        if ($this->hooks === null) throw new LogicException('Module hooks are unavailable in this context.');
        $this->hooks->addFilter($this->slug(), $name, $callback, $priority);
    }

    /**
     * Render a module-owned admin view inside the standard CMS layout.
     */
    public function renderAdmin(string $relativeView, array $data = []): void
    {
        $viewFile = $this->resolveFile($relativeView);
        if ($viewFile === null) {
            throw new RuntimeException('Module admin view not found.');
        }

        $user = $data['user'] ?? null;
        $activeNav = (string)($data['activeNav'] ?? ('module:' . $this->slug()));
        $documentTitle = (string)($data['documentTitle'] ?? $this->name());
        $sectionWorkspace = is_array($data['sectionWorkspace'] ?? null) ? $data['sectionWorkspace'] : null;

        // Core layout variables and workspace configuration do not become view variables.
        $viewData = $data;
        unset($viewData['user'], $viewData['activeNav'], $viewData['documentTitle'], $viewData['bodyHtml'], $viewData['sectionWorkspace']);
        extract($viewData, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $bodyHtml = (string)ob_get_clean();
        if ($sectionWorkspace !== null) {
            $workspaceView = PUBLICPATH . '/admin/views/partials/section-workspace.php';
            if (!is_file($workspaceView)) {
                throw new RuntimeException('Admin section workspace view not found.');
            }
            ob_start();
            require $workspaceView;
            $bodyHtml = (string)ob_get_clean();
        }

        $layout = PUBLICPATH . '/admin/views/partials/layout.php';
        if (!is_file($layout)) {
            throw new RuntimeException('Admin layout not found.');
        }
        require $layout;
    }

    private function resolveFile(string $relativePath): ?string
    {
        $relativePath = ltrim(str_replace('\\', '/', trim($relativePath)), '/');
        if ($relativePath === '' || str_contains($relativePath, '../')) {
            return null;
        }

        $candidate = $this->basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $realBase = realpath($this->basePath);
        $realFile = realpath($candidate);

        if ($realBase === false || $realFile === false || !is_file($realFile)) {
            return null;
        }

        $prefix = rtrim($realBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($realFile, $prefix) ? $realFile : null;
    }
}
