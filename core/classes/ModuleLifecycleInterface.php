<?php

declare(strict_types=1);

/** Optional, explicit module state transition callbacks. No schema changes in web requests. */
interface ModuleLifecycleInterface
{
    /** Called before first activation; an exception leaves the module inactive. */
    public function onActivate(ModuleContext $context): void;

    /** Called before deactivation; an exception leaves the module active. */
    public function onDeactivate(ModuleContext $context): void;

    /** Called on explicit update confirmation after required migrations have run. */
    public function onUpdate(ModuleContext $context, string $fromVersion): void;
}
