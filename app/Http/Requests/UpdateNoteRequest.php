<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\Update\UpdateNoteDto;
use App\Exceptions\System\BadRequestException;
use App\Exceptions\System\ResponseValidationException;
use App\Support\Http\Request;
use App\Validation\Validators\NoteValidator;

final readonly class UpdateNoteRequest
{
    public function __construct(private NoteValidator $noteValidator)
    {
    }

    /**
     * PUT — полная замена, поэтому набор правил тот же, что при создании:
     * непереданные необязательные поля сбрасываются в значения по умолчанию.
     *
     * @throws BadRequestException
     * @throws ResponseValidationException
     */
    public function toDto(Request $request): UpdateNoteDto
    {
        $validated = $this->noteValidator->validate($request->json());

        return new UpdateNoteDto(
            title: $validated['title'],
            content: $validated['content'],
            tags: $validated['tags'],
        );
    }
}
