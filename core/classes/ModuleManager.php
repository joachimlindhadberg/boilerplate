<?php

/**
 * Core module registry and safe loader.
 *
 * Modules live outside core in ROOTPATH/modules/<slug>. Static metadata is
 * read from module.json without loading PHP. Only database-active modules have
 * their entry file included and booted.
 */
class ModuleManager
{
    private Db $db;
    private string $modulesPath;
    private ?array $discovered = null;
    private ?array $registeredRows = null;
    private array $instances = [];
    private array $contexts = [];
    private array $runtimeErrors = [];
    private bool $activeBooted = false;
    private ModuleHooks $hooks;

    public function __construct(?string $modulesPath = null, ?Db $db = null)
    {
        $this->db = $db ?? new Db();
        $this->hooks = new ModuleHooks();
        $this->modulesPath = rtrim($modulesPath ?? (ROOTPATH . '/modules'), DIRECTORY_SEPARATOR);
        // Read-only at startup. Registration is an explicit administrator operation.
    }

    /** Discover/install module metadata only in the Modules administration workflow. */
    public function syncRegistry(): void
    {
        $this->syncDiscovered();
    }

    /** @return array<int,array<string,mixed>> */
    public function allModules(): array
    {
        $discovered = $this->discover();
        $rows = $this->databaseRows();
        $result = [];

        foreach ($discovered as $slug => $module) {
            $row = $rows[$slug] ?? [];
            $module['active'] = !empty($row['is_active']);
            $module['installed_version'] = (string)($row['installed_version'] ?? '');
            $module['update_available'] = !empty($module['valid'])
                && $module['installed_version'] !== ''
                && version_compare((string)$module['version'], $module['installed_version'], '>');
            $module['version_mismatch'] = !empty($module['valid'])
                && $module['installed_version'] !== ''
                && version_compare((string)$module['version'], $module['installed_version'], '<');
            $module['activated_at'] = $row['activated_at'] ?? null;
            $module['available'] = true;
            $module['compatibility_error'] = $this->compatibilityError($module);
            $module['compatible'] = $module['compatibility_error'] === null;
            $module['runtime_error'] = $this->runtimeErrors[$slug] ?? null;
            $result[$slug] = $module;
        }

        // Keep a missing module visible to administrators instead of silently
        // forgetting a previously registered database entry.
        foreach ($rows as $slug => $row) {
            if (isset($result[$slug])) {
                continue;
            }
            $result[$slug] = [
                'slug' => $slug,
                'name' => (string)($row['module_name'] ?? $slug),
                'version' => (string)($row['installed_version'] ?? ''),
                'installed_version' => (string)($row['installed_version'] ?? ''),
                'update_available' => false,
                'version_mismatch' => false,
                'description' => 'Modulefilerne findes ikke længere på serveren.',
                'author' => '',
                'requires' => '',
                'valid' => false,
                'error' => 'Modulemappen mangler.',
                'active' => !empty($row['is_active']),
                'available' => false,
                'compatible' => false,
                'compatibility_error' => 'Modulefilerne mangler.',
                'activated_at' => $row['activated_at'] ?? null,
                'runtime_error' => null,
            ];
        }

        uasort($result, static function (array $a, array $b): int {
            return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        return array_values($result);
    }

    /**
     * Activation is an explicit transition, not a request-time installation.
     * Optional lifecycle failures prevent changing the active flag.
     */
    public function activate(string $slug): void
    {
        $this->syncRegistry();
        $slug = $this->normalizeSlug($slug);
        $module = $this->requireTransitionModule($slug);
        $row = $this->databaseRows()[$slug] ?? null;
        if (!$row) throw new RuntimeException('Module registration missing. Open Modules and try again.');
        if (!empty($row['is_active'])) return;
        if ((string)$row['installed_version'] !== '') {
            if (version_compare((string)$module['version'], (string)$row['installed_version'], '<')) {
                throw new RuntimeException('Module files are older than the registered version. Restore the correct files.');
            }
            if (version_compare((string)$module['version'], (string)$row['installed_version'], '>')) {
                throw new RuntimeException('A plugin update is pending. Complete the update before activation.');
            }
        }
        $this->assertMigrationsApplied($slug);
        $instance = $this->loadInstance($module, false);
        $context = new ModuleContext($module, (string)$module['base_path']);
        if ($instance instanceof ModuleLifecycleInterface) $instance->onActivate($context);
        $this->db->query(
            'UPDATE modules SET is_active = 1, installed_version = ?, activated_at = NOW(), updated_at = NOW() WHERE module_slug = ? AND is_active = 0',
            [(string)$module['version'], $slug]
        );
        $this->invalidateRuntime($slug);
        $this->emitLifecycleAction('modules.activated', $slug, (string)$module['version']);
    }

    public function deactivate(string $slug): void
    {
        $slug = $this->normalizeSlug($slug);
        $row = $this->databaseRows()[$slug] ?? null;
        if ($slug === '' || $row === null) throw new RuntimeException('Ugyldigt module.');
        if (empty($row['is_active'])) return;
        $module = $this->discover()[$slug] ?? null;
        if ($module && !empty($module['valid']) && $this->compatibilityError($module) === null
            && version_compare((string)$module['version'], (string)($row['installed_version'] ?? ''), '==')) {
            // Do not depend on boot() just to deactivate a module. Broken entry
            // classes are bypassed so admins can still recover safely.
            try {
                $instance = $this->loadInstance($module, false);
            } catch (Throwable $error) {
                error_log('Module deactivate fallback [' . $slug . ']: ' . $error->getMessage());
                $instance = null;
            }
            if ($instance instanceof ModuleLifecycleInterface) {
                $instance->onDeactivate(new ModuleContext($module, (string)$module['base_path']));
            }
        }
        // Missing, incompatible or mismatched files may be deactivated without running plugin PHP.
        $this->db->query(
            'UPDATE modules SET is_active = 0, activated_at = NULL, updated_at = NOW() WHERE module_slug = ? AND is_active = 1',
            [$slug]
        );
        $this->invalidateRuntime($slug);
        $this->emitLifecycleAction('modules.deactivated', $slug, (string)($row['installed_version'] ?? ''));
    }

    /** Explicitly acknowledge deployed plugin files after migrations and callbacks succeed. */
    public function update(string $slug): void
    {
        $this->syncRegistry();
        $slug = $this->normalizeSlug($slug);
        $module = $this->requireTransitionModule($slug);
        $row = $this->databaseRows()[$slug] ?? null;
        if (!$row) throw new RuntimeException('Module registration missing.');
        $from = (string)($row['installed_version'] ?? '');
        $to = (string)$module['version'];
        if ($from === '' || version_compare($to, $from, '<')) {
            throw new RuntimeException('Module update is invalid: installed version is missing or newer than plugin files.');
        }
        if (version_compare($to, $from, '==')) return;
        $this->assertMigrationsApplied($slug);
        $instance = $this->loadInstance($module, false);
        if ($instance instanceof ModuleLifecycleInterface) {
            $instance->onUpdate(new ModuleContext($module, (string)$module['base_path']), $from);
        }
        $this->db->query(
            'UPDATE modules SET installed_version = ?, module_name = ?, updated_at = NOW() WHERE module_slug = ? AND installed_version = ?',
            [$to, (string)$module['name'], $slug, $from]
        );
        $this->invalidateRuntime($slug);
        $this->emitLifecycleAction('modules.updated', $slug, $from, $to);
    }

    private function requireTransitionModule(string $slug): array
    {
        $module = $this->discover()[$slug] ?? null;
        if ($slug === '' || !$module || empty($module['valid'])) {
            throw new RuntimeException((string)($module['error'] ?? 'Modulet kunne ikke findes.'));
        }
        $compatibility = $this->compatibilityError($module);
        if ($compatibility !== null) throw new RuntimeException($compatibility);
        return $module;
    }

    private function assertMigrationsApplied(string $slug): void
    {
        foreach ((new MigrationRunner($this->db))->status() as $migration) {
            if (($migration['state'] ?? '') !== 'applied'
                && (str_starts_with((string)$migration['id'], 'core:')
                    || str_starts_with((string)$migration['id'], 'module:' . $slug . ':'))) {
                throw new RuntimeException('Database migration required before module activation/update. Run the migration command first.');
            }
        }
    }

    private function invalidateRuntime(string $slug): void
    {
        $this->registeredRows = null;
        unset($this->instances[$slug], $this->contexts[$slug], $this->runtimeErrors[$slug]);
        $this->hooks->forget($slug);
        $this->activeBooted = false;
    }

    /** Notification hooks are best-effort and must never roll back a completed transition. */
    private function emitLifecycleAction(string $event, string ...$arguments): void
    {
        try {
            $this->bootActiveModules();
            $this->hooks->doAction($event, ...$arguments);
        } catch (Throwable $error) {
            error_log('Module lifecycle notification failed [' . $event . ']: ' . $error->getMessage());
        }
    }

    /**
     * Load and boot only active modules. One broken module is isolated from
     * the rest of the CMS and does not stop other active modules from loading.
     */
    public function bootActiveModules(): array
    {
        if ($this->activeBooted) {
            return $this->instances;
        }
        $this->activeBooted = true;

        $activeRows = array_filter($this->databaseRows(), static fn(array $row): bool => !empty($row['is_active']));
        ksort($activeRows, SORT_STRING); // Preserve the previous module_slug boot order.
        $discovered = $this->discover();

        foreach ($activeRows as $row) {
            $slug = $this->normalizeSlug((string)($row['module_slug'] ?? ''));
            $module = $discovered[$slug] ?? null;
            if (!$module || empty($module['valid'])) {
                continue;
            }

            $compatibility = $this->compatibilityError($module);
            if ($compatibility !== null) {
                $this->runtimeErrors[$slug] = $compatibility;
                error_log('Module ' . $slug . ': ' . $compatibility);
                continue;
            }

            $installedVersion = (string)($row['installed_version'] ?? '');
            if ($installedVersion !== '' && version_compare((string)$module['version'], $installedVersion, '!=')) {
                // Fail closed until an explicit update confirms the deployed code.
                // Mismatched plugin PHP must not boot with an unconfirmed schema.
                $this->runtimeErrors[$slug] = 'Plugin file version differs from confirmed version. Complete update or restore matching files.';
                continue;
            }

            try {
                $this->loadInstance($module, true);
            } catch (Throwable $e) {
                $this->hooks->forget($slug);
                $message = AppConfig::isProduction() ? 'Modulet kunne ikke startes.' : $e->getMessage();
                $this->runtimeErrors[$slug] = $message;
                error_log('Module boot failed [' . $slug . ']: ' . $e->getMessage());
            }
        }

        $this->hooks->doAction('modules.booted');
        return $this->instances;
    }

    /** Run a registered action after booting active modules. */
    public function doAction(string $hook, mixed ...$args): void
    {
        $this->bootActiveModules();
        $this->hooks->doAction($hook, ...$args);
    }

    /** Apply registered filters after booting active modules. */
    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        $this->bootActiveModules();
        return $this->hooks->applyFilters($hook, $value, ...$args);
    }

    /** @return array<int,array<string,mixed>> */
    public function adminMenuItems(): array
    {
        $this->bootActiveModules();
        $items = [];

        foreach ($this->instances as $slug => $instance) {
            try {
                $registrations = $instance->adminMenu();
            } catch (Throwable $e) {
                error_log('Module admin menu failed [' . $slug . ']: ' . $e->getMessage());
                continue;
            }

            foreach ($registrations as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $label = trim((string)($item['label'] ?? ''));
                $route = $this->normalizeRoute((string)($item['route'] ?? ''));
                if ($label === '' || $route === '' || !CapabilityRegistry::isAllowedForModule($slug, (string)($item['capability'] ?? ''))) {
                    continue;
                }
                $items[] = [
                    'label' => $label,
                    'route' => $route,
                    'href' => '/admin/' . $route,
                    'icon' => trim((string)($item['icon'] ?? 'cube')) ?: 'cube',
                    'capability' => trim((string)($item['capability'] ?? '')),
                    'key' => trim((string)($item['key'] ?? '')) ?: ('module:' . $slug . ':' . $index),
                    'module_slug' => $slug,
                    'badge_label' => trim((string)($item['badge_label'] ?? '')),
                ];
            }
        }

        return $items;
    }

    /**
     * Optional badge provider contract: only active modules can contribute and
     * only to their own, Core-authorized top-level menu keys. Each provider is
     * called once at most per request, not once for each menu item.
     *
     * @param array<int,string> $allowedKeys
     * @return array<string,int>
     */
    public function adminNavigationBadgeCounts(array $allowedKeys): array
    {
        if (!$allowedKeys) {
            return [];
        }
        $this->bootActiveModules();
        $allowed = array_fill_keys($allowedKeys, true);
        $counts = [];
        foreach ($this->instances as $slug => $instance) {
            if (!$instance instanceof ModuleAdminNavigationBadgeProviderInterface) {
                continue;
            }
            $ownedKeys = [];
            try {
                foreach ($instance->adminMenu() as $index => $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $route = $this->normalizeRoute((string)($item['route'] ?? ''));
                    $key = trim((string)($item['key'] ?? '')) ?: ('module:' . $slug . ':' . $index);
                    $cap = (string)($item['capability'] ?? '');
                    if ($route !== '' && str_starts_with($key, 'module:' . $slug) && isset($allowed[$key])
                        && CapabilityRegistry::isAllowedForModule($slug, $cap)) {
                        $ownedKeys[$key] = true;
                    }
                }
                if (!$ownedKeys) {
                    continue;
                }
                $supplied = $instance->adminNavigationBadges(array_keys($ownedKeys));
                foreach ($supplied as $key => $value) {
                    if (is_string($key) && isset($ownedKeys[$key]) && is_int($value) && $value > 0) {
                        $counts[$key] = $value;
                    }
                }
            } catch (Throwable $e) {
                error_log('Admin menu badge provider [' . $slug . ']: ' . $e->getMessage());
            }
        }
        return $counts;
    }

    /**
     * Resolve an active module route without letting inactive module PHP run.
     * Returns the module instance and a validated route descriptor.
     */
    public function resolveAdminRoute(string $route): ?array
    {
        $route = $this->normalizeRoute($route);
        if ($route === '') {
            return null;
        }

        $this->bootActiveModules();

        foreach ($this->instances as $slug => $instance) {
            try {
                $registrations = $instance->adminRoutes();
            } catch (Throwable $e) {
                error_log('Module admin routes failed [' . $slug . ']: ' . $e->getMessage());
                continue;
            }

            foreach ($registrations as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $registeredRoute = $this->normalizeRoute((string)($item['route'] ?? ''));
                if ($registeredRoute === '' || $registeredRoute !== $route) {
                    continue;
                }

                $capability = trim((string)($item['capability'] ?? ''));
                if (!CapabilityRegistry::isAllowedForModule($slug, $capability)) {
                    error_log('Module route is missing a registered capability [' . $slug . ':' . $registeredRoute . ']');
                    continue;
                }
                $handler = trim((string)($item['handler'] ?? ''));
                if ($handler === '' || !is_callable([$instance, $handler])) {
                    error_log('Invalid module route handler [' . $slug . ':' . $registeredRoute . ']');
                    continue;
                }

                $methods = array_map(
                    static fn($method): string => strtoupper(trim((string)$method)),
                    (array)($item['methods'] ?? ['GET'])
                );
                $methods = array_values(array_filter($methods, static fn(string $method): bool => $method !== ''));
                $methods = array_values(array_unique($methods));
                if (!$methods || array_diff($methods, ['GET', 'POST'])) {
                    error_log('Module route has invalid HTTP methods [' . $slug . ':' . $registeredRoute . ']');
                    continue;
                }

                return [
                    'module_slug' => $slug,
                    'instance' => $instance,
                    'context' => $this->contexts[$slug] ?? null,
                    'route' => $registeredRoute,
                    'methods' => $methods,
                    'capability' => $capability,
                    'handler' => $handler,
                    'nav_key' => trim((string)($item['nav_key'] ?? '')) ?: ('module:' . $slug . ':' . $index),
                ];
            }
        }

        return null;
    }

    public function invokeAdminRoute(array $resolved, array $runtime = []): void
    {
        $instance = $resolved['instance'] ?? null;
        $handler = (string)($resolved['handler'] ?? '');
        if (!$instance instanceof ModuleInterface || $handler === '' || !is_callable([$instance, $handler])) {
            throw new RuntimeException('Module-routen kunne ikke køres.');
        }
        $instance->{$handler}($runtime);
    }

    /**
     * Dynamic components registered by active modules.
     *
     * Modules only expose local keys; core namespaces every key with the
     * module slug so two modules cannot accidentally register the same name.
     *
     * @return array<string,string>
     */
    public function componentOptions(): array
    {
        $this->bootActiveModules();
        $options = [];

        foreach ($this->instances as $slug => $instance) {
            if (!$instance instanceof ModuleComponentProviderInterface) {
                continue;
            }

            try {
                foreach ($instance->componentOptions() as $localKey => $label) {
                    $localKey = $this->normalizeExtensionKey((string)$localKey);
                    $label = trim((string)$label);
                    if ($localKey === '' || $label === '') {
                        continue;
                    }
                    $options[$slug . ':' . $localKey] = $label;
                }
            } catch (Throwable $e) {
                error_log('Module component options failed [' . $slug . ']: ' . $e->getMessage());
            }
        }

        return $options;
    }

    /** @return array<int,string> */
    public function frontendScriptUrls(): array
    {
        $this->bootActiveModules();
        $urls = [];

        foreach ($this->instances as $slug => $instance) {
            if (!$instance instanceof ModuleFrontendScriptProviderInterface) {
                continue;
            }

            try {
                $scripts = $instance->frontendScripts();
            } catch (Throwable $e) {
                error_log('Module frontend scripts failed [' . $slug . ']: ' . $e->getMessage());
                continue;
            }

            foreach ((array)$scripts as $relativePath) {
                $relativePath = $this->normalizeAssetPath((string)$relativePath);
                if ($relativePath === '' || strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) !== 'js') {
                    continue;
                }

                $asset = $this->resolvePublicScript($slug, $relativePath);
                if ($asset === null) {
                    continue;
                }

                $encoded = implode('/', array_map('rawurlencode', explode('/', $relativePath)));
                $urls[] = '/assets/modules/' . rawurlencode($slug) . '/' . $encoded . '?v=' . (int)$asset['mtime'];
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Resolve one public JavaScript file below a module's assets/ directory.
     * CSS is deliberately unsupported: frontend presentation is owned by the
     * frontend theme, not by modules.
     *
     * @return array{path:string,mime:string,mtime:int}|null
     */
    public function resolvePublicScript(string $slug, string $relativePath): ?array
    {
        $slug = $this->normalizeSlug($slug);
        $relativePath = $this->normalizeAssetPath($relativePath);
        if ($slug === '' || $relativePath === '') {
            return null;
        }

        if (strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) !== 'js') {
            return null;
        }

        $rows = $this->databaseRows();
        if (empty($rows[$slug]['is_active'])) {
            return null;
        }

        $module = $this->discover()[$slug] ?? null;
        if (!$module || empty($module['valid']) || $this->compatibilityError($module) !== null) {
            return null;
        }

        $assetBase = realpath($this->modulesPath . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'assets');
        if ($assetBase === false || !is_dir($assetBase)) {
            return null;
        }

        $candidate = $assetBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $realFile = realpath($candidate);
        if ($realFile === false || !is_file($realFile)) {
            return null;
        }

        $prefix = rtrim($assetBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($realFile, $prefix)) {
            return null;
        }

        $mtime = filemtime($realFile);
        return [
            'path' => $realFile,
            'mime' => 'application/javascript; charset=UTF-8',
            'mtime' => $mtime === false ? time() : (int)$mtime,
        ];
    }

    /**
     * Resolve public content supplied by active modules. Called only when no
     * core Page record matches, so existing pages always keep their routes.
     */
    public function resolvePublicPage(string $path): ?array
    {
        $this->bootActiveModules();
        foreach ($this->instances as $slug => $instance) {
            if (!$instance instanceof ModulePublicPageInterface) {
                continue;
            }
            try {
                $page = $instance->resolvePublicPage($path);
                if ($page === null) {
                    continue;
                }
                $page['_module_public'] = $slug;
                return $page;
            } catch (Throwable $e) {
                error_log('Public module route failed [' . $slug . ']: ' . $e->getMessage());
                if (!AppConfig::isProduction()) {
                    throw $e;
                }
            }
        }
        return null;
    }

    /** Output a previously resolved public module page. */
    public function renderPublicPage(array $page): bool
    {
        $slug = (string)($page['_module_public'] ?? '');
        $this->bootActiveModules();
        $instance = $this->instances[$slug] ?? null;
        if (!$instance instanceof ModulePublicPageInterface) {
            return false;
        }
        $instance->renderPublicPage($page);
        return true;
    }

    /** Render a namespaced module component. */
    public function renderComponent(string $component, array $modifiers = []): bool
    {
        [$slug, $localKey] = $this->splitExtensionKey($component);
        if ($slug === '' || $localKey === '') {
            return false;
        }

        $this->bootActiveModules();
        $instance = $this->instances[$slug] ?? null;
        if (!$instance instanceof ModuleComponentProviderInterface) {
            return false;
        }

        try {
            return $instance->renderComponent($localKey, $modifiers);
        } catch (Throwable $e) {
            error_log('Module component render failed [' . $slug . ':' . $localKey . ']: ' . $e->getMessage());
            if (!AppConfig::isProduction()) {
                throw $e;
            }
            return false;
        }
    }

    /**
     * Dispatch an explicit frontend action such as "forms:submit".
     * Returns false when no active module owns the action.
     */
    public function handleFrontendAction(string $action, array $runtime = []): bool
    {
        [$slug, $localAction] = $this->splitExtensionKey($action);
        if ($slug === '' || $localAction === '') {
            return false;
        }

        $this->bootActiveModules();
        $instance = $this->instances[$slug] ?? null;
        if (!$instance instanceof ModuleFrontendActionInterface) {
            return false;
        }

        $method = strtoupper((string)($runtime['method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET')));

        foreach ($instance->frontendActions() as $registration) {
            if (!is_array($registration)) {
                continue;
            }
            $registeredAction = $this->normalizeExtensionKey((string)($registration['action'] ?? ''));
            if ($registeredAction === '' || $registeredAction !== $localAction) {
                continue;
            }

            $methods = array_values(array_filter(array_map(
                static fn($value): string => strtoupper(trim((string)$value)),
                (array)($registration['methods'] ?? ['POST'])
            )));
            if (!$methods) {
                $methods = ['POST'];
            }
            if (!in_array($method, $methods, true)) {
                http_response_code(405);
                header('Allow: ' . implode(', ', $methods));
                return true;
            }

            $handler = trim((string)($registration['handler'] ?? ''));
            if ($handler === '' || !is_callable([$instance, $handler])) {
                error_log('Invalid module frontend action handler [' . $slug . ':' . $localAction . ']');
                return false;
            }

            $runtime['action'] = $localAction;
            $instance->{$handler}($runtime);
            return true;
        }

        return false;
    }

    /** Active modules that expose scheduled actions. Core never reads module tables directly. */
    public function scheduledContentProviders(): array
    {
        $this->bootActiveModules();
        return array_values(array_filter(
            $this->instances,
            static fn($instance): bool => $instance instanceof ModuleScheduledContentProviderInterface
        ));
    }

    /**
     * Public sitemap data from active modules. All paths are local, canonical
     * and validated. One malfunctioning module cannot break the core sitemap.
     * @return array<int,array{path:string,lastmod:?string}>
     */
    public function sitemapEntries(): array
    {
        $this->bootActiveModules();
        $entries = [];
        foreach ($this->instances as $slug => $instance) {
            if (!$instance instanceof ModuleSitemapProviderInterface) continue;
            try {
                foreach ($instance->sitemapEntries() as $entry) {
                    if (!is_array($entry)) continue;
                    $path = (string)($entry['path'] ?? '');
                    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')
                        || str_contains($path, '?') || str_contains($path, '#')
                        || preg_match('/[\x00-\x1F\x7F]/', $path)
                        || strlen($path) > 2000) continue;
                    $lastmod = $entry['lastmod'] ?? null;
                    $lastmod = is_string($lastmod) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $lastmod)
                        ? $lastmod : null;
                    $entries[$path] = ['path' => $path, 'lastmod' => $lastmod];
                    if (count($entries) >= 45000) break 2;
                }
            } catch (Throwable $e) {
                error_log('Module sitemap failed [' . $slug . ']: ' . $e->getMessage());
            }
        }
        return array_values($entries);
    }

