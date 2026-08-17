<?php

declare(strict_types=1);

namespace App\Services\Note;

use App\DTO\Create\CreateNoteDto;
use App\DTO\Helper\NoteFilterDto;
use App\DTO\RepositoryMapModel\NoteMapDto;
use App\DTO\Update\UpdateNoteDto;
use App\Interfaces\Repositories\NoteRepositoryInterface;
use App\Interfaces\Services\NoteServiceInterface;
use App\Support\Helpers\Lock\LockHelper;

/**
 * Оркестратор: расставляет порядок, работу делает репозиторий.
 * Про файл, JSON и пути не знает ничего.
 */
final readonly class NoteService implements NoteServiceInterface
{
    /**
     * Блокировка берётся на хранилище целиком: заметки лежат в одном файле,
     * и запись любой из них переписывает его весь.
     */
    private const string LOCK_KEY = 'notes-storage';

    public function __construct(private NoteRepositoryInterface $noteRepository)
    {
    }

    /**
     * @return list<NoteMapDto>
     */
    public function getNotes(NoteFilterDto $filter): array
    {
        return $this->noteRepository->getNotes($filter);
    }

    public function getNote(string $id): NoteMapDto
    {
        return $this->noteRepository->getNoteById($id);
    }

    public function create(CreateNoteDto $note): NoteMapDto
    {
        return LockHelper::lock(
            self::LOCK_KEY,
            fn (): NoteMapDto => $this->noteRepository->createNote($note)
        );
    }

    public function replace(string $id, UpdateNoteDto $note): NoteMapDto
    {
        return LockHelper::lock(self::LOCK_KEY, function () use ($id, $note): NoteMapDto {
            $current = $this->noteRepository->getNoteById($id);

            // Идемпотентность: ретрай по таймауту не должен двигать updatedAt.
            // Сравнение идёт с уже нормализованным телом, поэтому «Работа» и «РАБОТА»
            // считаются одним и тем же запросом.
            if ($this->isUnchanged($current, $note)) {
                return $current;
            }

            return $this->noteRepository->replaceNote($id, $note);
        });
    }

    public function delete(string $id): void
    {
        LockHelper::lock(self::LOCK_KEY, function () use ($id): void {
            // Явная проверка до удаления: повторный DELETE обязан вернуть 404,
            // а не молча отчитаться об успехе.
            $this->noteRepository->getNoteById($id);
            $this->noteRepository->deleteNote($id);
        });
    }

    private function isUnchanged(NoteMapDto $current, UpdateNoteDto $note): bool
    {
        return $current->title === $note->title
            && $current->content === $note->content
            && $current->tags === $note->tags;
    }
}
