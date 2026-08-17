<?php

declare(strict_types=1);

namespace App\Interfaces\Exceptions;

use App\Enums\ExceptionStatusCodeEnum;

/**
 * Исключение само знает свой HTTP-код и тело ответа.
 * Рендер описан один раз в bootstrap/app.php, в контроллерах try/catch нет.
 */
interface ResponseExceptionInterface
{
    public array $response {
        get;
    }

    public ExceptionStatusCodeEnum $errorCode {
        get;
        set;
    }
}
