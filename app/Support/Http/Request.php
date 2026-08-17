<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Exceptions\System\BadRequestException;

final readonly class Request
{
    /**
     * @param array<string, string> $query
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
     * @return array<string, mixed>
     * @throws BadRequestException
     */
    public function json(): array
    {
        if (trim($this->rawBody) === '') {
            return [];
        }

        $decoded = json_decode($this->rawBody, true);

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new BadRequestException();
        }

        return $decoded;
    }

    public function queryString(string $key, string $default = ''): string
    {
        $value = $this->query[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    public function hasQuery(string $key): bool
    {
        return array_key_exists($key, $this->query);
    }
}
