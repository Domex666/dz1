<?php

declare(strict_types=1);

namespace App\Services\Note;

use App\DTO\Create\CreateNoteDto;
use App\DTO\Helper\NoteFilterDto;
use App\DTO\RepositoryMapModel\NoteMapDto;
use App\DTO\Response\NoteResponseDto;
use App\DTO\Update\UpdateNoteDto;
use App\Interfaces\Repositories\NoteRepositoryInterface;
use App\Interfaces\Services\NoteServiceInterface;
use App\Support\Helpers\Lock\LockHelper;
use Throwable;

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
     * @return list<NoteResponseDto>
     * @throws Throwable
     */
    public function getNotes(NoteFilterDto $filter): array
    {
        return array_map(
            fn (NoteMapDto $note): NoteResponseDto => $this->toResponseDto($note),
            $this->noteRepository->getNotes($filter)
        );
    }

    public function getNote(string $id): NoteResponseDto
    {
        return $this->toResponseDto($this->noteRepository->getNoteById($id));
    }

    public function create(CreateNoteDto $note): NoteResponseDto
    {
        $created = LockHelper::lock(
            self::LOCK_KEY,
            fn (): NoteMapDto => $this->noteRepository->createNote($note)
        );

        return $this->toResponseDto($created);
    }

    public function replace(string $id, UpdateNoteDto $note): NoteResponseDto
    {
        $replaced = LockHelper::lock(self::LOCK_KEY, function () use ($id, $note): NoteMapDto {
            $current = $this->noteRepository->getNoteById($id);

            // Идемпотентность: ретрай по таймауту не должен двигать updatedAt.
            // Сравнение идёт с уже нормализованным телом, поэтому «Работа» и «РАБОТА»
            // считаются одним и тем же запросом.
            if ($this->isUnchanged($current, $note)) {
                return $current;
            }

            return $this->noteRepository->replaceNote($id, $note);
        });

        return $this->toResponseDto($replaced);
    }

    public function delete(string $id): void
    {
        LockHelper::lock(self::LOCK_KEY, function () use ($id): void {
            $this->noteRepository->deleteNote($id);
        });
    }

    private function isUnchanged(NoteMapDto $current, UpdateNoteDto $note): bool
    {
        return $current->title === $note->title
            && $current->content === $note->content
            && $current->tags === $note->tags;
    }

    /**
     * Единственное место, где форма хранилища превращается в форму ответа.
     */
    private function toResponseDto(NoteMapDto $note): NoteResponseDto
    {
        return new NoteResponseDto(
            id: $note->id,
            title: $note->title,
            content: $note->content,
            tags: $note->tags,
            createdAt: $note->createdAt,
            updatedAt: $note->updatedAt,
        );
    }
}
