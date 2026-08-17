<?php

declare(strict_types=1);

namespace Tests\Feature;

final class NoteFilterTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createNote('Первая', ['работа', 'срочное']);
        $this->createNote('Вторая', ['работа']);
        $this->createNote('Третья', ['дом']);
    }

    public function testReturnsAllNotesWithoutFilter(): void
    {
        $response = $this->request('GET', '/api/v1/notes');

        self::assertSame(200, $response->status);
        self::assertCount(3, $this->data($response)['items']);
    }

    public function testDefaultModeRequiresAllTags(): void
    {
        $response = $this->request('GET', '/api/v1/notes', query: ['tags' => 'работа,срочное']);

        self::assertSame(200, $response->status);

        $items = $this->data($response)['items'];

        self::assertCount(1, $items);
        self::assertSame('Первая', $items[0]['title']);
    }

    public function testAnyModeRequiresAtLeastOneTag(): void
    {
        $response = $this->request('GET', '/api/v1/notes', query: [
            'tags' => 'срочное,дом',
            'mode' => 'any',
        ]);

        self::assertCount(2, $this->data($response)['items']);
    }

    public function testFilterIsCaseInsensitive(): void
    {
        $lower = $this->request('GET', '/api/v1/notes', query: ['tags' => 'работа']);
        $upper = $this->request('GET', '/api/v1/notes', query: ['tags' => 'РАБОТА']);

        self::assertCount(2, $this->data($lower)['items']);
        self::assertSame($this->data($lower), $this->data($upper));
    }

    public function testUnknownTagReturnsEmptyList(): void
    {
        $response = $this->request('GET', '/api/v1/notes', query: ['tags' => 'такого-тега-нет']);

        self::assertSame(200, $response->status);
        self::assertSame([], $this->data($response)['items']);
    }

    public function testInvalidModeIsRejected(): void
    {
        $response = $this->request('GET', '/api/v1/notes', query: ['mode' => 'wrong']);

        self::assertSame(422, $response->status);
        self::assertArrayHasKey('mode', $this->error($response)['fields']);
    }

    public function testEmptyTagSegmentIsRejected(): void
    {
        $response = $this->request('GET', '/api/v1/notes', query: ['tags' => 'работа,,дом']);

        self::assertSame(422, $response->status);
        self::assertArrayHasKey('tags.1', $this->error($response)['fields']);
    }

    public function testListIsSortedByCreatedAtDescending(): void
    {
        $items = $this->data($this->request('GET', '/api/v1/notes'))['items'];

        $createdAt = array_column($items, 'createdAt');
        $sorted = $createdAt;
        rsort($sorted);

        self::assertSame($sorted, $createdAt);
    }
}
