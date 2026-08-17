<?php

declare(strict_types=1);

namespace Tests\Feature;

final class TagAnalyticsTest extends FeatureTestCase
{
    public function testEmptyStorageReturnsEmptyList(): void
    {
        $response = $this->request('GET', '/api/v1/tags/top');

        self::assertSame(200, $response->status);
        self::assertSame([], $this->data($response)['items']);
    }

    public function testCountsNotesNotMentions(): void
    {
        // Дубли внутри одной заметки схлопываются при нормализации,
        // поэтому «работа» здесь должна посчитаться один раз, а не три.
        $this->createNote('Первая', ['работа', 'Работа', 'РАБОТА']);

        $items = $this->data($this->request('GET', '/api/v1/tags/top'))['items'];

        self::assertSame([['tag' => 'РАБОТА', 'count' => 1]], $items);
    }

    /**
     * Метки времени задаются явно. Раньше три заметки создавались в одну секунду,
     * порядок обхода определялся случайными UUID, и снятие алфавитного тай-брейка
     * ловилось примерно в 58% прогонов — тест был монеткой, а не проверкой.
     */
    public function testSortsByCountThenAlphabetically(): void
    {
        $this->seedStorage([
            $this->noteRow('11111111-1111-4111-8111-111111111111', 'Первая', ['РАБОТА', 'БЫТ'], '2026-01-03T00:00:00+00:00'),
            $this->noteRow('22222222-2222-4222-8222-222222222222', 'Вторая', ['РАБОТА', 'АВТО'], '2026-01-02T00:00:00+00:00'),
            $this->noteRow('33333333-3333-4333-8333-333333333333', 'Третья', ['РАБОТА'], '2026-01-01T00:00:00+00:00'),
        ]);

        $items = $this->data($this->request('GET', '/api/v1/tags/top'))['items'];

        self::assertSame(
            [
                ['tag' => 'РАБОТА', 'count' => 3],
                ['tag' => 'АВТО', 'count' => 1],
                ['tag' => 'БЫТ', 'count' => 1],
            ],
            $items
        );
    }

    public function testRespectsLimit(): void
    {
        $this->createNote('Первая', ['a', 'b', 'c']);

        $items = $this->data($this->request('GET', '/api/v1/tags/top', query: ['limit' => '2']))['items'];

        self::assertCount(2, $items);
    }

    public function testDefaultLimitIsTen(): void
    {
        $this->createNote('Первая', ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j']);
        $this->createNote('Вторая', ['k', 'l']);

        $items = $this->data($this->request('GET', '/api/v1/tags/top'))['items'];

        self::assertCount(10, $items);
    }

    public function testZeroLimitIsRejected(): void
    {
        $response = $this->request('GET', '/api/v1/tags/top', query: ['limit' => '0']);

        self::assertSame(422, $response->status);
        self::assertArrayHasKey('limit', $this->fields($response));
    }

    public function testNonNumericLimitIsRejected(): void
    {
        $response = $this->request('GET', '/api/v1/tags/top', query: ['limit' => 'abc']);

        self::assertSame(422, $response->status);
    }

    public function testLimitAboveMaximumIsRejected(): void
    {
        $response = $this->request('GET', '/api/v1/tags/top', query: ['limit' => '101']);

        self::assertSame(422, $response->status);
    }

    public function testLimitBoundariesAreAccepted(): void
    {
        self::assertSame(200, $this->request('GET', '/api/v1/tags/top', query: ['limit' => '1'])->status);
        self::assertSame(200, $this->request('GET', '/api/v1/tags/top', query: ['limit' => '100'])->status);
    }

    public function testLeadingZeroLimitIsRejected(): void
    {
        // «007» принимался как 7. Принимать его — значит угадывать за клиента.
        $response = $this->request('GET', '/api/v1/tags/top', query: ['limit' => '007']);

        self::assertSame(422, $response->status);
        self::assertArrayHasKey('limit', $this->fields($response));
    }

    public function testArrayLimitParameterIsRejected(): void
    {
        $response = $this->request('GET', '/api/v1/tags/top', query: ['limit' => ['5']]);

        self::assertSame(422, $response->status);
    }

    public function testCountsNotesEvenWhenFileHasDuplicateTagsInOneNote(): void
    {
        // Дедуп на записи — инвариант, а не гарантия чтения: файл, отредактированный
        // руками, его не соблюдает, а контракт обещает число заметок.
        $this->seedStorage([
            $this->noteRow('11111111-1111-4111-8111-111111111111', 'Первая', ['РАБОТА', 'РАБОТА']),
        ]);

        $items = $this->data($this->request('GET', '/api/v1/tags/top'))['items'];

        self::assertSame([['tag' => 'РАБОТА', 'count' => 1]], $items);
    }
}
