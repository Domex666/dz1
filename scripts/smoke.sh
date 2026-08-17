#!/usr/bin/env sh
# Ручная проверка живого сервиса: основной сценарий плюс нештатные.
#
# Скрипт именно ПРОВЕРЯЕТ, а не печатает: каждый сценарий сверяется с ожидаемым
# кодом ответа и типом содержимого, а в конце возвращается ненулевой код выхода,
# если хоть что-то разошлось. Раньше он только выводил ответы, и три мутации —
# «убрать http_response_code», «отдавать text/html», «путь всегда /» — проходили
# через него незамеченными.
#
# Требуется ПОДНЯТЫЙ сервис (см. README.md) и curl.
#
# Использование:
#   sh scripts/smoke.sh
#   API=http://localhost:9000/api/v1 STORAGE=/tmp/notes.json sh scripts/smoke.sh

set -u

API="${API:-http://localhost:8080/api/v1}"
STORAGE="${STORAGE:-storage/notes.json}"

PASSED=0
FAILED=0
BODY_FILE="$(mktemp)"

# Хранилище всегда возвращается в пригодное состояние, даже если скрипт прервали.
# Без этого сценарий «файла нет» оставлял рабочий файл удалённым.
cleanup() {
    mkdir -p "$(dirname "$STORAGE")" 2>/dev/null || true
    printf '[]\n' > "$STORAGE" 2>/dev/null || true
    rm -f "$BODY_FILE"
}
trap cleanup EXIT INT TERM

section() { printf '\n=== %s ===\n' "$1"; }

# check <описание> <ожидаемый код> <curl-аргументы...>
check() {
    description="$1"
    expected="$2"
    shift 2

    result=$(curl -s -o "$BODY_FILE" -w '%{http_code} %{content_type}' "$@")
    code=$(printf '%s' "$result" | cut -d' ' -f1)
    ctype=$(printf '%s' "$result" | cut -d' ' -f2-)

    printf '%s\n' "$(cat "$BODY_FILE")"

    if [ "$code" != "$expected" ]; then
        printf 'FAIL  %s: ожидался HTTP %s, получен %s\n' "$description" "$expected" "$code"
        FAILED=$((FAILED + 1))
        return
    fi

    # 204 идёт без тела, но заголовок типа обязан быть JSON-овым.
    case "$ctype" in
        application/json*) ;;
        *)
            printf 'FAIL  %s: ожидался application/json, получен "%s"\n' "$description" "$ctype"
            FAILED=$((FAILED + 1))
            return
            ;;
    esac

    printf 'ok    %s (HTTP %s)\n' "$description" "$code"
    PASSED=$((PASSED + 1))
}

# contains <описание> <подстрока> — проверяет тело последнего ответа
contains() {
    if grep -q -- "$2" "$BODY_FILE"; then
        printf 'ok    %s\n' "$1"
        PASSED=$((PASSED + 1))
    else
        printf 'FAIL  %s: в теле нет "%s"\n' "$1" "$2"
        FAILED=$((FAILED + 1))
    fi
}

section "Сброс хранилища в пустое состояние"
mkdir -p "$(dirname "$STORAGE")"
printf '[]\n' > "$STORAGE"
echo "$STORAGE := []"

section "1. Список на пустом хранилище"
check "пустой список" 200 "$API/notes"
contains "items пуст" '"items":\[\]'

section "2. Создание заметки: нормализация тегов"
check "создание" 201 -X POST "$API/notes" -H 'Content-Type: application/json' \
  -d '{"title":"Созвон с командой","content":"Обсудить сроки","tags":["  Работа ","работа","Work"]}'
contains "теги нормализованы" '"tags":\["РАБОТА","WORK"\]'
ID=$(sed -n 's/.*"id":"\([^"]*\)".*/\1/p' "$BODY_FILE")
echo "id = $ID"

section "3. Чтение созданной заметки"
check "чтение по id" 200 "$API/notes/$ID"

section "4. Ошибка: пустой тег"
check "пустой тег" 422 -X POST "$API/notes" -H 'Content-Type: application/json' -d '{"title":"Заметка","tags":["   "]}'
contains "указан индекс тега" '"tags.0"'

section "5. Ошибка: неизвестное поле"
check "лишнее поле" 422 -X POST "$API/notes" -H 'Content-Type: application/json' -d '{"title":"Заметка","colour":"red"}'
contains "названо лишнее поле" '"colour"'

section "6. Ошибка: битый JSON"
check "битый JSON" 400 -X POST "$API/notes" -H 'Content-Type: application/json' -d '{"title": '

section "7. Пустой JSON-объект — ошибка валидации, не разбора"
check "тело {}" 422 -X POST "$API/notes" -H 'Content-Type: application/json' -d '{}'

