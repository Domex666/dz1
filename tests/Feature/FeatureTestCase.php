<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Http\Request;
use App\Support\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Базовый класс для тестов эндпоинтов.
 *
 * Тесты не поднимают сеть и не трогают рабочий файл данных: приложение собирается
 * тем же bootstrap/app.php, но с путём во временный каталог, который удаляется в tearDown.
 */
abstract class FeatureTestCase extends TestCase
{
    protected string $storagePath;
    protected string $storageDirectory;

    /** @var callable(Request): Response */
    protected $handle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'notes-api-test-' . bin2hex(random_bytes(8));
        $this->storagePath = $this->storageDirectory . DIRECTORY_SEPARATOR . 'notes.json';

        mkdir($this->storageDirectory, 0o775, true);

        $bootstrap = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $this->handle = $bootstrap($this->storagePath);
    }

    protected function tearDown(): void
    {
        if (is_file($this->storagePath)) {
            unlink($this->storagePath);
        }

        if (is_dir($this->storageDirectory)) {
            rmdir($this->storageDirectory);
        }

        parent::tearDown();
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string> $query
     */
    protected function request(string $method, string $path, ?array $body = null, array $query = []): Response
    {
        $rawBody = $body === null ? '' : (string)json_encode($body, JSON_UNESCAPED_UNICODE);

        return $this->send($method, $path, $rawBody, $query);
    }

    /**
     * @param array<string, string> $query
     */
    protected function send(string $method, string $path, string $rawBody = '', array $query = []): Response
    {
        return ($this->handle)(new Request(
            method: $method,
            path: $path,
            query: $query,
            rawBody: $rawBody,
        ));
    }

    /**
     * Создаёт заметку и возвращает её представление из ответа.
     *
     * @param string[] $tags
     * @return array<string, mixed>
     */
    protected function createNote(string $title, array $tags = [], string $content = ''): array
    {
        $response = $this->request('POST', '/api/v1/notes', [
            'title' => $title,
            'content' => $content,
            'tags' => $tags,
        ]);

        self::assertSame(201, $response->status, 'Не удалось подготовить заметку для теста');

        /** @var array{data: array<string, mixed>} $body */
        $body = $response->body;

        return $body['data'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(Response $response): array
    {
        /** @var array{data: array<string, mixed>} $body */
        $body = $response->body;

        return $body['data'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function error(Response $response): array
    {
        /** @var array{error: array<string, mixed>} $body */
        $body = $response->body;

        return $body['error'];
    }
}
