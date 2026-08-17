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

    public function testSortsByCountThenAlphabetically(): void
    {
        $this->createNote('Первая', ['работа', 'быт']);
        $this->createNote('Вторая', ['работа', 'авто']);
        $this->createNote('Третья', ['работа']);

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
        self::assertArrayHasKey('limit', $this->error($response)['fields']);
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
}
