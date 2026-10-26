<?php
/** Return one shared model instance per request. */
function model(string $name)
{
    static $models = [];
    if (!isset($models[$name])) {
        $models[$name] = match ($name) {
            'pages' => new Pages(),
            'settings' => new Settings(),
            'page_blocks' => new PageBlocks(),
            'media' => new MediaLibrary(),
            default => throw new InvalidArgumentException("Unknown model: {$name}"),
        };
    }
    return $models[$name];
}
/** Return one shared module manager per request when extension services are needed. */
function cms_module_manager(): ModuleManager
{
    static $manager = null;
    if (!$manager instanceof ModuleManager) {
        $manager = new ModuleManager();
    }
    return $manager;
}
