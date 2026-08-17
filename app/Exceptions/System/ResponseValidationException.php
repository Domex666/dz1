<?php

declare(strict_types=1);

namespace App\Exceptions\System;

use App\Enums\ErrorCodeEnum;

class ResponseValidationException extends ResponseException
{
    /**
     * @param array<string, string[]> $fields ключ — путь до поля (tags.1, colour), значение — сообщения
     */
    public function __construct(array $fields)
    {
        parent::__construct(ErrorCodeEnum::VALIDATION_ERROR->getMessage());

        $this->setError(code: ErrorCodeEnum::VALIDATION_ERROR, message: null, fields: $fields);
    }
}
