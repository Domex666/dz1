<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\Helper\NoteFilterDto;
use App\Enums\TagFilterModeEnum;
use App\Exceptions\System\ResponseValidationException;
use App\Helpers\TagHelper;
use App\Support\Http\Request;

final readonly class ListNotesRequest
{
    /**
     * @throws ResponseValidationException
     */
    public function toDto(Request $request): NoteFilterDto
    {
        $errors = [];

        $rawMode = $request->queryString('mode', TagFilterModeEnum::default()->value);
        $mode = TagFilterModeEnum::tryFrom($rawMode);

        if ($mode === null) {
            $errors['mode'][] = 'Допустимые значения: all, any';
        }

        [$tags, $tagErrors] = $this->parseTags($request->queryString('tags'));
        $errors = array_merge($errors, $tagErrors);

        if ($errors !== []) {
            throw new ResponseValidationException($errors);
        }

        return new NoteFilterDto(tags: $tags, mode: $mode ?? TagFilterModeEnum::default());
    }

    /**
     * Теги фильтра нормализуются теми же правилами, что и при записи,
     * поэтому ?tags=работа находит заметку с тегом РАБОТА.
     *
     * @return array{0: list<string>, 1: array<string, string[]>}
     */
    private function parseTags(string $raw): array
    {
        if (trim($raw) === '') {
            return [[], []];
        }

        $segments = explode(',', $raw);
        $errors = [];

        foreach ($segments as $index => $segment) {
            if (TagHelper::normalize($segment) === '') {
                $errors["tags.$index"][] = 'Тег не может быть пустым';
            }
        }

        if ($errors !== []) {
            return [[], $errors];
        }

        return [TagHelper::normalizeList($segments), []];
    }
}
