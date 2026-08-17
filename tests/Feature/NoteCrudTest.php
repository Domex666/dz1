<?php

declare(strict_types=1);

namespace Tests\Feature;

final class NoteCrudTest extends FeatureTestCase
{
    public function testCreatesNoteAndNormalizesTags(): void
    {
        $response = $this->request('POST', '/api/v1/notes', [
            'title' => 'Созвон с командой',
            'content' => 'Обсудить сроки',
            'tags' => ['  Работа ', 'работа', 'Work'],
        ]);

        self::assertSame(201, $response->status);

        $note = $this->data($response);

        self::assertSame(['РАБОТА', 'WORK'], $note['tags']);
        self::assertSame('Созвон с командой', $note['title']);
        self::assertNotSame('', $note['id']);
        self::assertSame($note['createdAt'], $note['updatedAt']);
    }

    public function testTrimsTitle(): void
    {
        $note = $this->createNote('   Заголовок с пробелами   ');

        self::assertSame('Заголовок с пробелами', $note['title']);
    }

    public function testRejectsBlankTagWithIndex(): void
    {
        $response = $this->request('POST', '/api/v1/notes', [
            'title' => 'Заметка',
            'tags' => ['   '],
        ]);

        self::assertSame(422, $response->status);

        $error = $this->error($response);

        self::assertSame('VALIDATION_ERROR', $error['code']);
        self::assertArrayHasKey('tags.0', $error['fields']);
    }

    public function testRejectsUnknownField(): void
    {
        $response = $this->request('POST', '/api/v1/notes', [
            'title' => 'Заметка',
            'colour' => 'red',
        ]);

        self::assertSame(422, $response->status);
        self::assertArrayHasKey('colour', $this->error($response)['fields']);
    }

    public function testRejectsClientSuppliedId(): void
    {
        $response = $this->request('POST', '/api/v1/notes', [
            'id' => 'подсунутый-клиентом',
            'title' => 'Заметка',
        ]);

        self::assertSame(422, $response->status);
        self::assertArrayHasKey('id', $this->error($response)['fields']);
    }

    public function testRejectsMissingTitle(): void
    {
        $response = $this->request('POST', '/api/v1/notes', ['content' => 'Без заголовка']);

        self::assertSame(422, $response->status);
        self::assertArrayHasKey('title', $this->error($response)['fields']);
    }

    public function testRejectsTooManyTagsAfterDeduplication(): void
    {
        $response = $this->request('POST', '/api/v1/notes', [
            'title' => 'Заметка',
            'tags' => ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k'],
        ]);

        self::assertSame(422, $response->status);
        self::assertArrayHasKey('tags', $this->error($response)['fields']);
    }

