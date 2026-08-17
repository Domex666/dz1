<?php

declare(strict_types=1);

namespace App\DTO\Response;

final readonly class TagCountDto
{
    public function __construct(
        public string $tag,
        public int $count,
    ) {
    }
}
