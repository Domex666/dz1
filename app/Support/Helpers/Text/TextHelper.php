<?php

declare(strict_types=1);

namespace App\Support\Helpers\Text;

/**
 * Инфраструктурная работа со строками. Ни одного слова из предметной области.
 */
final class TextHelper
{
    /**
     * trim(), который снимает и юникод-пробелы.
     *
     * Встроенный trim() режет только ASCII (` \t\n\r\0\x0B`), поэтому тег из одного
     * неразрывного пробела U+00A0 проходил валидацию и сохранялся визуально пустым.
     * \p{Z} покрывает NBSP, U+2000–U+200A, U+202F, U+205F, U+3000;
     * \x{FEFF} добавлен отдельно — это Cf, а не Z.
     */
    public static function trim(string $value): string
    {
        return (string)preg_replace('/^[\p{Z}\s\x{FEFF}]+|[\p{Z}\s\x{FEFF}]+$/u', '', $value);
    }
}
