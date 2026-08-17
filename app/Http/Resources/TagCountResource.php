<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTO\Response\TagCountDto;

final readonly class TagCountResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(TagCountDto $tag): array
    {
        return [
            'tag' => $tag->tag,
            'count' => $tag->count,
        ];
    }

    /**
     * @param list<TagCountDto> $tags
     * @return list<array<string, mixed>>
     */
    public static function collection(array $tags): array
    {
        return array_map(static fn (TagCountDto $tag): array => self::make($tag), $tags);
    }
}
