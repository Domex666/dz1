<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\Create\CreateNoteDto;
use App\DTO\Helper\NoteFilterDto;
use App\DTO\RepositoryMapModel\NoteMapDto;
use App\DTO\Update\UpdateNoteDto;
use App\Enums\ErrorCodeEnum;
use App\Exceptions\System\NotFoundException;
use App\Exceptions\System\StorageException;
use App\Interfaces\Repositories\NoteRepositoryInterface;
use App\Support\Helpers\File\JsonStorageHelper;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * Единственный слой, который знает, где лежат данные.
 * Замена файла на базу должна быть правкой только этого класса.
 */
final readonly class NoteJsonRepository implements NoteRepositoryInterface
{
    private const array REQUIRED_COLUMNS = ['id', 'title', 'content', 'tags', 'created_at', 'updated_at'];

    public function __construct(private JsonStorageHelper $storage)
    {
    }

    /**
     * @return list<NoteMapDto>
     */
    public function getNotes(NoteFilterDto $filter): array
    {
        $notes = $this->getAllNotes();

        $filtered = array_filter(
            $notes,
            static fn (NoteMapDto $note): bool => $filter->mode->matches($note->tags, $filter->tags)
        );

        return array_values($filtered);
    }

    /**
     * @return list<NoteMapDto>
     */
    public function getAllNotes(): array
    {
        $notes = array_map(fn (array $row): NoteMapDto => $this->toDto($row), $this->readRows());

        // Тай-брейк задан явно: без него порядок зависит от порядка строк в файле
        // и любой тест на список становится плавающим.
        usort(
            $notes,
            static fn (NoteMapDto $left, NoteMapDto $right): int
                => [$right->createdAt, $left->id] <=> [$left->createdAt, $right->id]
        );

        return $notes;
    }

    public function findNoteById(string $id): ?NoteMapDto
    {
        foreach ($this->readRows() as $row) {
            if (($row['id'] ?? null) === $id) {
                return $this->toDto($row);
            }
        }

        return null;
    }

    /**
     * Читает строки и проверяет КАЖДУЮ до того, как вызывающий код что-либо запишет.
     *
     * Без этого файл, испорченный на уровне отдельной записи, вёл себя так:
     * GET честно отдавал 500 STORAGE_CORRUPTED, а POST и DELETE проходили
     * и затирали данные. То есть ровно «потеря данных под видом устойчивости»,
     * которую SPEC.md называет отвергнутой альтернативой.
     *
     * @return list<array<string, mixed>>
     * @throws StorageException
     */
    private function readRows(): array
    {
        $rows = $this->storage->read();

        foreach ($rows as $row) {
            $this->toDto($row);
        }

        return $rows;
    }

    /**
     * @throws NotFoundException
     */
    public function getNoteById(string $id): NoteMapDto
    {
        $note = $this->findNoteById($id);

        if ($note === null) {
            throw new NotFoundException();
        }

        return $note;
    }

    /**
     * @throws StorageException
     */
    public function createNote(CreateNoteDto $note): NoteMapDto
    {
        $rows = $this->readRows();
        $now = $this->now();

        $row = [
            'id' => $this->generateId(),
            'title' => $note->title,
            'content' => $note->content,
            'tags' => $note->tags,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $rows[] = $row;
        $this->storage->write($rows);

        return $this->toDto($row);
    }

    /**
     * @throws NotFoundException
     * @throws StorageException
     */
    public function replaceNote(string $id, UpdateNoteDto $note): NoteMapDto
    {
        $rows = $this->readRows();
        $index = $this->indexOf($rows, $id);

        $rows[$index]['title'] = $note->title;
        $rows[$index]['content'] = $note->content;
        $rows[$index]['tags'] = $note->tags;
        $rows[$index]['updated_at'] = $this->now();

        $this->storage->write($rows);

        return $this->toDto($rows[$index]);
    }

    /**
     * @throws NotFoundException
     * @throws StorageException
     */
    public function deleteNote(string $id): void
    {
        $rows = $this->readRows();
        $index = $this->indexOf($rows, $id);

        unset($rows[$index]);

        $this->storage->write(array_values($rows));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @throws NotFoundException
     */
    private function indexOf(array $rows, string $id): int
    {
        foreach ($rows as $index => $row) {
            if (($row['id'] ?? null) === $id) {
                return $index;
            }
        }

        throw new NotFoundException();
    }

    /**
     * snake_case файла превращается в camelCase DTO именно здесь и больше нигде.
     *
     * Форма строки проверяется, а не достраивается значениями по умолчанию:
     * раньше строка {} превращалась в заметку с пустыми полями, а "tags":"xxx"
     * протаскивал ненормализованный тег и в список, и в аналитику.
     * Знание о форме строки принадлежит репозиторию, поэтому проверка здесь,
     * а не в инфраструктурном JsonStorageHelper.
     *
     * @param array<string, mixed> $row
     * @throws StorageException
     */
    private function toDto(array $row): NoteMapDto
    {
        foreach (self::REQUIRED_COLUMNS as $column) {
            if (!array_key_exists($column, $row)) {
                throw new StorageException(ErrorCodeEnum::STORAGE_CORRUPTED);
            }
        }

        // Лишние ключи — тоже порча. Иначе сервис хранил и переписывал бы данные,
        // которые никогда не валидировал и не показывает, а API при этом
        // отвергает лишнее поле в теле запроса кодом 422. Строгость должна быть одна.
        if (count($row) !== count(self::REQUIRED_COLUMNS)) {
            throw new StorageException(ErrorCodeEnum::STORAGE_CORRUPTED);
        }

        if (!is_string($row['id']) || !is_string($row['title']) || !is_string($row['content'])) {
            throw new StorageException(ErrorCodeEnum::STORAGE_CORRUPTED);
        }

        if (!is_string($row['created_at']) || !is_string($row['updated_at'])) {
            throw new StorageException(ErrorCodeEnum::STORAGE_CORRUPTED);
        }

        // Проверяется не только тип, но и формат: строка «не дата» раньше
        // доезжала до клиента как значение createdAt и ломала сортировку,
        // которая сравнивает эти строки.
        $this->assertTimestamp($row['created_at']);
        $this->assertTimestamp($row['updated_at']);

        if (!is_array($row['tags']) || !array_is_list($row['tags'])) {
            throw new StorageException(ErrorCodeEnum::STORAGE_CORRUPTED);
        }

        foreach ($row['tags'] as $tag) {
            if (!is_string($tag)) {
                throw new StorageException(ErrorCodeEnum::STORAGE_CORRUPTED);
            }
        }

        return new NoteMapDto(
            id: $row['id'],
            title: $row['title'],
            content: $row['content'],
            tags: $row['tags'],
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at'],
        );
    }

    /**
     * @throws StorageException
     */
    private function assertTimestamp(string $value): void
    {
        $parsed = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);

        // Сравнение с обратным форматированием отсекает три класса мусора,
        // которые createFromFormat пропускает: календарно несуществующие даты
        // (2026-02-31 «переезжает» на 3 марта), смещение не UTC (+03:00)
        // и суффикс Z вместо +00:00. Последние два особенно вредны: сортировка
        // сравнивает эти строки лексикографически, и эквивалентные по времени
        // метки встали бы в разном порядке — ровно тот плавающий порядок,
        // против которого введён тай-брейк.
        if ($parsed === false || $parsed->format(DateTimeInterface::ATOM) !== $value) {
            throw new StorageException(ErrorCodeEnum::STORAGE_CORRUPTED);
        }

        if ($parsed->getOffset() !== 0) {
            throw new StorageException(ErrorCodeEnum::STORAGE_CORRUPTED);
        }
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
    }

    /**
     * UUID v4. Автоинкремент отвергнут: он течёт из хранилища наружу
     * и мешает заменить файл на что-то другое.
     *
     * random_bytes оборачивается здесь же: RandomException — не App-исключение,
     * и без обёртки оно вышло бы наружу мимо объявленного контракта репозитория.
     *
     * @throws StorageException
     */
    private function generateId(): string
    {
        try {
            $bytes = random_bytes(16);
        } catch (Throwable $exception) {
            throw new StorageException(ErrorCodeEnum::STORAGE_FAILURE, $exception);
        }

        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
