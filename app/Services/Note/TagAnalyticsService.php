<?php

declare(strict_types=1);

namespace App\Services\Note;

use App\DTO\Response\TagCountDto;
use App\Interfaces\Repositories\NoteRepositoryInterface;
use App\Interfaces\Services\TagAnalyticsServiceInterface;

final readonly class TagAnalyticsService implements TagAnalyticsServiceInterface
{
    public function __construct(private NoteRepositoryInterface $noteRepository)
    {
    }

    /**
     * @return list<TagCountDto>
     */
    public function getTopTags(int $limit): array
    {
        $counts = [];

        foreach ($this->noteRepository->getAllNotes() as $note) {
            foreach ($note->tags as $tag) {
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
