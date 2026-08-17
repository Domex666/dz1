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

        foreach ($tagErrors as $field => $messages) {
            $errors[$field] = $messages;
        }

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
        // Отсутствие фильтра — это ровно пустая строка (?tags= или параметра нет).
        // Пробельный сегмент (?tags=%20) раньше тоже проглатывался как «фильтра нет»,
        // хотя ?tags=%20,%20 давал 422 — и это то самое молчаливое решение
        // за клиента, которое запрещено для тегов в теле запроса.
        if ($raw === '') {
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
