<?php

declare(strict_types=1);

/**
 * Обработка PHP-предупреждений. Подключается через require_once ровно один раз.
 *
 * Без этого файла любой warning (нечитаемый файл хранилища, отказ записи) печатался
 * в тело ответа ДО того, как отработает Response::send(). Дальше http_response_code()
 * и header() падали с «headers already sent», и клиент получал 200 OK,
 * Content-Type: text/html и абсолютный путь к файлу внутри HTML — при том, что
 * SPEC.md объявляет утечку внутренних путей невозможной.
 *
 * Предупреждение превращается в ErrorException, и дальше его подбирает тот же слой,
 * что и остальные ошибки: JsonStorageHelper обернёт его в StorageException,
 * всё непойманное поймает обработчик в bootstrap/app.php.
 */

ini_set('display_errors', '0');
ini_set('html_errors', '0');
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    // Оператор @ и настройки error_reporting должны продолжать работать:
    // без этой проверки подавленный @unlink() начал бы бросать исключение.
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});
