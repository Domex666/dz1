<?php

declare(strict_types=1);

namespace App\Enums;

enum TagFilterModeEnum: string
{
    case ALL = 'all';
    case ANY = 'any';

    public static function default(): self
    {
        return self::ALL;
    }

    /**
     * @param string[] $noteTags   нормализованные теги заметки
     * @param string[] $filterTags нормализованные теги из запроса
     */
    public function matches(array $noteTags, array $filterTags): bool
    {
        if ($filterTags === []) {
            return true;
        }

        $intersection = array_intersect($filterTags, $noteTags);

        return match ($this) {
            self::ALL => count($intersection) === count($filterTags),
            self::ANY => $intersection !== [],
        };
    }
}
