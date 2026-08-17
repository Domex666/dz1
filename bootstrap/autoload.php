<?php

declare(strict_types=1);

/**
 * PSR-4 автолоадер для пространства App\ поверх каталога app/.
 * Composer не используется намеренно — у проекта нет зависимостей.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $relative . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});
