<?php

/**
 * Optional capability for modules that handle controlled frontend actions.
 *
 * Each registration may contain: action, methods and handler. Actions are
 * automatically namespaced as "<module-slug>:<action>" by ModuleManager.
 */
interface ModuleFrontendActionInterface
{
    /** @return array<int,array<string,mixed>> */
    public function frontendActions(): array;
}
