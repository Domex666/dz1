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

    /**
     * Файл [1,2,3] формально список, поэтому проверку «это список» он проходил.
     * Чтение падало глубже TypeError'ом и отдавало STORAGE_FAILURE вместо
     * STORAGE_CORRUPTED, а запись проходила и дописывала мусор к мусору.
     */
    public function testListOfScalarsIsCorruptedOnRead(): void
    {
        $this->writeStorage('[1,2,3]');

        $response = $this->request('GET', '/api/v1/notes');

        self::assertSame(500, $response->status);
        self::assertSame('STORAGE_CORRUPTED', $this->error($response)['code']);
    }

    public function testListOfScalarsBlocksWrites(): void
    {
        $corrupted = '[1,2,3]';
        $this->writeStorage($corrupted);

        $response = $this->request('POST', '/api/v1/notes', ['title' => 'Новая']);

        self::assertSame(500, $response->status);
        self::assertSame('STORAGE_CORRUPTED', $this->error($response)['code']);
        self::assertSame($corrupted, file_get_contents($this->storagePath));
    }

    public function testRowWithoutRequiredColumnsIsCorrupted(): void
    {
        // Раньше строка {} превращалась в заметку со всеми пустыми полями.
        $this->writeStorage('[{}]');

        $response = $this->request('GET', '/api/v1/notes');

        self::assertSame(500, $response->status);
        self::assertSame('STORAGE_CORRUPTED', $this->error($response)['code']);
    }

    public function testRowWithWrongTagsTypeIsCorrupted(): void
    {
        // "tags":"xxx" протаскивал ненормализованный тег и в список, и в аналитику,
        // ломая заявленный инвариант «теги всегда в верхнем регистре».
        $this->seedStorage([
            ['id' => 'x', 'title' => 't', 'content' => '', 'tags' => 'работа', 'created_at' => 'a', 'updated_at' => 'a'],
        ]);

        $response = $this->request('GET', '/api/v1/notes');

        self::assertSame(500, $response->status);
        self::assertSame('STORAGE_CORRUPTED', $this->error($response)['code']);
    }

    public function testSuccessfulWriteLeavesNoTemporaryFiles(): void
    {
        $this->createNote('Заметка');

        $leftovers = glob($this->storageDirectory . DIRECTORY_SEPARATOR . '*.tmp');

        self::assertSame([], $leftovers);
    }

    /**
     * Порча на уровне отдельной записи. Раньше GET честно отдавал 500,
     * а POST и DELETE проходили и затирали данные — «потеря данных
     * под видом устойчивости», которую SPEC называет отвергнутой альтернативой.
     */
    public function testRowWithMissingColumnBlocksDelete(): void
    {
        $id = '11111111-1111-4111-8111-111111111111';
        $corrupted = (string)json_encode([[
            'id' => $id,
            'title' => 'НЕ ТРОГАТЬ',
            'tags' => ['РАБОТА'],
            'created_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-01T00:00:00+00:00',
        ]], JSON_UNESCAPED_UNICODE);

        $this->writeStorage($corrupted);

        $response = $this->request('DELETE', '/api/v1/notes/' . $id);

        self::assertSame(500, $response->status);
        self::assertSame('STORAGE_CORRUPTED', $this->error($response)['code']);
        self::assertSame($corrupted, file_get_contents($this->storagePath), 'данные обязаны остаться на месте');
    }

    public function testRowWithMissingColumnBlocksCreate(): void
    {
        $corrupted = (string)json_encode([[
            'id' => '11111111-1111-4111-8111-111111111111',
            'title' => 'НЕ ТРОГАТЬ',
            'tags' => [],
            'created_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-01T00:00:00+00:00',
        ]], JSON_UNESCAPED_UNICODE);

        $this->writeStorage($corrupted);

        $response = $this->request('POST', '/api/v1/notes', ['title' => 'Новая']);

        self::assertSame(500, $response->status);
        self::assertSame('STORAGE_CORRUPTED', $this->error($response)['code']);
        self::assertSame($corrupted, file_get_contents($this->storagePath));
    }

    public function testRowWithNonStringTagInsideListIsCorrupted(): void
    {
        // Проверялся только тип всего поля tags; список с числом внутри
        // доезжал до клиента и до аналитики как валидный тег.
        $this->seedStorage([[
            'id' => 'x',
            'title' => 't',
            'content' => '',
            'tags' => [5, 'ok'],
            'created_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-01T00:00:00+00:00',
        ]]);

        self::assertSame(500, $this->request('GET', '/api/v1/notes')->status);
        self::assertSame(500, $this->request('GET', '/api/v1/tags/top')->status);
    }

    public function testUnparsableTimestampIsCorrupted(): void
    {
        // Проверялся только is_string, и строка «не дата» уезжала клиенту
        // в createdAt, попутно ломая сортировку — она сравнивает эти строки.
        $this->seedStorage([[
            'id' => 'x',
            'title' => 't',
            'content' => '',
            'tags' => [],
            'created_at' => 'не дата',
            'updated_at' => '12345',
        ]]);

        $response = $this->request('GET', '/api/v1/notes');

        self::assertSame(500, $response->status);
        self::assertSame('STORAGE_CORRUPTED', $this->error($response)['code']);
    }

    public function testCorruptedFileErrorHasNoStackTrace(): void
    {
        file_put_contents($this->storagePath, 'мусор');

        $encoded = $this->request('GET', '/api/v1/notes')->encodedBody();

        self::assertStringNotContainsString('.php', $encoded);
        self::assertStringNotContainsString('Stack trace', $encoded);
    }
}
