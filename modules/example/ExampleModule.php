<?php

final class ExampleModule implements ModuleInterface
{
    private ?ModuleContext $context = null;

    public function boot(ModuleContext $context): void
    {
        $this->context = $context;
    }

    public function adminMenu(): array
    {
        return [[
            'label' => 'Eksempel',
            'route' => 'eksempel',
            'icon' => 'cube',
            'capability' => 'admin.access',
            'key' => 'module:example',
        ]];
    }

    public function adminRoutes(): array
    {
        return [[
            'route' => 'eksempel',
            'methods' => ['GET'],
            'capability' => 'admin.access',
            'handler' => 'adminIndex',
            'nav_key' => 'module:example',
        ]];
    }

    public function adminIndex(array $runtime): void
    {
        if (!$this->context instanceof ModuleContext) {
            throw new RuntimeException('Example Module er ikke startet korrekt.');
        }

        $this->context->renderAdmin('admin/views/index.php', [
            'user' => $runtime['user'] ?? null,
            'activeNav' => 'module:example',
            'documentTitle' => 'Eksempel',
            'moduleVersion' => $this->context->version(),
        ]);
    }
}
