<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Доменный хелпер: правила нормализации тегов из SPEC.md.
 * Лежит в App\Helpers, а не в App\Support, потому что «тег» — слово из предметной области.
 */
final class TagHelper
{
    public const int MAX_TAG_LENGTH = 32;
    public const int MAX_TAGS_PER_NOTE = 10;

    /**
     * trim + верхний регистр. mb_strtoupper, а не strtoupper: strtoupper
     * не трогает кириллицу, и «работа» осталась бы в нижнем регистре.
     */
    public static function normalize(string $tag): string
    {
        return mb_strtoupper(trim($tag), 'UTF-8');
    }

    /**
     * Нормализация плюс дедупликация после неё, порядок первого вхождения сохраняется.
     * Важен именно этот порядок: сначала привести к общему виду, потом убрать дубли —
     * иначе «Работа» и «работа» останутся двумя разными тегами.
     *
     * @param string[] $tags
     * @return list<string>
     */
    public static function normalizeList(array $tags): array
    {
        $normalized = array_map(static fn (string $tag): string => self::normalize($tag), $tags);

        return array_values(array_unique($normalized));
    }
}
