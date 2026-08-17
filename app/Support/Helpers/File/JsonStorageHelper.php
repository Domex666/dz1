<?php

declare(strict_types=1);

namespace App\Support\Helpers\File;

use App\Enums\ErrorCodeEnum;
use App\Exceptions\System\StorageException;

/**
 * Аналог DatabaseHelper: инфраструктурный доступ к хранилищу.
 * Знает про файл и JSON, не знает ни одного слова из предметной области.
 */
final readonly class JsonStorageHelper
{
    public function __construct(private string $path)
    {
    }

    /**
     * Отсутствующий и пустой файл — это пустой набор данных, а не ошибка.
     * Испорченный файл — ошибка: молча пересоздать его означало бы потерять данные.
     *
     * @return list<array<string, mixed>>
     * @throws StorageException
     */
    public function read(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $raw = file_get_contents($this->path);

        if ($raw === false) {
            throw new StorageException(ErrorCodeEnum::STORAGE_FAILURE);
        }

        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new StorageException(ErrorCodeEnum::STORAGE_CORRUPTED);
        }

        return $decoded;
    }

    /**
     * Атомарная запись: сначала во временный файл, потом rename.
     * Иначе оборванная запись оставит наполовину записанный JSON,
     * и следующий read() честно отдаст STORAGE_CORRUPTED на пустом месте.
     *
     * @param list<array<string, mixed>> $data
     * @throws StorageException
     */
    public function write(array $data): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new StorageException(ErrorCodeEnum::STORAGE_FAILURE);
        }

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        if ($encoded === false) {
            throw new StorageException(ErrorCodeEnum::STORAGE_FAILURE);
        }

        $temporary = $this->path . '.' . getmypid() . '.tmp';

        if (file_put_contents($temporary, $encoded . PHP_EOL) === false) {
            throw new StorageException(ErrorCodeEnum::STORAGE_FAILURE);
        }

        if (!rename($temporary, $this->path)) {
            @unlink($temporary);

            throw new StorageException(ErrorCodeEnum::STORAGE_FAILURE);
        }
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
