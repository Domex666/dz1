<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\System\StorageException;
use App\Support\Helpers\File\JsonStorageHelper;
use PHPUnit\Framework\TestCase;

final class JsonStorageHelperTest extends TestCase
{
    private string $directory;
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'notes-storage-' . bin2hex(random_bytes(8));
        $this->path = $this->directory . DIRECTORY_SEPARATOR . 'notes.json';

        mkdir($this->directory, 0o775, true);
    }

    protected function tearDown(): void
    {
        foreach ((array)glob($this->directory . DIRECTORY_SEPARATOR . '*') as $file) {
            if (is_string($file) && is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->path)) {
            rmdir($this->path);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    /**
     * Регрессия на stat-кэш.
     *
     * Удалять файл через unlink() бесполезно: PHP сам сбрасывает кэш для пути,
     * над которым работает, и тест прошёл бы даже на сломанном коде. Поэтому файл
     * убирает внешний процесс — ровно так это и случилось живьём, когда
     * smoke-скрипт удалил хранилище у работающего сервера и первый же GET
     * вернул 500 вместо 200 с пустым списком.
     */
    public function testFileRemovedByAnotherProcessIsTreatedAsMissing(): void
    {
        file_put_contents($this->path, '[]');

        $storage = new JsonStorageHelper($this->path);

        self::assertSame([], $storage->read(), 'подготовка: файл читается и попадает в stat-кэш');

        exec('rm -f ' . escapeshellarg($this->path), $output, $code);

        self::assertSame(0, $code, 'внешнее удаление должно было сработать');
        self::assertSame([], $storage->read(), 'исчезнувший файл — это пустое хранилище, а не отказ');
    }

    public function testMissingFileIsEmptyStorage(): void
    {
        self::assertSame([], new JsonStorageHelper($this->path)->read());
    }

    public function testWhitespaceOnlyFileIsEmptyStorage(): void
    {
        file_put_contents($this->path, "\n  \t\n");

        self::assertSame([], new JsonStorageHelper($this->path)->read());
    }

    public function testDirectoryInPlaceOfFileIsFailureNotEmptyStorage(): void
    {
        // Сломанное хранилище нельзя выдавать за пустое: клиент увидел бы 200
        // и решил, что заметок просто нет.
        mkdir($this->path);

        $this->expectException(StorageException::class);

        new JsonStorageHelper($this->path)->read();
    }

    public function testCorruptedTopLevelThrows(): void
    {
        file_put_contents($this->path, '{"не":"список"}');

        $this->expectException(StorageException::class);

        new JsonStorageHelper($this->path)->read();
    }

    public function testScalarRowThrows(): void
    {
        file_put_contents($this->path, '[1,2,3]');

        $this->expectException(StorageException::class);

        new JsonStorageHelper($this->path)->read();
    }

    public function testWriteIsReadableBack(): void
    {
        $storage = new JsonStorageHelper($this->path);
        $rows = [['id' => 'a', 'title' => 'т']];

        $storage->write($rows);

        self::assertSame($rows, $storage->read());
    }

    public function testWriteCreatesMissingDirectory(): void
    {
        $nested = $this->directory . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'notes.json';
        $storage = new JsonStorageHelper($nested);

        $storage->write([]);

        self::assertFileExists($nested);

        unlink($nested);
        rmdir(dirname($nested));
    }

    public function testWriteLeavesNoTemporaryFiles(): void
    {
        new JsonStorageHelper($this->path)->write([['id' => 'a']]);

        self::assertSame([], glob($this->directory . DIRECTORY_SEPARATOR . '*.tmp'));
    }
}
