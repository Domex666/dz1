<?php

declare(strict_types=1);

namespace App\Exceptions\System;

use App\Enums\ErrorCodeEnum;
use Throwable;

/**
 * Аналог QueryException из плагина: всё, что сломалось на уровне хранилища,
 * оборачивается сюда, чтобы наружу не ушла трассировка стека.
 */
class StorageException extends ResponseException
{
    public function __construct(
        ErrorCodeEnum $code = ErrorCodeEnum::STORAGE_FAILURE,
        ?Throwable $previousException = null
    ) {
        parent::__construct($code->getMessage(), $previousException);

        $this->setError($code);
    }
}
