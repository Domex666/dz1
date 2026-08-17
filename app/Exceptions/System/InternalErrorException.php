<?php

declare(strict_types=1);

namespace App\Exceptions\System;

use App\Enums\ErrorCodeEnum;
use Throwable;

/**
 * Ошибка, не относящаяся к хранилищу: несобранная зависимость, нарушенный инвариант кода.
 *
 * Заведена отдельно, потому что раньше такие случаи уходили клиенту под кодом
 * STORAGE_FAILURE и сообщали неверную причину. Машиночитаемый код, систематически
 * называющий не ту причину, хуже отсутствующего.
 */
class InternalErrorException extends ResponseException
{
    public function __construct(string $message = '', ?Throwable $previousException = null)
    {
        parent::__construct($message !== '' ? $message : ErrorCodeEnum::INTERNAL_ERROR->getMessage(), $previousException);

        $this->setError(ErrorCodeEnum::INTERNAL_ERROR);
    }
}