section "8. PUT — полная замена"
check "замена" 200 -X PUT "$API/notes/$ID" -H 'Content-Type: application/json' \
  -d '{"title":"Созвон перенесён","content":"Новый текст","tags":["Работа","Срочное"]}'
UPDATED_FIRST=$(sed -n 's/.*"updatedAt":"\([^"]*\)".*/\1/p' "$BODY_FILE")

section "9. Повторный PUT тем же телом — идемпотентность"
check "повторная замена" 200 -X PUT "$API/notes/$ID" -H 'Content-Type: application/json' \
  -d '{"title":"Созвон перенесён","content":"Новый текст","tags":["Работа","Срочное"]}'
UPDATED_SECOND=$(sed -n 's/.*"updatedAt":"\([^"]*\)".*/\1/p' "$BODY_FILE")
if [ "$UPDATED_FIRST" = "$UPDATED_SECOND" ]; then
    printf 'ok    updatedAt не изменился (%s)\n' "$UPDATED_FIRST"
    PASSED=$((PASSED + 1))
else
    printf 'FAIL  updatedAt изменился: %s -> %s\n' "$UPDATED_FIRST" "$UPDATED_SECOND"
    FAILED=$((FAILED + 1))
fi

section "10. PUT по несуществующему id"
check "PUT в никуда" 404 -X PUT "$API/notes/00000000-0000-4000-8000-000000000000" \
  -H 'Content-Type: application/json' -d '{"title":"Нет такой"}'

section "11. Метод не поддержан"
check "PATCH" 405 -X PATCH "$API/notes/$ID" -H 'Content-Type: application/json' -d '{"title":"Нет"}'

section "12. Неизвестный маршрут"
check "неизвестный путь" 404 "$API/unknown"

section "13. Фильтр по тегам, mode=all по умолчанию"
check "фильтр all" 200 --get "$API/notes" --data-urlencode "tags=работа,срочное"

section "14. Фильтр mode=any"
check "фильтр any" 200 --get "$API/notes" --data-urlencode "tags=дом,работа" --data-urlencode "mode=any"

section "15. Ошибка: неверный mode"
check "mode=wrong" 422 --get "$API/notes" --data-urlencode "mode=wrong"

section "16. Ошибка: массив вместо строки в параметре"
check "mode[]=any" 422 --get "$API/notes" --data-urlencode "mode[]=any"

section "17. Аналитика: топ тегов"
check "топ тегов" 200 "$API/tags/top"

section "18. Ошибка: limit=0"
check "limit=0" 422 "$API/tags/top?limit=0"

section "19. Удаление заметки"
check "удаление" 204 -X DELETE "$API/notes/$ID"

section "20. Повторное удаление"
check "повторное удаление" 404 -X DELETE "$API/notes/$ID"

section "21. Состояние хранилища: пустой файл (0 байт)"
: > "$STORAGE"
check "пустой файл" 200 "$API/notes"

section "22. Состояние хранилища: файла нет"
rm -f "$STORAGE"
check "файла нет" 200 "$API/notes"
contains "список пуст" '"items":\[\]'

section "23. Состояние хранилища: файл испорчен"
printf '{ это не JSON' > "$STORAGE"
check "испорченный файл" 500 "$API/notes"
contains "код STORAGE_CORRUPTED" 'STORAGE_CORRUPTED'

section "24. Испорченный файл не затирается записью"
check "запись отклонена" 500 -X POST "$API/notes" -H 'Content-Type: application/json' -d '{"title":"Новая"}'
if [ "$(cat "$STORAGE")" = "{ это не JSON" ]; then
    printf 'ok    содержимое файла не изменилось\n'
    PASSED=$((PASSED + 1))
else
    printf 'FAIL  испорченный файл был перезаписан\n'
    FAILED=$((FAILED + 1))
fi

section "25. Порча на уровне записи тоже блокирует запись"
printf '%s' '[{"id":"11111111-1111-4111-8111-111111111111","title":"НЕ ТРОГАТЬ","tags":[],"created_at":"2026-01-01T00:00:00+00:00","updated_at":"2026-01-01T00:00:00+00:00"}]' > "$STORAGE"
check "DELETE по испорченной записи" 500 -X DELETE "$API/notes/11111111-1111-4111-8111-111111111111"
if grep -q 'НЕ ТРОГАТЬ' "$STORAGE"; then
    printf 'ok    запись на месте\n'
    PASSED=$((PASSED + 1))
else
    printf 'FAIL  DELETE уничтожил данные в испорченном файле\n'
    FAILED=$((FAILED + 1))
fi

section "ИТОГ"
printf 'пройдено: %s, провалено: %s\n' "$PASSED" "$FAILED"

if [ "$FAILED" -ne 0 ]; then
    exit 1
fi
