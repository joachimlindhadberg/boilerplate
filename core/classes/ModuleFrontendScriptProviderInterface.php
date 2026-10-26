<?php

/**
 * Optional contract for modules that need frontend JavaScript.
 *
 * Frontend presentation belongs exclusively to the site's frontend theme.
 * Modules must not register or ship frontend CSS through the module runtime.
 * Script paths are relative to the module's own assets/ directory and are
 * exposed only for active, compatible modules.
 */
interface ModuleFrontendScriptProviderInterface
{
    /** @return array<int,string> */
    public function frontendScripts(): array;
}
