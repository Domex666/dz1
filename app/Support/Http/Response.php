<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Enums\ExceptionStatusCodeEnum;

final readonly class Response
{
    /**
     * @param array<string, mixed>|null $body null — ответ без тела (204)
     */
    public function __construct(
        public int $status,
        public ?array $body = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function success(array $data, int $status = 200): self
    {
        return new self($status, ['success' => true, 'data' => $data]);
    }

    public static function noContent(): self
    {
        return new self(204);
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function error(ExceptionStatusCodeEnum $status, array $body): self
    {
        return new self($status->value, $body);
    }

    public function encodedBody(): string
    {
        if ($this->body === null) {
            return '';
        }

        return (string)json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function send(): void
    {
        http_response_code($this->status);

        // Заголовок ставится и для ответа без тела: иначе 204 уходил
        // с дефолтным text/html и вводил клиента в заблуждение.
        header('Content-Type: application/json; charset=utf-8');

        if ($this->body === null) {
            return;
        }

        echo $this->encodedBody();
    }
}
