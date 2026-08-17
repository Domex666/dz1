<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Create\CreateNoteDto;
use App\DTO\Helper\NoteFilterDto;
use App\DTO\RepositoryMapModel\NoteMapDto;
use App\DTO\Update\UpdateNoteDto;
use App\Interfaces\Repositories\NoteRepositoryInterface;
use App\Services\Note\NoteService;
use PHPUnit\Framework\TestCase;

/**
 * `LockHelperTest` проверяет сам `LockHelper`, но не то, что сервис им пользуется.
 * Аудит показал: удаление `lock()` из `create`, `replace` и `delete` не роняло
 * ни одного теста, а живьём 60 параллельных POST давали 51 заметку.
 * Дыру закрыли на уровень ниже, чем нужно.
 *
 * Здесь репозиторий-двойник изнутри операции пытается взять ту же блокировку
 * вторым дескриптором. Если сервис её держит, попытка обязана провалиться.
 */
final class NoteServiceLockTest extends TestCase
{
    public function testCreateHoldsLockWhileRepositoryWorks(): void
    {
        $repository = $this->probingRepository();
        new NoteService($repository)->create(new CreateNoteDto('Заметка', '', []));

        self::assertFalse($repository->lockWasFree, 'create обязан выполняться под блокировкой');
    }

    public function testReplaceHoldsLockWhileRepositoryWorks(): void
    {
        $repository = $this->probingRepository();
        new NoteService($repository)->replace('id-1', new UpdateNoteDto('Другая', '', []));

        self::assertFalse($repository->lockWasFree, 'replace обязан выполняться под блокировкой');
    }

    public function testDeleteHoldsLockWhileRepositoryWorks(): void
    {
        $repository = $this->probingRepository();
        new NoteService($repository)->delete('id-1');

        self::assertFalse($repository->lockWasFree, 'delete обязан выполняться под блокировкой');
    }

    public function testReadingDoesNotRequireLock(): void
    {
        // Чтение блокировку не берёт — это осознанно, и тест фиксирует именно это,
        // чтобы «взяли блокировку везде» не проехало незамеченным.
        $repository = $this->probingRepository();
        new NoteService($repository)->getNote('id-1');

        self::assertTrue($repository->lockWasFree, 'чтение не должно держать блокировку');
    }

    private function probingRepository(): NoteRepositoryInterface
    {
        return new class implements NoteRepositoryInterface {
            public ?bool $lockWasFree = null;

            public function getNotes(NoteFilterDto $filter): array
            {
                $this->probe();

                return [];
            }

            public function getAllNotes(): array
            {
                $this->probe();

                return [];
            }

            public function findNoteById(string $id): ?NoteMapDto
            {
                $this->probe();

                return $this->note();
            }

            public function getNoteById(string $id): NoteMapDto
            {
                $this->probe();

                return $this->note();
            }

            public function createNote(CreateNoteDto $note): NoteMapDto
            {
                $this->probe();

                return $this->note();
            }

            public function replaceNote(string $id, UpdateNoteDto $note): NoteMapDto
            {
                $this->probe();

                return $this->note();
            }

            public function deleteNote(string $id): void
            {
                $this->probe();
            }

            /**
             * Пытается взять ту же блокировку вторым дескриптором.
             * flock(2) выдаётся на открытый файловый дескриптор, поэтому пока
             * первый её держит, LOCK_NB со второго обязан получить отказ.
             */
            private function probe(): void
            {
                if ($this->lockWasFree !== null) {
                    return;
                }

                $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'notes-api-' . md5('notes-storage') . '.lock';
                $handle = fopen($path, 'c');

                if ($handle === false) {
                    $this->lockWasFree = true;

                    return;
                }

                $acquired = flock($handle, LOCK_EX | LOCK_NB);

                if ($acquired) {
                    flock($handle, LOCK_UN);
                }

                fclose($handle);

                $this->lockWasFree = $acquired;
            }

            private function note(): NoteMapDto
            {
                return new NoteMapDto(
                    id: 'id-1',
                    title: 'Заметка',
                    content: '',
                    tags: [],
                    createdAt: '2026-01-01T00:00:00+00:00',
                    updatedAt: '2026-01-01T00:00:00+00:00',
                );
            }
        };
    }
}
