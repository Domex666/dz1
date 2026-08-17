<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Enums\ErrorCodeEnum;
use App\Interfaces\Exceptions\ResponseExceptionInterface;
use Throwable;

/**
 * Единственное место, где исключение превращается в HTTP-ответ.
 *
 * Вынесено из bootstrap/app.php отдельным классом ради проверяемости: пока рендер
 * жил замыканием внутри сборки приложения, гарантию «трассировка стека наружу
 * не уходит никогда» нельзя было проверить тестом. Аудит это и показал —
 * добавление getTraceAsString() в тело ответа не уронило ни одного из 50 тестов.
 */
final readonly class ExceptionRenderer
{
    public function render(Throwable $exception): Response
    {
        if ($exception instanceof ResponseExceptionInterface) {
            return Response::error($exception->errorCode, $exception->response);
        }

        // Всё непредвиденное — INTERNAL_ERROR, а не STORAGE_FAILURE.
        // Раньше TypeError и несобранная зависимость сообщали клиенту, что
        // сломалось хранилище: код ошибки систематически называл неверную причину.
        $code = ErrorCodeEnum::INTERNAL_ERROR;

        return Response::error($code->getStatusCode(), [
            'success' => false,
            'error' => [
                'code' => $code->value,
                'message' => $code->getMessage(),
            ],
        ]);
    }
}
