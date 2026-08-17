<?php

declare(strict_types=1);

namespace App\Validation\Validators;

use App\Exceptions\System\ResponseValidationException;
use App\Helpers\TagHelper;
use App\Validation\Rules\TagsValidationRule;

final readonly class NoteValidator
{
    private const int TITLE_MAX_LENGTH = 200;
    private const int CONTENT_MAX_LENGTH = 10000;
    private const array ALLOWED_FIELDS = ['title', 'content', 'tags'];

    public function __construct(private TagsValidationRule $tagsValidationRule)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{title: string, content: string, tags: list<string>}
     * @throws ResponseValidationException
     */
    public function validate(array $payload): array
    {
        $errors = $this->validateUnknownFields($payload);
        $errors = array_merge($errors, $this->validateTitle($payload['title'] ?? null));
        $errors = array_merge($errors, $this->validateContent($payload['content'] ?? ''));

        $rawTags = $payload['tags'] ?? [];
        $tagErrors = $this->tagsValidationRule->validate($rawTags);
        $errors = array_merge($errors, $tagErrors);

        $tags = [];

        if ($tagErrors === [] && is_array($rawTags)) {
            /** @var string[] $rawTags */
            $tags = TagHelper::normalizeList($rawTags);

            // Лимит проверяется после дедупликации: десять тегов, из которых
            // половина — регистровые дубли, это пять тегов, а не десять.
            if (count($tags) > TagHelper::MAX_TAGS_PER_NOTE) {
                $errors['tags'][] = 'Не больше ' . TagHelper::MAX_TAGS_PER_NOTE . ' тегов на заметку';
            }
        }

        if ($errors !== []) {
            throw new ResponseValidationException($errors);
        }

        return [
            'title' => trim((string)($payload['title'] ?? '')),
            'content' => (string)($payload['content'] ?? ''),
            'tags' => $tags,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string[]>
     */
    private function validateUnknownFields(array $payload): array
    {
        $errors = [];

        foreach (array_keys($payload) as $field) {
            if (!in_array((string)$field, self::ALLOWED_FIELDS, true)) {
                $errors[(string)$field][] = 'Неизвестное поле';
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string[]>
     */
    private function validateTitle(mixed $title): array
    {
        if (!is_string($title) || trim($title) === '') {
            return ['title' => ['Поле title обязательно и должно быть непустой строкой']];
        }

        if (mb_strlen(trim($title)) > self::TITLE_MAX_LENGTH) {
            return ['title' => ['Поле title длиннее ' . self::TITLE_MAX_LENGTH . ' символов']];
        }

        return [];
    }

    /**
     * @return array<string, string[]>
     */
    private function validateContent(mixed $content): array
    {
        if (!is_string($content)) {
            return ['content' => ['Поле content должно быть строкой']];
        }

        if (mb_strlen($content) > self::CONTENT_MAX_LENGTH) {
            return ['content' => ['Поле content длиннее ' . self::CONTENT_MAX_LENGTH . ' символов']];
        }

        return [];
    }
}
