<?php

declare(strict_types=1);

namespace App\DTO\Update;

/**
 * PUT — полная замена, поэтому набор полей совпадает с созданием.
 * Отдельный класс, а не переиспользование CreateNoteDto: у них разные слои-источники
 * и разойтись они могут в любой момент.
 */
final readonly class UpdateNoteDto
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
