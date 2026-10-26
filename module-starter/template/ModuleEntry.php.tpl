<?php

declare(strict_types=1);

/** Entry point: register routes/hooks, do not create tables or alter schema here. */
final class __MODULE_CLASS__ implements ModuleInterface
{
    private ?ModuleContext $context = null;

    public function boot(ModuleContext $context): void
    {
        $this->context = $context;
        // Optional: register listeners via $context->onAction() / onFilter().
        // Keep boot() fast and free from DDL.
    }

    public function adminMenu(): array
    {
        return [[
            'label' => __PHP_MODULE_NAME__,
            'route' => '__MODULE_SLUG__',
            'icon' => 'cube',
            'capability' => '__MODULE_SLUG__.manage',
            'key' => 'module:__MODULE_SLUG__',
        ]];
    }

    public function adminRoutes(): array
    {
        return [[
            'route' => '__MODULE_SLUG__',
            'methods' => ['GET'],
            'capability' => '__MODULE_SLUG__.manage',
            'handler' => 'adminIndex',
            'nav_key' => 'module:__MODULE_SLUG__',
        ]];
    }

    public function adminIndex(array $runtime): void
    {
        if (!$this->context instanceof ModuleContext) {
            throw new LogicException('Module has not booted.');
        }
        $this->context->renderAdmin('admin/views/index.php', [
            'user' => $runtime['user'] ?? null,
            'activeNav' => 'module:__MODULE_SLUG__',
            'documentTitle' => $this->context->name(),
            'moduleName' => $this->context->name(),
        ]);
    }
}
