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
        self::assertArrayHasKey('mode', $this->fields($response));
    }

    public function testEmptyTagSegmentIsRejected(): void
    {
        $response = $this->request('GET', '/api/v1/notes', query: ['tags' => 'работа,,дом']);

        self::assertSame(422, $response->status);
        self::assertArrayHasKey('tags.1', $this->fields($response));
    }

    /**
     * Прежняя версия этой проверки ничего не доказывала: три заметки создавались
     * в одну секунду, массив createdAt состоял из трёх одинаковых строк,
     * и rsort его не менял — сравнение проходило при любом порядке.
     * Удаление usort целиком тест не роняло. Теперь метки задаются явно.
     */
    public function testListIsSortedByCreatedAtDescending(): void
    {
        $this->seedStorage([
            $this->noteRow('11111111-1111-4111-8111-111111111111', 'Старая', [], '2026-01-01T00:00:00+00:00'),
            $this->noteRow('22222222-2222-4222-8222-222222222222', 'Новая', [], '2026-03-03T00:00:00+00:00'),
            $this->noteRow('33333333-3333-4333-8333-333333333333', 'Средняя', [], '2026-02-02T00:00:00+00:00'),
        ]);

        $items = $this->data($this->request('GET', '/api/v1/notes'))['items'];

        self::assertSame(['Новая', 'Средняя', 'Старая'], array_column($items, 'title'));
    }

    /**
     * Тай-брейк по id объявлен в SPEC явно — ради воспроизводимого порядка.
     * До этого теста он не проверялся ни одним ассертом.
     */
    public function testEqualCreatedAtIsBrokenByIdAscending(): void
    {
        $sameMoment = '2026-01-01T00:00:00+00:00';

        $this->seedStorage([
            $this->noteRow('cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'Третья', [], $sameMoment),
            $this->noteRow('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'Первая', [], $sameMoment),
            $this->noteRow('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Вторая', [], $sameMoment),
        ]);

        $items = $this->data($this->request('GET', '/api/v1/notes'))['items'];

        self::assertSame(['Первая', 'Вторая', 'Третья'], array_column($items, 'title'));
    }

    public function testWhitespaceOnlyTagSegmentIsRejected(): void
    {
        // Один пробельный сегмент раньше проглатывался как «фильтра нет»,
        // хотя два таких же давали 422.
        $response = $this->request('GET', '/api/v1/notes', query: ['tags' => ' ']);

        self::assertSame(422, $response->status);
        self::assertArrayHasKey('tags.0', $this->fields($response));
    }

    public function testEmptyTagsParameterMeansNoFilter(): void
    {
        $response = $this->request('GET', '/api/v1/notes', query: ['tags' => '']);

        self::assertSame(200, $response->status);
        self::assertCount(3, $this->data($response)['items']);
    }

    public function testArrayModeParameterIsRejected(): void
    {
        // ?mode[]=wrong молча подменялся значением по умолчанию,
        // и клиент получал не тот набор данных без признака ошибки.
        $response = $this->request('GET', '/api/v1/notes', query: ['mode' => ['any']]);

        self::assertSame(422, $response->status);
        self::assertArrayHasKey('mode', $this->fields($response));
    }

    public function testArrayTagsParameterIsRejected(): void
    {
        $response = $this->request('GET', '/api/v1/notes', query: ['tags' => ['дом']]);

        self::assertSame(422, $response->status);
        self::assertArrayHasKey('tags', $this->fields($response));
    }

    public function testAnyModeWithoutTagsReturnsEverything(): void
    {
        // Непокрытая комбинация: убрать ранний выход при пустом фильтре —
        // и mode=any начинал возвращать ноль заметок вместо всех.
        $response = $this->request('GET', '/api/v1/notes', query: ['mode' => 'any']);

        self::assertSame(200, $response->status);
        self::assertCount(3, $this->data($response)['items']);
    }
}
