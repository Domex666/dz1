<?php

declare(strict_types=1);

namespace Tests\Unit;

use ErrorException;
use PHPUnit\Framework\TestCase;

/**
 * bootstrap/errors.php — самый поздний по времени файл проекта и до этого теста
 * покрытый нулём проверок. Обе его существенные мутации выживали, и обе живьём
 * дают «201 Created на несостоявшейся записи», а в паре с display_errors —
 * дословно ту утечку абсолютного пути в text/html, которую докблок этого файла
 * объявляет невозможной.
 *
 * Обработчик ставится в tests/bootstrap.php, поэтому здесь проверяется
 * уже установленный глобальный обработчик, а не переустанавливается свой.
 */
final class ErrorHandlerTest extends TestCase
{
    public function testWarningBecomesException(): void
    {
        $this->expectException(ErrorException::class);

        file_get_contents('/nonexistent/path/' . bin2hex(random_bytes(8)));
    }

    public function testExceptionCarriesMessageOfTheWarning(): void
    {
        try {
            file_get_contents('/nonexistent/path/' . bin2hex(random_bytes(8)));
            self::fail('предупреждение должно было превратиться в исключение');
        } catch (ErrorException $exception) {
            self::assertStringContainsString('Failed to open stream', $exception->getMessage());
        }
    }

    public function testSuppressionOperatorStillWorks(): void
    {
        // Без проверки error_reporting() внутри обработчика подавленный @unlink()
        // в JsonStorageHelper::write() начал бы бросать исключение.
        $result = @file_get_contents('/nonexistent/path/' . bin2hex(random_bytes(8)));

        self::assertFalse($result);
    }

    public function testDisplayErrorsIsOff(): void
    {
        // Именно display_errors=1 в паре со снятым обработчиком печатает
        // предупреждение в тело ответа до отправки заголовков.
        self::assertSame('0', ini_get('display_errors'));
    }

    public function testHtmlErrorsAreOff(): void
    {
        self::assertSame('0', ini_get('html_errors'));
    }
}
