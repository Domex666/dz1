<?php

declare(strict_types=1);

namespace App\Exceptions\System;

use App\Enums\ErrorCodeEnum;

class MethodNotAllowedException extends ResponseException
{
    public function __construct()
    {
        parent::__construct(ErrorCodeEnum::METHOD_NOT_ALLOWED->getMessage());

        $this->setError(ErrorCodeEnum::METHOD_NOT_ALLOWED);
    }
}
