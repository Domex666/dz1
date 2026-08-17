<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\System\InternalErrorException;
use Closure;

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

    /**
     * @throws InternalErrorException
     */
    public function get(string $abstract): object
    {
        if (isset($this->resolved[$abstract])) {
            return $this->resolved[$abstract];
        }

        if (!isset($this->bindings[$abstract])) {
            // Не RuntimeException: бросать разрешено только App\Exceptions\*,
            // иначе ответ уходит мимо описанного формата ошибки.
            throw new InternalErrorException("Контракт $abstract не зарегистрирован в контейнере");
        }

        return $this->resolved[$abstract] = ($this->bindings[$abstract])($this);
    }
}