    public function testAcceptsElevenTagsThatDeduplicateToTen(): void
    {
        $response = $this->request('POST', '/api/v1/notes', [
            'title' => 'Заметка',
            'tags' => ['a', 'A', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'],
        ]);

        self::assertSame(201, $response->status);
        self::assertCount(10, $this->data($response)['tags']);
    }

    public function testRejectsMalformedJson(): void
    {
        $response = $this->send('POST', '/api/v1/notes', '{"title": ');

        self::assertSame(400, $response->status);
        self::assertSame('BAD_REQUEST', $this->error($response)['code']);
    }

    public function testShowsNote(): void
    {
        $created = $this->createNote('Заметка', ['дом']);

        $response = $this->request('GET', '/api/v1/notes/' . $created['id']);

        self::assertSame(200, $response->status);
        self::assertSame($created, $this->data($response));
    }

    public function testReturnsNotFoundForUnknownId(): void
    {
        $response = $this->request('GET', '/api/v1/notes/00000000-0000-4000-8000-000000000000');

        self::assertSame(404, $response->status);
        self::assertSame('NOTE_NOT_FOUND', $this->error($response)['code']);
    }

    public function testReplacesNote(): void
    {
        $created = $this->createNote('Старый заголовок', ['дом'], 'Старый текст');

        $response = $this->request('PUT', '/api/v1/notes/' . $created['id'], [
            'title' => 'Новый заголовок',
            'content' => 'Новый текст',
            'tags' => ['работа'],
        ]);

        self::assertSame(200, $response->status);

        $note = $this->data($response);

        self::assertSame('Новый заголовок', $note['title']);
        self::assertSame(['РАБОТА'], $note['tags']);
        self::assertSame($created['createdAt'], $note['createdAt']);
    }

    public function testReplaceResetsOmittedOptionalFields(): void
    {
        $created = $this->createNote('Заголовок', ['дом'], 'Текст есть');

        $response = $this->request('PUT', '/api/v1/notes/' . $created['id'], ['title' => 'Только заголовок']);

        self::assertSame(200, $response->status);

        $note = $this->data($response);

        self::assertSame('', $note['content']);
        self::assertSame([], $note['tags']);
    }

    public function testRepeatedPutIsIdempotent(): void
    {
        $created = $this->createNote('Заметка', ['дом'], 'Текст');

        $payload = ['title' => 'Изменённая', 'content' => 'Другой текст', 'tags' => ['Работа']];

        $first = $this->request('PUT', '/api/v1/notes/' . $created['id'], $payload);
        $second = $this->request('PUT', '/api/v1/notes/' . $created['id'], $payload);

        self::assertSame(200, $first->status);
        self::assertSame(200, $second->status);
        self::assertSame($this->data($first), $this->data($second));
        self::assertSame($this->data($first)['updatedAt'], $this->data($second)['updatedAt']);
    }

    /**
     * Проверка идемпотентности «в ту же секунду» ничего не доказывает: updatedAt
     * совпал бы и без неё, потому что метка времени имеет секундную точность.
     * Поэтому метка в хранилище сдвигается в прошлое руками, и тест падает,
     * если сервис всё-таки перезаписывает запись.
     */
    public function testRepeatedPutDoesNotTouchUpdatedAt(): void
    {
        $payload = ['title' => 'Заметка', 'content' => 'Текст', 'tags' => ['Работа']];

        $created = $this->request('POST', '/api/v1/notes', $payload);
        $id = $this->data($created)['id'];

        $past = '2020-01-01T00:00:00+00:00';
        $this->rewriteStoredField($id, 'updated_at', $past);

        $response = $this->request('PUT', '/api/v1/notes/' . $id, $payload);

        self::assertSame(200, $response->status);
        self::assertSame($past, $this->data($response)['updatedAt']);
    }

    public function testChangedPutDoesTouchUpdatedAt(): void
    {
        $created = $this->request('POST', '/api/v1/notes', ['title' => 'Заметка', 'tags' => ['Работа']]);
        $id = $this->data($created)['id'];

        $past = '2020-01-01T00:00:00+00:00';
        $this->rewriteStoredField($id, 'updated_at', $past);

        $response = $this->request('PUT', '/api/v1/notes/' . $id, ['title' => 'Другой заголовок']);

        self::assertSame(200, $response->status);
        self::assertNotSame($past, $this->data($response)['updatedAt']);
    }

    private function rewriteStoredField(string $id, string $field, string $value): void
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = json_decode((string)file_get_contents($this->storagePath), true);

        foreach ($rows as $index => $row) {
            if ($row['id'] === $id) {
                $rows[$index][$field] = $value;
            }
        }

        file_put_contents($this->storagePath, json_encode($rows, JSON_UNESCAPED_UNICODE));
    }

    public function testReplaceUnknownIdReturnsNotFound(): void
    {
        $response = $this->request('PUT', '/api/v1/notes/00000000-0000-4000-8000-000000000000', [
            'title' => 'Заметка',
        ]);

        self::assertSame(404, $response->status);
    }

    public function testDeletesNote(): void
    {
        $created = $this->createNote('На удаление');

        $deleted = $this->request('DELETE', '/api/v1/notes/' . $created['id']);

        self::assertSame(204, $deleted->status);
        self::assertNull($deleted->body);
        self::assertSame(404, $this->request('GET', '/api/v1/notes/' . $created['id'])->status);
    }

    public function testRepeatedDeleteReturnsNotFound(): void
    {
        $created = $this->createNote('На удаление');

        $this->request('DELETE', '/api/v1/notes/' . $created['id']);
        $second = $this->request('DELETE', '/api/v1/notes/' . $created['id']);

        self::assertSame(404, $second->status);
    }

    public function testUnknownRouteReturnsNotFound(): void
    {
        $response = $this->request('GET', '/api/v1/unknown');

        self::assertSame(404, $response->status);
        self::assertSame('ROUTE_NOT_FOUND', $this->error($response)['code']);
    }

    public function testUnsupportedMethodReturnsMethodNotAllowed(): void
    {
        $created = $this->createNote('Заметка');

        $response = $this->request('PATCH', '/api/v1/notes/' . $created['id'], ['title' => 'Другая']);

        self::assertSame(405, $response->status);
        self::assertSame('METHOD_NOT_ALLOWED', $this->error($response)['code']);
    }

    public function testErrorBodyNeverLeaksStackTrace(): void
    {
        $response = $this->request('GET', '/api/v1/notes/00000000-0000-4000-8000-000000000000');

        $encoded = $response->encodedBody();

        self::assertStringNotContainsString('#0', $encoded);
        self::assertStringNotContainsString('.php', $encoded);
    }
}
