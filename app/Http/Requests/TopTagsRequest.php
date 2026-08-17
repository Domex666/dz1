<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Exceptions\System\ResponseValidationException;
use App\Support\Http\Request;

final readonly class TopTagsRequest
{
    public const int DEFAULT_LIMIT = 10;
    private const int MIN_LIMIT = 1;
    private const int MAX_LIMIT = 100;

    /**
     * @throws ResponseValidationException
     */
    public function toLimit(Request $request): int
    {
        if (!$request->hasQuery('limit')) {
            return self::DEFAULT_LIMIT;
        }

        $raw = $request->queryString('limit');

        // Проверка регуляркой, а не (int): (int)"abc" даёт 0 и молча превращает
        // мусор в валидное на вид значение.
        if (preg_match('/^\d+$/', $raw) !== 1) {
            throw new ResponseValidationException(['limit' => ['Ожидается целое число']]);
        }

        $limit = (int)$raw;

        if ($limit < self::MIN_LIMIT || $limit > self::MAX_LIMIT) {
            throw new ResponseValidationException([
                'limit' => ['Допустимый диапазон: ' . self::MIN_LIMIT . '..' . self::MAX_LIMIT],
            ]);
        }

        return $limit;
    }
}
