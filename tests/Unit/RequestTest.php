<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\System\BadRequestException;
use App\Exceptions\System\ResponseValidationException;
use App\Support\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Через Request::fromGlobals() не проходил ни один тест: фича-тесты собирают
 * Request руками. Мутация «путь всегда /» оставляла набор зелёным, при этом
 * живьём все эндпоинты отдавали 404.
 */
final class RequestTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup;

    /** @var array<string, mixed> */
    private array $getBackup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_GET = $this->getBackup;

        parent::tearDown();
    }

    public function testParsesMethodAndPath(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'post';
        $_SERVER['REQUEST_URI'] = '/api/v1/notes';
        $_GET = [];

        $request = Request::fromGlobals();

        self::assertSame('POST', $request->method, 'метод приводится к верхнему регистру');
        self::assertSame('/api/v1/notes', $request->path);
    }

    public function testDropsQueryStringFromPath(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/notes?tags=%D0%B4%D0%BE%D0%BC&mode=any';
        $_GET = ['tags' => 'дом', 'mode' => 'any'];

        $request = Request::fromGlobals();

        self::assertSame('/api/v1/notes', $request->path);
        self::assertSame('дом', $request->queryString('tags'));
        self::assertSame('any', $request->queryString('mode'));
    }

    public function testNormalizesTrailingSlash(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/notes/';
        $_GET = [];

        self::assertSame('/api/v1/notes', Request::fromGlobals()->path);
    }

    public function testKeepsRootPath(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_GET = [];

        self::assertSame('/', Request::fromGlobals()->path);
    }

    public function testEmptyBodyIsEmptyArray(): void
    {
        self::assertSame([], new Request('POST', '/x')->json());
    }

    public function testEmptyJsonObjectIsEmptyArray(): void
    {
        self::assertSame([], new Request('POST', '/x', rawBody: '{}')->json());
    }

    public function testTopLevelArrayIsRejected(): void
    {
        $this->expectException(BadRequestException::class);

        new Request('POST', '/x', rawBody: '[]')->json();
    }

    public function testScalarBodyIsRejected(): void
    {
        $this->expectException(BadRequestException::class);

        new Request('POST', '/x', rawBody: '42')->json();
    }

    public function testMalformedBodyIsRejected(): void
    {
        $this->expectException(BadRequestException::class);

        new Request('POST', '/x', rawBody: '{"title": ')->json();
    }

    public function testNestedObjectStaysObject(): void
    {
        // Иначе tags вида {"0":"a"} декодировался бы в список и проходил валидацию.
        $decoded = new Request('POST', '/x', rawBody: '{"tags":{"0":"a"}}')->json();

        self::assertIsObject($decoded['tags']);
    }

    public function testArrayQueryParameterIsRejected(): void
    {
        $this->expectException(ResponseValidationException::class);

        new Request('GET', '/x', query: ['mode' => ['any']])->queryString('mode');
    }

    public function testMissingQueryParameterFallsBackToDefault(): void
    {
        self::assertSame('all', new Request('GET', '/x')->queryString('mode', 'all'));
    }
}
