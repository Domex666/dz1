<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateNoteRequest;
use App\Http\Requests\ListNotesRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Interfaces\Services\NoteServiceInterface;
use App\Support\Http\Request;
use App\Support\Http\Response;
use Throwable;

/**
 * Три шага в каждом методе: собрать DTO, вызвать контракт сервиса, вернуть ответ.
 * Ни репозиториев, ни хранилища, ни try/catch.
 */
final readonly class NoteController extends Controller
{
    public function __construct(
        private NoteServiceInterface $noteService,
        private CreateNoteRequest $createNoteRequest,
        private UpdateNoteRequest $updateNoteRequest,
        private ListNotesRequest $listNotesRequest,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function index(Request $request): Response
    {
        $notes = $this->noteService->getNotes($this->listNotesRequest->toDto($request));

        return $this->successResponse(['items' => NoteResource::collection($notes)]);
    }

    /**
     * @throws Throwable
     */
    public function show(string $id): Response
    {
        return $this->successResponse(NoteResource::make($this->noteService->getNote($id)));
    }

    /**
     * @throws Throwable
     */
    public function create(Request $request): Response
    {
        $note = $this->noteService->create($this->createNoteRequest->toDto($request));

        return $this->successResponse(data: NoteResource::make($note), status: 201);
    }

    /**
     * @throws Throwable
     */
    public function replace(Request $request, string $id): Response
    {
        $note = $this->noteService->replace($id, $this->updateNoteRequest->toDto($request));

        return $this->successResponse(NoteResource::make($note));
    }

    /**
     * @throws Throwable
     */
    public function delete(string $id): Response
    {
        $this->noteService->delete($id);

        return $this->noContentResponse();
    }
}
