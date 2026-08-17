<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Exceptions\System\BadRequestException;
use App\Exceptions\System\ResponseValidationException;
use stdClass;

final readonly class Request
{
    /**
     * @param array<string, mixed> $query
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public string $rawBody = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $normalizedPath = is_string($path) ? (rtrim($path, '/') ?: '/') : '/';

        return new self(
            method: strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            path: $normalizedPath,
            query: $_GET,
            rawBody: (string)file_get_contents('php://input'),
        );
    }

    /**
     * Тело запроса как ассоциативный массив.
     * Пустое тело — это пустой массив, а не ошибка: пусть решает валидация полей.
     *
     * Декодируется в объекты, а не сразу в массивы. Причина: json_decode('{}', true)
     * и json_decode('[]', true) дают одно и то же — пустой массив, — и отличить
     * пустой объект от массива верхнего уровня уже невозможно. Из-за этого тело {}
     * отвергалось как «не JSON» с кодом 400 вместо ожидаемых 422 по обязательным полям.
     * Побочная польза: вложенный объект остаётся stdClass, поэтому tags вида
     * {"0":"a"} больше не притворяется списком.
     *
     * @return array<string, mixed>
     * @throws BadRequestException
     */
    public function json(): array
    {
        if (trim($this->rawBody) === '') {
            return [];
        }

        $decoded = json_decode($this->rawBody);

        if (json_last_error() !== JSON_ERROR_NONE || !$decoded instanceof stdClass) {
            throw new BadRequestException();
        }

        return get_object_vars($decoded);
    }

    /**
     * Строковый параметр строки запроса.
     *
     * Не-строка (?mode[]=any) раньше молча подменялась значением по умолчанию,
     * и клиент получал не тот набор данных, который просил, без признака ошибки.
     *
     * @throws ResponseValidationException
     */
    public function queryString(string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $this->query)) {
            return $default;
        }

        $value = $this->query[$key];

        if (!is_string($value)) {
            throw new ResponseValidationException([$key => ['Ожидается одно строковое значение']]);
        }

        return $value;
    }

    public function hasQuery(string $key): bool
    {
        return array_key_exists($key, $this->query);
    }
}
