<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Tests\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $path = __DIR__ . DIRECTORY_SEPARATOR . $relative . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});
