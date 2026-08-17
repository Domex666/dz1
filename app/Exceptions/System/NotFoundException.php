<?php

declare(strict_types=1);

namespace App\Exceptions\System;

use App\Enums\ErrorCodeEnum;

class NotFoundException extends ResponseException
{
    public function __construct(ErrorCodeEnum $code = ErrorCodeEnum::NOTE_NOT_FOUND)
    {
        parent::__construct($code->getMessage());

        $this->setError($code);
    }
}
