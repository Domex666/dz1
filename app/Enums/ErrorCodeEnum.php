<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Машиночитаемый код ошибки. Поведение живёт в самом енаме,
 * чтобы соответствие «код → статус → текст» было в одном месте,
 * а не разъезжалось по классам исключений.
 */
enum ErrorCodeEnum: string
{
    case BAD_REQUEST = 'BAD_REQUEST';
    case ROUTE_NOT_FOUND = 'ROUTE_NOT_FOUND';
    case NOTE_NOT_FOUND = 'NOTE_NOT_FOUND';
    case METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';
    case VALIDATION_ERROR = 'VALIDATION_ERROR';
    case STORAGE_CORRUPTED = 'STORAGE_CORRUPTED';
    case STORAGE_FAILURE = 'STORAGE_FAILURE';

    public function getStatusCode(): ExceptionStatusCodeEnum
    {
        return match ($this) {
            self::BAD_REQUEST => ExceptionStatusCodeEnum::BAD_REQUEST,
            self::ROUTE_NOT_FOUND, self::NOTE_NOT_FOUND => ExceptionStatusCodeEnum::NOT_FOUND,
            self::METHOD_NOT_ALLOWED => ExceptionStatusCodeEnum::METHOD_NOT_ALLOWED,
            self::VALIDATION_ERROR => ExceptionStatusCodeEnum::UNPROCESSABLE_ENTITY,
            self::STORAGE_CORRUPTED, self::STORAGE_FAILURE => ExceptionStatusCodeEnum::INTERNAL_ERROR,
        };
    }

    public function getMessage(): string
    {
        return match ($this) {
            self::BAD_REQUEST => 'Тело запроса не является корректным JSON',
            self::ROUTE_NOT_FOUND => 'Запрошенный ресурс не найден',
            self::NOTE_NOT_FOUND => 'Заметка не найдена',
            self::METHOD_NOT_ALLOWED => 'Метод не поддерживается для этого пути',
            self::VALIDATION_ERROR => 'Переданные данные не прошли проверку',
            self::STORAGE_CORRUPTED => 'Файл хранилища повреждён',
            self::STORAGE_FAILURE => 'Не удалось обратиться к хранилищу',
        };
    }
}
