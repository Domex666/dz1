<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Helpers\TagHelper;

/**
 * Правило вынесено отдельно, потому что те же теги приходят из тела запроса
 * и из строки запроса при фильтрации — правило должно быть одно.
 */
final readonly class TagsValidationRule
{
    /**
     * @return array<string, string[]> ключ — путь до элемента (tags, tags.1)
     */
    public function validate(mixed $value): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            return ['tags' => ['Поле tags должно быть массивом строк']];
        }

        $errors = [];

        foreach ($value as $index => $tag) {
            if (!is_string($tag)) {
                $errors["tags.$index"][] = 'Тег должен быть строкой';

                continue;
            }

            $normalized = TagHelper::normalize($tag);

            // Пустой после trim тег — ошибка, а не молчаливое выбрасывание элемента:
            // молча съеденный элемент массива это потеря данных клиента.
            if ($normalized === '') {
                $errors["tags.$index"][] = 'Тег не может быть пустым';

                continue;
            }

            if (mb_strlen($normalized) > TagHelper::MAX_TAG_LENGTH) {
                $errors["tags.$index"][] = 'Тег длиннее ' . TagHelper::MAX_TAG_LENGTH . ' символов';
            }
        }

        return $errors;
    }
}
