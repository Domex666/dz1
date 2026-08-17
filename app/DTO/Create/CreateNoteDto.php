<?php

declare(strict_types=1);

namespace App\DTO\Create;

final readonly class CreateNoteDto
{
    /**
     * @param list<string> $tags уже нормализованные теги
     */
    public function __construct(
        public string $title,
        public string $content,
        public array $tags,
    ) {
    }
}
