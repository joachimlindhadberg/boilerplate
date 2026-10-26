<?php

declare(strict_types=1);

/**
 * Read-only codebase and release-structure audit.
 *
 * php core/cli/code-audit.php
 * php core/cli/code-audit.php --json
 * php core/cli/code-audit.php --release  (strict ZIP/distribution check)
 *
 * Does not connect to MySQL, remove files, or modify the installation.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$flags = array_slice($argv, 1);
if (count(array_diff($flags, ['--json', '--release', '--help'])) > 0) {
    fwrite(STDERR, "Usage: php core/cli/code-audit.php [--json] [--release|--help]\n");
    exit(2);
}
if (in_array('--help', $flags, true)) {
    echo "Read-only code audit: php core/cli/code-audit.php [--json] [--release]\n";
    exit;
}

$root = dirname(__DIR__, 2);
$releaseMode = in_array('--release', $flags, true);
$checks = [];
$notes = [];
$check = static function (bool $ok, string $label, string $detail = '') use (&$checks): void {
    $checks[] = ['status' => $ok ? 'PASS' : 'FAIL', 'name' => $label, 'detail' => $detail];
};

$allFiles = [];
$phpFiles = [];
$hashGroups = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($iterator as $file) {
    if ($file->isLink()) {
        $check(false, 'No symlinks in release', substr($file->getPathname(), strlen($root) + 1));
        continue;
    }
    if (!$file->isFile()) {
        continue;
    }
    $relative = substr($file->getPathname(), strlen($root) + 1);
    // .env belongs to an installed CMS, never to a distribution. Do not hash,
    // read or report its contents when auditing a live installation.
    if ($relative === '.env') {
        continue;
    }
    // Finder and macOS may create these files inside a live installation.
    // Ignore them during an installation audit; release audits must still
    // detect and reject them. Skip before hashing to avoid reading their data.
    $macMetadata = preg_match('~(?:^|/)__MACOSX(?:/|$)|(?:^|/)\.DS_Store$|(?:^|/)\._[^/]+$~', $relative) === 1;
    if ($macMetadata && !$releaseMode) {
        continue;
    }
    $allFiles[] = $relative;
    // Live uploads/runtime storage may legitimately contain identical files.
    // Duplicate detection is for shipped source files, not user-owned content.
    $runtimeContent = preg_match('~^(?:public_html/(?:uploads|assets/uploads)/|storage/|cache/)~', $relative) === 1;
    // ZIP deployments do not delete the Forms module's old content views.
    // They may duplicate the new partials byte-for-byte on an installed CMS.
    $oldFormsView = !$releaseMode && (
        $relative === 'modules/forms/admin/views/form.php'
        || str_starts_with($relative, 'modules/forms/admin/views/content/')
    );
    if ($releaseMode || (!$runtimeContent && !$oldFormsView)) {
        $hashGroups[hash_file('sha256', $file->getPathname())][] = $relative;
    }
    if (str_ends_with($relative, '.php')) {
        $phpFiles[] = $relative;
    }
}
sort($allFiles);

$check(!$releaseMode || !is_file($root . '/.env'),
    $releaseMode ? 'Release ZIP excludes private .env' : 'Installed .env allowed and not inspected');
$systemArtifacts = array_values(array_filter($allFiles, static fn(string $path): bool =>
    preg_match('~(?:^|/)__MACOSX(?:/|$)|(?:^|/)\.DS_Store$|(?:^|/)\._[^/]+$~', $path) === 1
));
$check(
    $systemArtifacts === [],
    $releaseMode ? 'Release ZIP excludes macOS metadata' : 'Installed macOS metadata ignored',
    implode(', ', $systemArtifacts)
);

$expectedPages = [
    'index' => 'GET /admin/sider', 'view' => 'GET ?mode=view',
    'new' => 'GET ?mode=new', 'edit' => 'GET ?mode=edit',
    'history' => 'GET ?mode=history', 'revision' => 'GET ?mode=revision',
];
$controller = (string)@file_get_contents($root . '/public_html/admin/pages.php');
foreach ($expectedPages as $name => $route) {
    $relative = 'public_html/admin/views/pages/' . $name . '.php';
    $check(is_file($root . '/' . $relative), 'Pages view: ' . $name, $route);
    $check(str_contains($controller, "admin_render('pages/" . $name . "'"), 'Pages controller mapping: ' . $name);
}
foreach (['form-fields', 'delete-panel', 'delete-modal'] as $name) {
    $check(is_file($root . '/public_html/admin/views/pages/partials/' . $name . '.php'), 'Pages partial: ' . $name);
}
$oldPages = ['pages.php', 'page-detail.php', 'page-form.php', 'page-history.php', 'page-revision.php'];
$stale = [];
foreach ($oldPages as $name) {
    if (is_file($root . '/public_html/admin/views/' . $name)) {
        $stale[] = $name;
    }
}
$check($stale === [], 'Legacy top-level Pages views removed', implode(', ', $stale));

// Media uses a single index view plus the upload and detail modal partials.
// The picker, upload, update and delete actions remain inside the controller.
$mediaController = (string)@file_get_contents($root . '/public_html/admin/media.php');
$check(
    str_contains($mediaController, "admin_render('media/index'"),
    'Media controller maps to index view'
);
$check(
    is_file($root . '/public_html/admin/views/media/index.php'),
    'Media index view exists'
);
foreach (['upload-dialog', 'detail-dialog'] as $name) {
    $check(
        is_file($root . '/public_html/admin/views/media/partials/' . $name . '.php'),
        'Media partial: ' . $name
    );
}
$check(
    !is_file($root . '/public_html/admin/views/media.php'),
    'Legacy top-level Media view removed'
);

// Navigation keeps the existing list/new/edit/delete controller modes and routes.
// Each mode now has its own view, with shared header and menu-item partials.
$navigationController = (string)@file_get_contents($root . '/public_html/admin/navigation.php');
foreach (['index', 'new', 'edit', 'delete'] as $name) {
    $check(
        str_contains($navigationController, "'navigation/" . $name . "'"),
        'Navigation controller mapping: ' . $name
    );
    $check(
        is_file($root . '/public_html/admin/views/navigation/' . $name . '.php'),
        'Navigation view: ' . $name
    );
}
foreach (['context', 'header'] as $name) {
    $check(
        is_file($root . '/public_html/admin/views/navigation/partials/' . $name . '.php'),
        'Navigation shared partial: ' . $name
    );
}
$check(
    !is_file($root . '/public_html/admin/views/navigation.php'),
    'Legacy top-level Navigation view removed'
);

// Users uses four GET views while POST actions and shared delete confirmation
// continue to be handled by the existing controller. Create and edit share form fields.
$usersController = (string)@file_get_contents($root . '/public_html/admin/users.php');
foreach (['index', 'view', 'new', 'edit'] as $name) {
    $check(
        str_contains($usersController, "admin_render('users/" . $name . "'"),
        'Users controller mapping: ' . $name
    );
    $check(
        is_file($root . '/public_html/admin/views/users/' . $name . '.php'),
        'Users view: ' . $name
    );
}
foreach (['form-context', 'form-fields'] as $name) {
    $check(
        is_file($root . '/public_html/admin/views/users/partials/' . $name . '.php'),
        'Users shared partial: ' . $name
    );
}
$legacyUsers = array_values(array_filter(['users.php', 'user-detail.php', 'user-form.php'],
    static fn(string $name): bool => is_file($root . '/public_html/admin/views/' . $name)
));
$check($legacyUsers === [], 'Legacy top-level Users views removed', implode(', ', $legacyUsers));

// Modules has a single GET index view and retains POST-only activate/deactivate/update
// modes in the controller. Modal dialogs are dedicated view partials.
$modulesController = (string)@file_get_contents($root . '/public_html/admin/modules.php');
$check(
    str_contains($modulesController, "admin_render('modules/index'"),
    'Modules controller maps to index view'
);
$check(
    is_file($root . '/public_html/admin/views/modules/index.php'),
    'Modules index view exists'
);
foreach (['module-list', 'deactivate-dialog', 'update-dialog'] as $name) {
    $path = $root . '/public_html/admin/views/modules/partials/' . $name . '.php';
    $check(is_file($path), 'Modules partial: ' . $name);
    $index = (string)@file_get_contents($root . '/public_html/admin/views/modules/index.php');
    $check(str_contains($index, "'/partials/" . $name . ".php'"), 'Modules index includes partial: ' . $name);
}
$check(
    !is_file($root . '/public_html/admin/views/modules.php'),
    'Legacy top-level Modules view removed'
);

// Settings is a single GET form. POST validation and persistence remain in the
// controller; individual panels are presentation-only partials.
$settingsController = (string)@file_get_contents($root . '/public_html/admin/settings.php');
$check(
    str_contains($settingsController, "admin_render('settings/index'"),
    'Settings controller maps to index view'
);
$settingsIndexPath = $root . '/public_html/admin/views/settings/index.php';
$check(is_file($settingsIndexPath), 'Settings index view exists');
$settingsIndex = (string)@file_get_contents($settingsIndexPath);
foreach (['language', 'website', 'seo', 'notifications', 'maintenance', 'session'] as $name) {
    $path = $root . '/public_html/admin/views/settings/partials/' . $name . '.php';
    $check(is_file($path), 'Settings partial: ' . $name);
    $check(
        str_contains($settingsIndex, "'/partials/" . $name . ".php'"),
        'Settings index includes partial: ' . $name
    );
}
$check(
    !is_file($root . '/public_html/admin/views/settings.php'),
    'Legacy top-level Settings view removed'
);

// Tools has three GET views (index, redirects and activity); all POST/streaming
// operations remain in the original controller. The index uses five presentation
// partials and its routes and form names are unchanged.
$toolsController = (string)@file_get_contents($root . '/public_html/admin/tools.php');
foreach (['index', 'redirects', 'activity'] as $name) {
    $check(
        str_contains($toolsController, "admin_render('tools/" . $name . "'"),
        'Tools controller mapping: ' . $name
    );
    $check(
        is_file($root . '/public_html/admin/views/tools/' . $name . '.php'),
        'Tools view: ' . $name
    );
}
$toolsIndexPath = $root . '/public_html/admin/views/tools/index.php';
$toolsIndex = (string)@file_get_contents($toolsIndexPath);
foreach (['health', 'redirects-card', 'activity-card', 'import', 'export'] as $name) {
    $check(
        is_file($root . '/public_html/admin/views/tools/partials/' . $name . '.php'),
        'Tools partial: ' . $name
    );
    $check(
        str_contains($toolsIndex, "'/partials/" . $name . ".php'"),
        'Tools index includes partial: ' . $name
    );
}
$legacyTools = array_values(array_filter(['tools.php', 'redirects.php', 'activity.php'],
    static fn(string $name): bool => is_file($root . '/public_html/admin/views/' . $name)
));
$check($legacyTools === [], 'Legacy top-level Tools views removed', implode(', ', $legacyTools));

// V2.4.4q: cross-section architecture consistency. These tests inspect source
// only: no controller/bootstrap execution, DB access, filesystem mutation, or
// interpretation of untrusted admin input.
$expectedCoreViews = [
    'pages' => ['index', 'view', 'new', 'edit', 'history', 'revision'],
    'media' => ['index'],
    'navigation' => ['index', 'new', 'edit', 'delete'],
    'users' => ['index', 'view', 'new', 'edit'],
    'modules' => ['index'],
    'settings' => ['index'],
    'tools' => ['index', 'redirects', 'activity'],
];
foreach ($expectedCoreViews as $section => $views) {
    $controllerPath = $root . '/public_html/admin/' . $section . '.php';
    $source = (string)@file_get_contents($controllerPath);
    $check(
        is_file($controllerPath) && str_contains($source, "require_once __DIR__ . '/incl/module-bootstrap.php'"),
        'Core ' . $section . ': shared security bootstrap'
    );
    foreach ($views as $view) {
        $relative = 'public_html/admin/views/' . $section . '/' . $view . '.php';
        $contents = (string)@file_get_contents($root . '/' . $relative);
        $check(
            is_file($root . '/' . $relative) &&
            str_contains($contents, "require __DIR__ . '/../partials/layout.php'"),
            'Core ' . $section . '/' . $view . ': shared layout'
        );
    }
}
// Navigation chooses a view through a match expression rather than through
// individual literal admin_render calls. Check that dispatch explicitly uses it.
$check(
    str_contains($navigationController, 'admin_render($navigationView,') &&
    str_contains($navigationController, 'match ($mode)'),
    'Navigation dynamic mode-to-view dispatch'
);
// Verify that all literal __DIR__ includes/requirements in Core admin views
// resolve to actual files, including their shared layout and modal partials.
// Computed PHP includes remain outside the scope of this static check.
$missingIncludes = [];
$resolvedIncludes = 0;
foreach ($phpFiles as $relative) {
    if (!str_starts_with($relative, 'public_html/admin/views/')) {
        continue;
    }
    $source = (string)@file_get_contents($root . '/' . $relative);
    preg_match_all('~\b(?:require|include)(?:_once)?\s*(?:\(\s*)?__DIR__\s*\.\s*[\'"]([^\'"]+)[\'"]~', $source, $matches);
    foreach ($matches[1] as $target) {
        ++$resolvedIncludes;
        if (!is_file(dirname($root . '/' . $relative) . '/' . $target)) {
            $missingIncludes[] = $relative . ' -> ' . $target;
        }
    }
}
$check($resolvedIncludes > 0 && $missingIncludes === [],
    'Core view literal includes resolve (' . $resolvedIncludes . ' references)',
    implode('; ', $missingIncludes));

// Check the clean URL rewrites for the six mapped sections; Navigation's
// existing route is left untouched (it uses a different dispatch pathway).
$rewriteRules = (string)@file_get_contents($root . '/public_html/.htaccess');
foreach ([
    'medier' => 'media', 'sider' => 'pages', 'modules' => 'modules',
    'brugere' => 'users', 'vaerktoejer' => 'tools', 'indstillinger' => 'settings',
] as $route => $controllerName) {
    $check(
        str_contains($rewriteRules, '^admin/' . $route . '/?$ admin/' . $controllerName . '.php'),
        'Core clean route: /admin/' . $route
    );
}
$check(
    str_contains($rewriteRules, '^admin/vaerktoejer/redirects/?$ admin/tools.php?mode=redirects'),
    'Core Tools nested route: /admin/vaerktoejer/redirects'
);
$check(
    str_contains($rewriteRules, '^admin/(?:incl|views)(?:/|$) - [F,L,NC]'),
    'Core views and includes are blocked from direct HTTP access'
);

// Check all literal admin_render calls without executing controllers or relying on MySQL.
$missingViewCalls = [];
foreach ($phpFiles as $relative) {
    if (!str_starts_with($relative, 'public_html/admin/')) {
        continue;
    }
    $source = (string)file_get_contents($root . '/' . $relative);
    preg_match_all('~\badmin_render\(\s*[\'\"]([a-zA-Z0-9_/-]+)[\'\"]~', $source, $matches);
    foreach ($matches[1] as $view) {
        if (str_contains($view, '..') || !is_file($root . '/public_html/admin/views/' . $view . '.php')) {
            $missingViewCalls[] = $relative . ': ' . $view;
        }
    }
}
$check($missingViewCalls === [], 'Static Core admin view references resolve', implode('; ', $missingViewCalls));

// Check module entry points and literal renderAdmin view references. Dynamic paths require manual review.
$missingModuleFiles = [];
$moduleCount = 0;
foreach (glob($root . '/modules/*', GLOB_ONLYDIR) ?: [] as $directory) {
    $moduleCount++;
    $manifest = json_decode((string)@file_get_contents($directory . '/module.json'), true);
    if (!is_array($manifest) || !is_string($manifest['entry'] ?? null) || !is_file($directory . '/' . $manifest['entry'])) {
        $missingModuleFiles[] = basename($directory) . ': missing/invalid module entry';
        continue;
    }
    foreach (glob($directory . '/*.php') ?: [] as $php) {
        $source = (string)file_get_contents($php);
        preg_match_all('~\brenderAdmin\(\s*[\'\"]([^\'\"]+)[\'\"]~', $source, $matches);
        foreach ($matches[1] as $view) {
            if (str_contains($view, '..') || !is_file($directory . '/' . $view)) {
                $missingModuleFiles[] = basename($directory) . ': ' . $view;
            }
        }
    }
}
$check($missingModuleFiles === [], 'Module entry points and static admin views', implode('; ', $missingModuleFiles));

// Forms is the first module to use the Core-style controller/view layout.
// The registry still calls FormsModule's public handlers through the trait.
$formsRoot = $root . '/modules/forms';
$formsSource = (string)@file_get_contents($formsRoot . '/FormsModule.php');
$formsControllerPath = $formsRoot . '/admin/controllers/FormsAdminController.php';
$formsController = (string)@file_get_contents($formsControllerPath);
$formsWorkspace = (string)@file_get_contents($formsRoot . '/admin/workspace.php');
$check(is_file($formsControllerPath) && str_contains($formsController, 'trait FormsAdminController')
    && str_contains($formsSource, 'use FormsAdminController;')
    && str_contains($formsSource, "require_once __DIR__ . '/admin/controllers/FormsAdminController.php';"),
    'Forms admin controller composed into module entrypoint');
foreach (['adminForms', 'adminSettings', 'adminSubmissions', 'adminSubmission'] as $handler) {
    $check(str_contains($formsController, 'function ' . $handler . '(')
        && !str_contains($formsSource, 'function ' . $handler . '('),
        'Forms controller handler: ' . $handler);
}
$check(str_contains($formsController, "renderAdmin('admin/views/' . (\$screen === 'edit' ? 'edit' : 'new') . '.php'")
    && str_contains($formsController, "renderAdmin('admin/views/index.php'")
    && str_contains($formsController, "renderAdmin('admin/views/submissions.php'")
    && str_contains($formsController, "renderAdmin('admin/views/submission.php'")
    && str_contains($formsController, "renderAdmin('admin/views/settings.php'"),
    'Forms controller view routing');
foreach (['index', 'new', 'edit', 'submissions', 'submission', 'settings'] as $screen) {
    $path = $formsRoot . '/admin/views/' . $screen . '.php';
    $source = (string)@file_get_contents($path);
    $check(is_file($path) && str_contains($source, "'/workspace.php'")
        && str_contains($source, "('" . $screen . "', get_defined_vars())"),
        'Forms admin view: ' . $screen);
}
foreach ([
    'form-editor' => ['new', 'edit'], 'forms-list' => ['index'],
    'submissions-list' => ['submissions'], 'submission-detail' => ['submission'],
    'settings-fields' => ['settings'],
] as $partial => $screens) {
    $check(is_file($formsRoot . '/admin/views/partials/' . $partial . '.php')
        && str_contains($formsWorkspace, "'" . $partial . "'"),
        'Forms shared partial: ' . $partial);
}
$formRepo = (string)@file_get_contents($formsRoot . '/FormsRepository.php');
$formEditor = (string)@file_get_contents($formsRoot . '/admin/views/partials/form-editor.php');
$check(str_contains($formsController, "(string)(\$_POST['form_action'] ?? '') === 'delete'")
    && str_contains($formsController, 'admin_verify_csrf')
    && str_contains($formsController, 'deleteForm($id)'),
    'Forms delete controller: POST + CSRF + repository');
$check(str_contains($formRepo, 'function formDeleteDependencies(')
    && str_contains($formRepo, 'function deleteForm(')
    && str_contains($formRepo, 'FOR UPDATE')
    && str_contains($formRepo, 'module_form_submissions'),
    'Forms delete repository: submission and Page Builder protection');
$check(str_contains($formEditor, 'data-admin-open-delete="forms-delete-dialog"')
    && str_contains($formEditor, 'AdminDeleteDialog::render(')
    && str_contains($formEditor, "'id' => 'forms-delete-dialog'")
    && str_contains($formEditor, "'confirm_delete' => 'permanent'")
    && str_contains($formEditor, "'form_action' => 'delete'"),
    'Forms edit delete panel and confirmation modal');
$legacyForms = [];
if (is_file($formsRoot . '/admin/views/form.php')) $legacyForms[] = 'admin/views/form.php';
if (is_dir($formsRoot . '/admin/views/content')) $legacyForms[] = 'admin/views/content/';
$check(!$releaseMode || $legacyForms === [],
    $releaseMode ? 'Forms legacy adapters excluded from release'
                 : 'Forms legacy adapters ignored during installed audit',
    implode(', ', $legacyForms));
if (!$releaseMode && $legacyForms !== []) {
    $notes[] = 'Forms old view adapters remain on disk after a ZIP merge; after backup and functional testing they may be removed: ' . implode(', ', $legacyForms) . '.';
}

// Gallery admin requests use a trait; the Core module registry still dispatches
// to GalleryModule::adminGalleries without changing route names or capabilities.
$galleryRoot = $root . '/modules/gallery';
$gallerySource = (string)@file_get_contents($galleryRoot . '/GalleryModule.php');
$galleryControllerPath = $galleryRoot . '/admin/controllers/GalleryAdminController.php';
$galleryController = (string)@file_get_contents($galleryControllerPath);
$check(is_file($galleryControllerPath)
    && str_contains($galleryController, 'trait GalleryAdminController')
    && str_contains($gallerySource, 'use GalleryAdminController;')
    && str_contains($gallerySource, "require_once __DIR__ . '/admin/controllers/GalleryAdminController.php';"),
    'Gallery admin controller composed into module entrypoint');
$check(str_contains($galleryController, 'function adminGalleries(array $runtime): void')
    && !str_contains($gallerySource, 'function adminGalleries('),
    'Gallery admin handler moved from entrypoint to controller');
$check(str_contains($gallerySource, "'route' => 'gallerier'")
    && str_contains($gallerySource, "'handler' => 'adminGalleries'")
    && str_contains($gallerySource, "'capability' => 'content.manage'"),
    'Gallery existing route and capability retained');
$check(str_contains($galleryController, "renderAdmin('admin/views/index.php'")
    && str_contains($galleryController, "renderAdmin('admin/views/' . (\$screen === 'edit' ? 'edit' : 'new') . '.php'"),
    'Gallery controller routes index, new and edit screens');
foreach (['index', 'new', 'edit'] as $screen) {
    $view = $galleryRoot . '/admin/views/' . $screen . '.php';
    $check(is_file($view), 'Gallery admin view: ' . $screen);
}
foreach (['gallery-editor', 'media-picker-dialog', 'delete-dialog', 'gallery-builder-script'] as $partial) {
    $check(is_file($galleryRoot . '/admin/views/partials/' . $partial . '.php'),
        'Gallery admin partial: ' . $partial);
}
$galleryEditor = (string)@file_get_contents($galleryRoot . '/admin/views/partials/gallery-editor.php');
$check(str_contains($galleryEditor, "'/media-picker-dialog.php'")
    && str_contains($galleryEditor, "'/delete-dialog.php'")
    && str_contains($galleryEditor, "'/gallery-builder-script.php'"),
    'Gallery editor includes media picker, delete dialog and builder script');
$check(str_contains($galleryController, 'admin_verify_csrf')
    && str_contains($galleryController, 'deleteGallery($id)')
    && str_contains($galleryController, 'saveGallery($id, $gallery, $mediaIds)'),
    'Gallery existing save/delete and CSRF actions preserved');
// V2.4.4s1: the installed PHP process can temporarily run the pre-migration
// GalleryModule.php after ZIP merge / OPcache reuse. Its handler loads form.php.
// Provide a small compatibility adapter to the canonical editor view instead
// of removing the path before deployed copies have switched handlers.
$galleryCompatPath = $galleryRoot . '/admin/views/form.php';
$galleryCompat = (string)@file_get_contents($galleryCompatPath);
$check(is_file($galleryCompatPath)
    && str_contains($galleryCompat, "require __DIR__ . '/partials/gallery-editor.php';")
    && !str_contains($galleryCompat, 'data-gallery-builder'),
    'Gallery legacy form route resolves to shared editor adapter');
$check(!str_contains($gallerySource, "renderAdmin('admin/views/form.php'")
    && !str_contains($galleryController, "renderAdmin('admin/views/form.php'"),
    'Gallery current controller never routes to legacy form.php',
    str_contains($gallerySource, "renderAdmin('admin/views/form.php'")
        ? 'Outdated GalleryModule.php detected. Replace modules/gallery/GalleryModule.php with the release version; restart Apache/PHP if OPcache still uses old code.'
        : '');

$adminScss = @file_get_contents($root . '/public_html/admin/assets/scss/admin.scss');
$adminCss = @file_get_contents($root . '/public_html/admin/assets/css/admin.css');
$check($adminScss !== false && $adminScss === $adminCss, 'Core admin SCSS/CSS synchronized');

$frontendEntry = 'public_html/assets/scss/style.scss';
$frontendScss = (string)@file_get_contents($root . '/' . $frontendEntry);
$missingImports = [];
preg_match_all('~@import\s+[\'\"]([^\'\"]+)[\'\"]~', $frontendScss, $matches);
foreach ($matches[1] as $import) {
    $directory = dirname($import);
    $base = basename($import);
    $path = $root . '/public_html/assets/scss/' . ($directory === '.' ? '' : $directory . '/') . '_' . $base . '.scss';
    if (!is_file($path)) {
        $missingImports[] = $import;
    }
}
$check($frontendScss !== '' && $missingImports === [], 'Frontend SCSS import targets', implode(', ', $missingImports));

$duplicates = [];
foreach ($hashGroups as $paths) {
    if (count($paths) < 2) {
        continue;
    }
    sort($paths);
    $approvedCopies = [
        ['public_html/admin/assets/css/admin.css', 'public_html/admin/assets/scss/admin.scss'],
        ['public_html/assets/install.css', 'public_html/assets/scss/install.scss'],
        // Same protective rules intentionally apply at both upload entry points.
        ['public_html/assets/uploads/.htaccess', 'public_html/uploads/.htaccess'],
    ];
    if (!in_array($paths, $approvedCopies, true)) {
        $duplicates[] = implode(' == ', $paths);
    }
}
$check($duplicates === [], 'No unexplained byte-identical files', implode('; ', $duplicates));

$formsSource = (string)@file_get_contents($root . '/modules/forms/FormsModule.php');
$check(!str_contains($formsSource, 'function emptyAdminField('), 'Unused private Forms method removed');

// Static references alone cannot prove absence of runtime uses. Report candidates,
// but never automatically remove public helpers, plugin APIs, migrations or cron scripts.
$notes[] = 'Public methods/helpers with no static callers are NOT classified as dead: hooks, dynamic dispatch and external themes may use them.';
$notes[] = 'Legacy PHP migration scripts are retained because the runtime migration discovery still loads them.';
$notes[] = 'Historical release documents and Module Starter templates are deliberately preserved.';
$notes[] = 'Default mode audits an installed CMS: local .env and macOS Finder metadata are ignored without reading them. --release rejects these files from ZIP distribution.';
$notes[] = 'Duplicate detection ignores live uploads and runtime storage in default mode; --release verifies all shipped files.';
$notes[] = 'Literal Core view includes and known route/view mappings are statically checked; computed includes, database actions, and live browser workflows require separate tests.';
$notes[] = 'Dashboard, authentication and scheduled content retain their existing top-level views; they are outside the seven-section migration, not presumed dead code.';
$notes[] = 'Copies left in an existing installation are outside a ZIP audit: deploying a ZIP does not delete older files.';
$notes[] = 'This audit does not substitute for end-to-end checks against the local Homebrew Apache/MySQL installation.';

$failed = count(array_filter($checks, static fn(array $c): bool => $c['status'] === 'FAIL'));
$result = [
    'version' => '2.4.4t',
    'mode' => $releaseMode ? 'release' : 'installation',
    'root' => $root,
    'files' => count($allFiles),
    'php_files' => count($phpFiles),
    'modules' => $moduleCount,
    'checks_passed' => count($checks) - $failed,
    'checks_failed' => $failed,
    'checks' => $checks,
    'notes' => $notes,
];
if (in_array('--json', $flags, true)) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "Boilerplate " . $result['version'] . " – read-only codebase audit\n";
    echo "Files: " . $result['files'] . "; PHP: " . $result['php_files'] . "; modules: " . $moduleCount . "\n";
    foreach ($checks as $row) {
        echo '[' . $row['status'] . '] ' . $row['name'] . ($row['detail'] !== '' ? ' – ' . $row['detail'] : '') . "\n";
    }
    echo "Summary: " . $result['checks_passed'] . " PASS, " . $failed . " FAIL\n";
    foreach ($notes as $note) {
        echo 'NOTE: ' . $note . "\n";
    }
}
exit($failed === 0 ? 0 : 1);
