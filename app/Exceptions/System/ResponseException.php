<?php

declare(strict_types=1);

namespace App\Exceptions\System;

use App\Enums\ErrorCodeEnum;
use App\Enums\ExceptionStatusCodeEnum;
use App\Interfaces\Exceptions\ResponseExceptionInterface;
use Exception;
use Throwable;

/**
 * Базовое исключение: знает свой HTTP-код и готовое тело ответа.
 * Наследники только вызывают setError() в конструкторе.
 */
abstract class ResponseException extends Exception implements ResponseExceptionInterface
{
    protected ExceptionStatusCodeEnum $status = ExceptionStatusCodeEnum::INTERNAL_ERROR;

    /** @var array<string, mixed> */
    protected array $error = [];

    public array $response {
        get {
            return [
                'success' => false,
                'error' => $this->error,
            ];
        }
    }

    public ExceptionStatusCodeEnum $errorCode {
        get {
            return $this->status;
        }
        set {
            $this->status = $value;
        }
    }

    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param array<string, string[]> $fields
     */
    protected function setError(ErrorCodeEnum $code, ?string $message = null, array $fields = []): void
    {
        $this->status = $code->getStatusCode();

        $this->error = [
            'code' => $code->value,
            'message' => $message ?? $code->getMessage(),
        ];

        if ($fields !== []) {
            $this->error['fields'] = $fields;
        }
    }
}
