<?php

/**
 * Contract for installable Boilerplate modules.
 *
 * The manifest owns static metadata. The PHP entry class only exposes the
 * runtime registrations the module needs. Keeping this contract small makes
 * module loading predictable and gives core one controlled extension point.
 */
interface ModuleInterface
{
    /** Called once per request when an active module is loaded. */
    public function boot(ModuleContext $context): void;

    /**
     * Return admin navigation registrations.
     *
     * Each item may contain: label, route, icon and capability.
     */
    public function adminMenu(): array;

    /**
     * Return admin route registrations.
     *
     * Each route may contain: route, methods, capability, handler and nav_key.
     */
    public function adminRoutes(): array;
}
