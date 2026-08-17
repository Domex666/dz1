<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use RuntimeException;

/**
 * Минимальная замена биндингам AppServiceProvider: контракт → фабрика реализации.
 * Разрешается всегда контракт, никогда конкретный класс.
 */
final class Container
{
    /** @var array<string, Closure> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $resolved = [];

    public function bind(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    public function get(string $abstract): object
    {
        if (isset($this->resolved[$abstract])) {
            return $this->resolved[$abstract];
        }

        if (!isset($this->bindings[$abstract])) {
            throw new RuntimeException("Контракт $abstract не зарегистрирован в контейнере");
        }

        return $this->resolved[$abstract] = ($this->bindings[$abstract])($this);
    }
}
