<?php

declare(strict_types=1);

namespace App\Services\Note;

use App\DTO\Response\TagCountDto;
use App\Interfaces\Repositories\NoteRepositoryInterface;
use App\Interfaces\Services\TagAnalyticsServiceInterface;
use Throwable;

final readonly class TagAnalyticsService implements TagAnalyticsServiceInterface
{
    public function __construct(private NoteRepositoryInterface $noteRepository)
    {
    }

    /**
     * @return list<TagCountDto>
     * @throws Throwable
     */
    public function getTopTags(int $limit): array
    {
        $counts = [];

        foreach ($this->noteRepository->getAllNotes() as $note) {
            // array_unique обязателен: контракт обещает число ЗАМЕТОК с тегом,
            // а не число упоминаний. Дедуп на записи — инвариант, а не гарантия
            // чтения: файл, отредактированный руками, его не соблюдает.
            foreach (array_unique($note->tags) as $tag) {
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }

        $items = [];

        foreach ($counts as $tag => $count) {
            $items[] = new TagCountDto(tag: (string)$tag, count: $count);
        }

        // count по убыванию, при равенстве tag по алфавиту.
        // Тай-брейк обязателен: иначе порядок зависит от порядка записей в файле.
        usort(
            $items,
            static fn (TagCountDto $left, TagCountDto $right): int
                => [$right->count, $left->tag] <=> [$left->count, $right->tag]
        );

        return array_slice($items, 0, $limit);
    }
}
