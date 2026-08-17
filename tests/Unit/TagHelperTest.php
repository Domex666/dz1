<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\TagHelper;
use PHPUnit\Framework\TestCase;

final class TagHelperTest extends TestCase
{
    public function testTrimsAndUppercases(): void
    {
        self::assertSame('РАБОТА', TagHelper::normalize('  Работа '));
        self::assertSame('WORK', TagHelper::normalize('Work'));
    }

    public function testUppercasesCyrillic(): void
    {
        // strtoupper() кириллицу не трогает — здесь ловится подмена mb_strtoupper на неё.
        self::assertSame('ЁЛКА', TagHelper::normalize('ёлка'));
    }

    public function testWhitespaceOnlyTagBecomesEmptyString(): void
    {
        self::assertSame('', TagHelper::normalize('   '));
    }

    public function testDeduplicatesAfterNormalization(): void
    {
        self::assertSame(
            ['РАБОТА', 'WORK'],
            TagHelper::normalizeList(['  Работа ', 'работа', 'РАБОТА', 'Work'])
        );
    }

    public function testKeepsOrderOfFirstOccurrence(): void
    {
        self::assertSame(['Б', 'А'], TagHelper::normalizeList(['б', 'а', 'Б']));
    }

    public function testReturnsListWithSequentialKeys(): void
    {
        $result = TagHelper::normalizeList(['а', 'а', 'б']);

        self::assertSame([0, 1], array_keys($result));
    }
}
