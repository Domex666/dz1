<?php

declare(strict_types=1);

namespace App\Exceptions\System;

use App\Enums\ErrorCodeEnum;

class BadRequestException extends ResponseException
{
    public function __construct()
    {
        parent::__construct(ErrorCodeEnum::BAD_REQUEST->getMessage());

        $this->setError(ErrorCodeEnum::BAD_REQUEST);
    }
}
