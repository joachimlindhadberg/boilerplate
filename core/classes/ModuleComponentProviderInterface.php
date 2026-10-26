<?php

/**
 * Optional capability for modules that expose reusable dynamic components.
 *
 * Component keys are local to the module. ModuleManager prefixes them with
 * "<module-slug>:" before exposing them to Page Builder, which prevents
 * collisions between independently developed modules.
 */
interface ModuleComponentProviderInterface
{
    /** @return array<string,string> local component key => human label */
    public function componentOptions(): array;

    /** Render one local component key. Return true when it was handled. */
    public function renderComponent(string $component, array $modifiers = []): bool;
}
