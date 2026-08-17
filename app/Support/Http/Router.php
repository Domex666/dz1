<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Enums\ErrorCodeEnum;
use App\Exceptions\System\MethodNotAllowedException;
use App\Exceptions\System\NotFoundException;
use Closure;

final class Router
{
    /** @var list<array{method: string, regex: string, handler: Closure}> */
    private array $routes = [];

    public function add(string $method, string $pattern, Closure $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => $this->toRegex($pattern),
            'handler' => $handler,
        ];
    }

    /**
     * @throws NotFoundException путь неизвестен
     * @throws MethodNotAllowedException путь есть, метод не тот
     */
    public function dispatch(Request $request): Response
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }

            $pathMatched = true;

            if ($route['method'] !== $request->method) {
                continue;
            }

            $parameters = array_filter($matches, static fn (int|string $key): bool => is_string($key), ARRAY_FILTER_USE_KEY);

            return ($route['handler'])($request, $parameters);
        }

        if ($pathMatched) {
            throw new MethodNotAllowedException();
        }

        throw new NotFoundException(ErrorCodeEnum::ROUTE_NOT_FOUND);
    }

    /**
     * /api/v1/notes/{id} → #^/api/v1/notes/(?<id>[^/]+)$#
     */
    private function toRegex(string $pattern): string
    {
        // Порядок важен: preg_quote экранирует фигурные скобки в \{id\},
        // и подстановка именованной группы после него уже не находит {id}.
        // Сначала снимаем экранирование со скобок-плейсхолдеров, потом подставляем.
        $quoted = str_replace(['\{', '\}'], ['{', '}'], preg_quote($pattern, '#'));

        $regex = preg_replace_callback(
            '#\{(\w+)\}#',
            static fn (array $matches): string => '(?<' . $matches[1] . '>[^/]+)',
            $quoted
        );

        return '#^' . (string)$regex . '$#';
    }
}
