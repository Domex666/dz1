<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTO\Response\NoteResponseDto;

final readonly class NoteResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(NoteResponseDto $note): array
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
     * @param list<NoteResponseDto> $notes
     * @return list<array<string, mixed>>
     */
    public static function collection(array $notes): array
    {
        return array_map(static fn (NoteResponseDto $note): array => self::make($note), $notes);
    }
}
