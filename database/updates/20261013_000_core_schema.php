<?php

declare(strict_types=1);

return static function (Db $db): void {
    ModuleManager::installSchema($db);
    MediaLibrary::installSchema($db);
    Redirects::installSchema($db);
};
