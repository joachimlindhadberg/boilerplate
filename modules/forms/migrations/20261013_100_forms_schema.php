<?php

declare(strict_types=1);

return static function (Db $db): void {
    require_once dirname(__DIR__) . '/FormsRepository.php';
    (new FormsRepository($db))->ensureSchema();
};
