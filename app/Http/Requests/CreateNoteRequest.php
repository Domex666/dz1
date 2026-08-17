<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\Create\CreateNoteDto;
use App\Exceptions\System\BadRequestException;
use App\Exceptions\System\ResponseValidationException;
use App\Support\Http\Request;
use App\Validation\Validators\NoteValidator;

final readonly class CreateNoteRequest
{
    public function __construct(private NoteValidator $noteValidator)
    {
    }

    /**
     * @throws BadRequestException тело не является корректным JSON-объектом
     * @throws ResponseValidationException
     */
    public function toDto(Request $request): CreateNoteDto
    {
        $validated = $this->noteValidator->validate($request->json());

        return new CreateNoteDto(
            title: $validated['title'],
            content: $validated['content'],
            tags: $validated['tags'],
        );
    }
}
