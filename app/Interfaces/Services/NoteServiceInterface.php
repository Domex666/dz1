<?php

declare(strict_types=1);

namespace App\Interfaces\Services;

use App\DTO\Create\CreateNoteDto;
use App\DTO\Helper\NoteFilterDto;
use App\DTO\Response\NoteResponseDto;
use App\DTO\Update\UpdateNoteDto;
use Throwable;

/**
 * Наружу отдаётся NoteResponseDto из DTO\Response, а не NoteMapDto из слоя хранилища:
 * контракт сервиса не должен зависеть от того, как запись лежит в файле.
 */
interface NoteServiceInterface
{
    /**
     * @return list<NoteResponseDto>
     * @throws Throwable
     */
    public function getNotes(NoteFilterDto $filter): array;

    /**
     * @throws Throwable
     */
    public function getNote(string $id): NoteResponseDto;

    /**
     * @throws Throwable
     */
    public function create(CreateNoteDto $note): NoteResponseDto;

    /**
     * Полная замена. Повторная отправка того же тела идемпотентна:
     * возвращает то же состояние и не двигает updatedAt.
     *
     * @throws Throwable
     */
    public function replace(string $id, UpdateNoteDto $note): NoteResponseDto;

    /**
     * @throws Throwable
     */
    public function delete(string $id): void;
}
