<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ExceptionStatusCodeEnum;
use App\Support\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Response::send() не выполнялся ни одним тестом: фича-тесты читают объект Response
 * и до отправки не доходят. Мутация «убрать http_response_code()» оставляла набор
 * зелёным, а живьём все ответы становились 200 — включая 404 и 422.
 *
 * Заголовки в CLI проверить нельзя: header() там не работает и headers_list()
 * пуст. Поэтому проверяются код ответа и тело; Content-Type остаётся
 * за пределами набора и закрыт проверкой в scripts/smoke.sh.
 */
final class ResponseTest extends TestCase
{
    protected function tearDown(): void
    {
        http_response_code(200);

        parent::tearDown();
    }

    public function testSendSetsStatusCode(): void
    {
        $this->capture(new Response(404, ['success' => false]));

        self::assertSame(404, http_response_code());
    }

    public function testSendWritesEncodedBody(): void
    {
        $body = $this->capture(Response::success(['items' => []]));

        self::assertSame('{"success":true,"data":{"items":[]}}', $body);
    }

    public function testNoContentSendsEmptyBodyWithStatus204(): void
    {
        $body = $this->capture(Response::noContent());

        self::assertSame('', $body);
        self::assertSame(204, http_response_code());
    }

    public function testSuccessWrapsDataAndDefaultsTo200(): void
    {
        $response = Response::success(['id' => 'x']);

        self::assertSame(200, $response->status);
        self::assertSame(['success' => true, 'data' => ['id' => 'x']], $response->body);
    }

    public function testErrorUsesStatusFromEnum(): void
    {
        $response = Response::error(ExceptionStatusCodeEnum::UNPROCESSABLE_ENTITY, ['success' => false]);

        self::assertSame(422, $response->status);
    }

    public function testCyrillicIsNotEscaped(): void
    {
        self::assertStringContainsString('РАБОТА', Response::success(['tag' => 'РАБОТА'])->encodedBody());
    }

    public function testSlashesAreNotEscaped(): void
    {
        self::assertStringContainsString('/api/v1', Response::success(['path' => '/api/v1'])->encodedBody());
    }

    private function capture(Response $response): string
    {
        ob_start();
        $response->send();

        return (string)ob_get_clean();
    }
}
