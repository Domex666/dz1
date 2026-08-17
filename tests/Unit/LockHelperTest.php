<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Helpers\Lock\LockHelper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Аудит показал: удаление flock целиком не роняло ни одного теста, при этом
 * на 60 параллельных POST терялось 6 записей. Блокировка работала, но её
 * исчезновение ничем не ловилось.
 *
 * Проверка идёт через второй дескриптор того же lock-файла: flock(2) выдаёт
 * блокировку на открытый файловый дескриптор, поэтому LOCK_NB со второго
 * дескриптора обязан получить отказ, пока первый её держит.
 */
final class LockHelperTest extends TestCase
{
    private const string KEY = 'notes-api-test-lock';

    public function testLockIsHeldWhileCallbackRuns(): void
    {
        $acquiredInside = null;

        LockHelper::lock(self::KEY, function () use (&$acquiredInside): void {
            $acquiredInside = $this->tryAcquire();
        });

        self::assertFalse($acquiredInside, 'Во время выполнения callback блокировка должна быть занята');
    }

    public function testLockIsReleasedAfterCallback(): void
    {
        LockHelper::lock(self::KEY, static fn (): bool => true);

        self::assertTrue($this->tryAcquire(), 'После выхода блокировка должна быть отпущена');
    }

    public function testLockIsReleasedWhenCallbackThrows(): void
    {
        try {
            LockHelper::lock(self::KEY, static function (): void {
                throw new RuntimeException('падение внутри блокировки');
            });
        } catch (RuntimeException) {
            // Исключение обязано пролететь наружу, это проверяется ниже.
        }

        self::assertTrue($this->tryAcquire(), 'Блокировка должна отпускаться и при исключении');
    }

    public function testCallbackExceptionIsNotSwallowed(): void
    {
        $this->expectException(RuntimeException::class);

        LockHelper::lock(self::KEY, static function (): void {
            throw new RuntimeException('падение внутри блокировки');
        });
    }

    public function testCallbackResultIsReturned(): void
    {
        self::assertSame('результат', LockHelper::lock(self::KEY, static fn (): string => 'результат'));
    }

    private function tryAcquire(): bool
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'notes-api-' . md5(self::KEY) . '.lock';
        $handle = fopen($path, 'c');

        self::assertNotFalse($handle);

        $acquired = flock($handle, LOCK_EX | LOCK_NB);

        if ($acquired) {
            flock($handle, LOCK_UN);
        }

        fclose($handle);

        return $acquired;
    }
}
