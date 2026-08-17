<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTO\RepositoryMapModel\NoteMapDto;

final readonly class NoteResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(NoteMapDto $note): array
    {
        return [
            'id' => $note->id,
            'title' => $note->title,
            'content' => $note->content,
            'tags' => $note->tags,
            'createdAt' => $note->createdAt,
            'updatedAt' => $note->updatedAt,
        ];
    }

    /**
     * @param list<NoteMapDto> $notes
     * @return list<array<string, mixed>>
     */
    public static function collection(array $notes): array
    {
        return array_map(static fn (NoteMapDto $note): array => self::make($note), $notes);
    }
}
