<?php

declare(strict_types=1);

return static function (Db $db): void {
    require_once dirname(__DIR__) . '/GalleryRepository.php';
    (new GalleryRepository($db))->ensureSchema();
};
