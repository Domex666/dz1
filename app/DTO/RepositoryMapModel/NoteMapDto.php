<?php

declare(strict_types=1);

namespace App\DTO\RepositoryMapModel;

/**
 * То, что репозиторий отдаёт наружу вместо сырой записи из файла.
 * Ни один слой выше не видит массив из json_decode.
 */
final readonly class NoteMapDto
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $content,
        public array $tags,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
