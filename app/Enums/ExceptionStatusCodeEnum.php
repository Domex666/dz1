<?php

declare(strict_types=1);

namespace App\Enums;

enum ExceptionStatusCodeEnum: int
{
    case BAD_REQUEST = 400;
    case NOT_FOUND = 404;
    case METHOD_NOT_ALLOWED = 405;
    case UNPROCESSABLE_ENTITY = 422;
    case INTERNAL_ERROR = 500;
}
