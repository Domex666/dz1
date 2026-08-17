<?php

declare(strict_types=1);

namespace App\Interfaces\Services;

use App\DTO\Create\CreateNoteDto;
use App\DTO\Helper\NoteFilterDto;
use App\DTO\RepositoryMapModel\NoteMapDto;
use App\DTO\Update\UpdateNoteDto;
use Throwable;

interface NoteServiceInterface
{
    /**
     * @return list<NoteMapDto>
     * @throws Throwable
     */
    public function getNotes(NoteFilterDto $filter): array;

    /**
     * @throws Throwable
     */
    public function getNote(string $id): NoteMapDto;

    /**
     * @throws Throwable
     */
    public function create(CreateNoteDto $note): NoteMapDto;

    /**
     * Полная замена. Повторная отправка того же тела идемпотентна:
     * возвращает то же состояние и не двигает updatedAt.
     *
     * @throws Throwable
     */
    public function replace(string $id, UpdateNoteDto $note): NoteMapDto;

    /**
     * @throws Throwable
     */
    public function delete(string $id): void;
}