    public function runtimeErrors(): array
    {
        $this->bootActiveModules();
        return $this->runtimeErrors;
    }

    /** Explicit installer entry point; never invoked by a web request. */
    public static function installSchema(Db $db): void
    {
        $db->query(
            "CREATE TABLE IF NOT EXISTS modules (\n"
            . "id int NOT NULL AUTO_INCREMENT,\n"
            . "module_slug varchar(100) NOT NULL,\n"
            . "module_name varchar(191) NOT NULL,\n"
            . "installed_version varchar(50) NOT NULL DEFAULT '',\n"
            . "is_active tinyint(1) NOT NULL DEFAULT 0,\n"
            . "activated_at datetime DEFAULT NULL,\n"
            . "created_at datetime DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY module_slug (module_slug),\n"
            . "KEY modules_active (is_active)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function syncDiscovered(): void
    {
        $rows = $this->databaseRows();
        foreach ($this->discover() as $slug => $module) {
            if (empty($module['valid'])) {
                continue;
            }

            $row = $rows[$slug] ?? null;
            if (is_array($row)) {
                // NEVER acknowledge a new code version during read-only metadata sync.
                // installed_version is the last explicit activation/update checkpoint.
                if ((string)($row['module_name'] ?? '') !== (string)$module['name']) {
                    $this->db->query(
                        'UPDATE modules SET module_name = ?, updated_at = NOW() WHERE module_slug = ?',
                        [(string)$module['name'], $slug]
                    );
                }
            } else {
                $this->db->query(
                    'INSERT INTO modules (module_slug, module_name, installed_version, is_active, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW())',
                    [$slug, (string)$module['name'], (string)$module['version']]
                );
            }
        }
        // A subsequent allModules()/boot call must see the persisted state.
        $this->registeredRows = null;
    }

    /** @return array<string,array<string,mixed>> */
    private function discover(): array
    {
        if ($this->discovered !== null) {
            return $this->discovered;
        }

        $this->discovered = [];
        if (!is_dir($this->modulesPath)) {
            return $this->discovered;
        }

        $directories = glob($this->modulesPath . '/*', GLOB_ONLYDIR) ?: [];
        sort($directories, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($directories as $directory) {
            $folderSlug = $this->normalizeSlug(basename($directory));
            if ($folderSlug === '') {
                continue;
            }

            $manifestFile = $directory . '/module.json';
            if (!is_file($manifestFile)) {
                $this->discovered[$folderSlug] = $this->invalidManifest($folderSlug, 'module.json mangler.');
                continue;
            }

            try {
                $this->discovered[$folderSlug] = ModuleManifestValidator::load($directory);
                Translator::registerModule($folderSlug, $directory);
            } catch (Throwable $e) {
                $this->discovered[$folderSlug] = $this->invalidManifest($folderSlug, $e->getMessage());
            }
        }

        return $this->discovered;
    }

    private function invalidManifest(string $slug, string $message): array
    {
        return [
            'slug' => $slug,
            'name' => ucfirst(str_replace(['-', '_'], ' ', $slug)),
            'version' => '',
            'description' => '',
            'author' => '',
            'requires' => '',
            'requires_php' => '',
            'entry' => '',
            'class' => '',
            'valid' => false,
            'error' => $message,
        ];
    }

    private function compatibilityError(array $module): ?string
    {
        $requires = trim((string)($module['requires'] ?? ''));
        $coreVersion = defined('BOILERPLATE_VERSION') ? (string)BOILERPLATE_VERSION : '0.0.0';
        if ($requires !== '' && version_compare($coreVersion, $requires, '<')) {
            return 'Kræver Boilerplate ' . $requires . ' eller nyere.';
        }

        $requiresPhp = trim((string)($module['requires_php'] ?? ''));
        if ($requiresPhp !== '' && version_compare(PHP_VERSION, $requiresPhp, '<')) {
            return 'Kræver PHP ' . $requiresPhp . ' eller nyere.';
        }

        return null;
    }

    private function loadInstance(array $module, bool $boot): ModuleInterface
    {
        $slug = (string)$module['slug'];
        if (isset($this->instances[$slug])) {
            return $this->instances[$slug];
        }

        require_once (string)$module['entry_path'];
        $class = (string)$module['class'];
        if (!class_exists($class, false)) {
            throw new RuntimeException('Module-klassen ' . $class . ' blev ikke fundet.');
        }

        $instance = new $class();
        if (!$instance instanceof ModuleInterface) {
            throw new RuntimeException('Module-klassen skal implementere ModuleInterface.');
        }

        if (!$boot) {
            return $instance;
        }

        $context = new ModuleContext($module, (string)$module['base_path'], $this->hooks);
        $instance->boot($context);
        $this->instances[$slug] = $instance;
        $this->contexts[$slug] = $context;
        return $instance;
    }

    /** @return array<string,array<string,mixed>> */
    private function databaseRows(): array
    {
        if ($this->registeredRows !== null) {
            return $this->registeredRows;
        }
        $rows = $this->db->fetchArray('SELECT * FROM modules ORDER BY module_name, module_slug');
        $result = [];
        foreach ($rows as $row) {
            $slug = $this->normalizeSlug((string)($row['module_slug'] ?? ''));
            if ($slug !== '') {
                $result[$slug] = $row;
            }
        }
        return $this->registeredRows = $result;
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        return preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', $slug) ? $slug : '';
    }

    private function normalizeRoute(string $route): string
    {
        $route = strtolower(trim($route, " \t\n\r\0\x0B/"));
        if ($route === '' || strlen($route) > 180) {
            return '';
        }
        return preg_match('#^[a-z0-9][a-z0-9_/-]*$#', $route) ? $route : '';
    }

    private function normalizeAssetPath(string $value): string
    {
        $value = trim(str_replace('\\', '/', $value));
        $value = ltrim($value, '/');
        if ($value === '' || str_contains($value, '../') || str_contains($value, './')) {
            return '';
        }
        if (!preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#', $value)) {
            return '';
        }
        return $value;
    }

    private function normalizeExtensionKey(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '' || strlen($value) > 120) {
            return '';
        }
        return preg_match('/^[a-z0-9][a-z0-9_.-]*$/', $value) ? $value : '';
    }

    /** @return array{0:string,1:string} */
    private function splitExtensionKey(string $value): array
    {
        $parts = explode(':', strtolower(trim($value)), 2);
        if (count($parts) !== 2) {
            return ['', ''];
        }
        return [$this->normalizeSlug($parts[0]), $this->normalizeExtensionKey($parts[1])];
    }

}
