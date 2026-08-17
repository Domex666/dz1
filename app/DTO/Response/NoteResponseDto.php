<?php

declare(strict_types=1);

namespace App\DTO\Response;

/**
 * То, что уходит в Resource.
 *
 * Заведён отдельно от NoteMapDto намеренно: раньше DTO слоя хранилища типизировал
 * и Resource, и публичный контракт сервиса, то есть HTTP-слой знал форму записи в файле.
 * С этим DTO замена файла на базу остаётся правкой репозитория и маппинга в сервисе,
 * как и заявлено в SPEC.md.
 */
final readonly class NoteResponseDto
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
