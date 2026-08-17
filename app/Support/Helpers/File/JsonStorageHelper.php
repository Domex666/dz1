<?php

declare(strict_types=1);

namespace App\Support\Helpers\File;

use App\Enums\ErrorCodeEnum;
use App\Exceptions\System\StorageException;
use Throwable;

/**
 * Аналог DatabaseHelper: инфраструктурный доступ к хранилищу.
 * Знает про файл и JSON, не знает ни одного слова из предметной области.
 *
 * Всё, что обращается к диску, обёрнуто в try/catch(Throwable) → StorageException —
 * тот же приём, что и QueryException в эталоне. Без этого отказ прав на файл
 * печатал PHP-предупреждение с абсолютным путём прямо в тело ответа.
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
        $raw = $this->readRawContents();

        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new StorageException(ErrorCodeEnum::STORAGE_CORRUPTED);
        }

        // Проверяется не только «это список», но и каждый его элемент.
        // Файл [1,2,3] формально список, и раньше он проходил чтение,
        // а падал уже глубже — TypeError'ом с кодом STORAGE_FAILURE.
        // Хуже того, запись в такой файл проходила и дописывала мусор к мусору.
        foreach ($decoded as $row) {
            if (!is_array($row) || array_is_list($row)) {
                throw new StorageException(ErrorCodeEnum::STORAGE_CORRUPTED);
            }
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
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        if ($encoded === false) {
            throw new StorageException(ErrorCodeEnum::STORAGE_FAILURE);
        }

        $temporary = $this->path . '.' . getmypid() . '.tmp';

        try {
            $directory = dirname($this->path);

            if (!is_dir($directory)) {
                mkdir($directory, 0o775, true);
            }

            file_put_contents($temporary, $encoded . PHP_EOL);
            rename($temporary, $this->path);
        } catch (Throwable $exception) {
            if (is_file($temporary)) {
                @unlink($temporary);
            }

            throw new StorageException(ErrorCodeEnum::STORAGE_FAILURE, $exception);
        }
    }

    /**
     * @throws StorageException
     */
    private function readRawContents(): ?string
    {
        // clearstatcache обязателен. is_file() читает stat-кэш процесса, и после
        // удаления файла внешним процессом он ещё какое-то время отвечает true.
        // Дальше file_get_contents варнингует, обработчик из bootstrap/errors.php
        // превращает варнинг в исключение, и «файла нет» превращается
        // в 500 STORAGE_FAILURE вместо обещанных 200 и пустого списка.
        clearstatcache(true, $this->path);

        if (!file_exists($this->path)) {
            return null;
        }

        if (!is_file($this->path)) {
            // Путь есть, но это не файл — например каталог. Отдавать пустой список
            // значило бы выдать сломанное хранилище за пустое.
            throw new StorageException(ErrorCodeEnum::STORAGE_FAILURE);
        }

        try {
            return (string)file_get_contents($this->path);
        } catch (Throwable $exception) {
            clearstatcache(true, $this->path);

            // Файл мог исчезнуть между проверкой и чтением. Это гонка,
            // а не отказ хранилища: снаружи это по-прежнему «файла нет».
            if (!file_exists($this->path)) {
                return null;
            }

            throw new StorageException(ErrorCodeEnum::STORAGE_FAILURE, $exception);
        }
    }
}
