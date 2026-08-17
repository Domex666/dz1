<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ErrorCodeEnum;
use App\Exceptions\System\NotFoundException;
use App\Exceptions\System\ResponseValidationException;
use App\Support\Http\ExceptionRenderer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Аудит показал, что гарантия «трассировка стека наружу не уходит никогда»
 * не была проверена ничем: оба «антитрейсовых» теста били по телам, которые
 * собираются из енама и трассировку не могли бы содержать при любой реализации.
 * Добавление getTraceAsString() в обработчик не роняло ни одного из 50 тестов.
 *
 * Здесь тело ответа сравнивается целиком — лишний ключ уронит тест.
 */
final class ExceptionRendererTest extends TestCase
{
    public function testUnexpectedExceptionLeaksNothing(): void
    {
        $exception = new RuntimeException('/app/app/Support/Container.php:34 внутренняя деталь');

        $response = new ExceptionRenderer()->render($exception);

        self::assertSame(500, $response->status);
        self::assertSame(
            [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Внутренняя ошибка сервиса',
                ],
            ],
            $response->body
        );
    }

    public function testUnexpectedExceptionMessageDoesNotReachBody(): void
    {
        $response = new ExceptionRenderer()->render(new RuntimeException('секретный путь /app/x.php'));

        $encoded = $response->encodedBody();

        self::assertStringNotContainsString('.php', $encoded);
        self::assertStringNotContainsString('секретный', $encoded);
        self::assertStringNotContainsString('Container', $encoded);
    }

    public function testUnexpectedExceptionIsNotReportedAsStorageProblem(): void
    {
        // Раньше любая внутренняя ошибка приходила клиенту как STORAGE_FAILURE
        // и сообщала неверную причину.
        $response = new ExceptionRenderer()->render(new RuntimeException('boom'));

        self::assertStringNotContainsString(ErrorCodeEnum::STORAGE_FAILURE->value, $response->encodedBody());
    }

    public function testResponseExceptionKeepsItsOwnCodeAndBody(): void
    {
        $response = new ExceptionRenderer()->render(new NotFoundException());

        self::assertSame(404, $response->status);
        self::assertSame(
            [
                'success' => false,
                'error' => [
                    'code' => 'NOTE_NOT_FOUND',
                    'message' => 'Заметка не найдена',
                ],
            ],
            $response->body
        );
    }

    public function testValidationExceptionKeepsFieldsAsObject(): void
    {
        $response = new ExceptionRenderer()->render(new ResponseValidationException(['5' => ['Неизвестное поле']]));

        self::assertSame(422, $response->status);
        // Ключ из одних цифр обязан пережить сериализацию под своим именем.
        self::assertStringContainsString('{"5":["Неизвестное поле"]}', $response->encodedBody());
    }
}
