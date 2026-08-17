<?php

declare(strict_types=1);

namespace App\DTO\Helper;

use App\Enums\TagFilterModeEnum;

final readonly class NoteFilterDto
{
    /**
     * @param list<string> $tags уже нормализованные теги
     */
    public function __construct(
        public array $tags,
        public TagFilterModeEnum $mode,
    ) {
    }
}
