<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * Три состояния файла-хранилища: нет, пустой, испорчен.
 * Задание требует описать их поведение явно, поэтому они проверяются отдельно.
 */
final class StorageStateTest extends FeatureTestCase
{
    public function testMissingFileIsTreatedAsEmptyStorage(): void
    {
        self::assertFileDoesNotExist($this->storagePath);

        $response = $this->request('GET', '/api/v1/notes');

        self::assertSame(200, $response->status);
        self::assertSame([], $this->data($response)['items']);
    }

    public function testFileIsCreatedOnFirstWrite(): void
    {
        self::assertFileDoesNotExist($this->storagePath);

        $this->createNote('Первая');

        self::assertFileExists($this->storagePath);
    }

    public function testEmptyFileIsTreatedAsEmptyStorage(): void
    {
        file_put_contents($this->storagePath, '');

        $response = $this->request('GET', '/api/v1/notes');

        self::assertSame(200, $response->status);
        self::assertSame([], $this->data($response)['items']);
    }

    public function testCorruptedFileReturnsServerErrorAndKeepsFileIntact(): void
    {
        $corrupted = '{ это не JSON';
        file_put_contents($this->storagePath, $corrupted);

        $response = $this->request('GET', '/api/v1/notes');

        self::assertSame(500, $response->status);
        self::assertSame('STORAGE_CORRUPTED', $this->error($response)['code']);
        self::assertSame($corrupted, file_get_contents($this->storagePath));
    }

    public function testCorruptedFileBlocksWritesInsteadOfOverwritingData(): void
    {
        $corrupted = '{"notes": "объект вместо списка"}';
        file_put_contents($this->storagePath, $corrupted);

        $response = $this->request('POST', '/api/v1/notes', ['title' => 'Новая заметка']);

        self::assertSame(500, $response->status);
        self::assertSame('STORAGE_CORRUPTED', $this->error($response)['code']);
        self::assertSame($corrupted, file_get_contents($this->storagePath));
    }

    public function testCorruptedFileErrorHasNoStackTrace(): void
    {
        file_put_contents($this->storagePath, 'мусор');

        $encoded = $this->request('GET', '/api/v1/notes')->encodedBody();

        self::assertStringNotContainsString('.php', $encoded);
        self::assertStringNotContainsString('Stack trace', $encoded);
    }
}
