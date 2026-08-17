<?php

declare(strict_types=1);

namespace App\Interfaces\Repositories;

use App\DTO\Create\CreateNoteDto;
use App\DTO\Helper\NoteFilterDto;
use App\DTO\RepositoryMapModel\NoteMapDto;
use App\DTO\Update\UpdateNoteDto;
use App\Exceptions\System\NotFoundException;
use App\Exceptions\System\StorageException;

interface NoteRepositoryInterface
{
    /**
     * Пустая выборка — это [], а не исключение.
     *
     * @return list<NoteMapDto>
     * @throws StorageException
     */
    public function getNotes(NoteFilterDto $filter): array;

    /**
     * @return list<NoteMapDto>
     * @throws StorageException
     */
    public function getAllNotes(): array;

    /**
     * @throws StorageException
     */
    public function findNoteById(string $id): ?NoteMapDto;

    /**
     * @throws NotFoundException заметки с таким id нет
     * @throws StorageException
     */
    public function getNoteById(string $id): NoteMapDto;

    /**
     * @throws StorageException
     */
    public function createNote(CreateNoteDto $note): NoteMapDto;

    /**
     * @throws NotFoundException
     * @throws StorageException
     */
    public function replaceNote(string $id, UpdateNoteDto $note): NoteMapDto;

    /**
     * @throws NotFoundException
     * @throws StorageException
     */
    public function deleteNote(string $id): void;
}
