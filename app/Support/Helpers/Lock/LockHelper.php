<?php

declare(strict_types=1);

namespace App\Support\Helpers\Lock;

use App\Enums\ErrorCodeEnum;
use App\Exceptions\System\StorageException;

/**
 * Аналог LockHelper из плагина, но поверх flock вместо Cache::lock —
 * Redis в проекте запрещён. Инфраструктура: ни одного слова из предметной области.
 */
final class LockHelper
{
    public static function lock(string $key, callable $callback): mixed
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'notes-api-' . md5($key) . '.lock';
        $handle = fopen($path, 'c');

        if ($handle === false) {
            throw new StorageException(ErrorCodeEnum::STORAGE_FAILURE);
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);

            throw new StorageException(ErrorCodeEnum::STORAGE_FAILURE);
        }

        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
