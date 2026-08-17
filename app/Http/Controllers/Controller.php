<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Http\Response;

abstract readonly class Controller
{
    /**
     * @param array<string, mixed> $data
     */
    protected function successResponse(array $data, int $status = 200): Response
    {
        return Response::success($data, $status);
    }
}
