<?php

declare(strict_types=1);

/**
 * Forms module workspace adapter.
 *
 * Core renders the common titlebar and optional navigation; Core owns styling.
 * Forms uses full-width content, with compact top links on overview screens.
 *
 * The view calls this adapter directly. This means the workspace does not
 * depend on an additional Forms controller hook (the V2.3.2 regression).
 *
 * @return Closure(string,array):void
 */
return static function (string $screen, array $data): void {
    $allowed = ['index', 'new', 'edit', 'form', 'submissions', 'settings', 'submission'];
    if (!in_array($screen, $allowed, true)) {
        throw new InvalidArgumentException('Invalid Forms workspace view.');
    }
    $contentName = match ($screen) {
        'new', 'edit', 'form' => 'form-editor',
        'index' => 'forms-list',
        'submissions' => 'submissions-list',
        'submission' => 'submission-detail',
        'settings' => 'settings-fields',
    };
    $contentFile = __DIR__ . '/views/partials/' . $contentName . '.php';
    if (!is_file($contentFile)) {
        throw new RuntimeException('Forms content view not found.');
    }

    // The isolated scope deliberately prevents the view from modifying the
    // surrounding ModuleContext variables (especially $sectionWorkspace).
    (static function (string $screen, string $contentFile, array $data): void {
        $hasControllerWorkspace = is_array($data['sectionWorkspace'] ?? null);
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $contentFile;
            $contentHtml = (string)ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        if ($hasControllerWorkspace) {
            // Compatibility with a cached V2.3.2/2.3.3 controller. Core will
            // add the workspace around this HTML; never nest two workspaces.
            echo $contentHtml;
            return;
        }

        $section = match ($screen) {
            'submissions', 'submission' => 'submissions',
            'settings' => 'settings',
            default => 'forms',
        };
        $isEdit = !empty($data['isEdit']);
        $heading = match ($screen) {
            'new', 'edit', 'form' => $isEdit ? 'forms:Rediger formular' : 'forms:Ny formular',
            'submissions', 'submission' => 'forms:Indsendelser',
            'settings' => 'forms:Forms · Indstillinger',
            default => 'forms:Forms',
        };
        $description = match ($screen) {
            'new', 'edit', 'form' => 'forms:Definér felter, mails og hvad der skal ske efter en indsendelse.',
            'submissions', 'submission' => 'forms:Søg, læs og administrér gemte formularindsendelser.',
            'settings' => 'forms:Standarder og spam-beskyttelse for nye formularer.',
            default => 'forms:Opret genbrugelige formularer og administrér indsendelser.',
        };
        $sectionWorkspace = [
            'heading' => $heading,
            'description' => $description,
            'active' => $section,
            'label' => 'forms:Forms',
            // Forms and Submissions belong to two independent, Core-rendered
            // GLOBAL nav items. No duplicate local sidebar or inline menu.
            'navigationMode' => 'none',
            'action' => $screen === 'index' ? [
                'href' => '/admin/forms?screen=new',
                'label' => 'forms:Ny formular',
                'kind' => 'create',
            ] : [],
            'items' => [],
        ];
        $bodyHtml = $contentHtml;
        $coreWorkspace = PUBLICPATH . '/admin/views/partials/section-workspace.php';
        if (!is_file($coreWorkspace)) {
            throw new RuntimeException('CMS Core workspace view not found.');
        }
        require $coreWorkspace;
    })($screen, $contentFile, $data);
};
