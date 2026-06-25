<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/helpers.php';

spl_autoload_register(static function (string $class): void {
    $path = __DIR__ . '/' . $class . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

foreach ([DATA_DIR, UPLOADS_DIR, THUMBS_DIR, EXPORTS_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

Database::connect();
Database::migrate();
Event::ensureExists();