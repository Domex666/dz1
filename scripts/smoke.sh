#!/usr/bin/env sh
# Ручная проверка живого сервиса: основной сценарий плюс нештатные.
# Требует поднятый сервис (см. README.md) и curl.
#
# Использование:  sh scripts/smoke.sh

set -eu

API="${API:-http://localhost:8080/api/v1}"
STORAGE="${STORAGE:-storage/notes.json}"

section() { printf '\n=== %s ===\n' "$1"; }
call() { curl -s -w '\nHTTP %{http_code}\n' "$@"; }

section "Сброс хранилища в пустое состояние"
mkdir -p "$(dirname "$STORAGE")"
printf '[]\n' > "$STORAGE"
echo "$STORAGE := []"

section "1. Список на пустом хранилище"
call "$API/notes"

section "2. Создание заметки: нормализация тегов"
CREATED=$(curl -s -X POST "$API/notes" -H 'Content-Type: application/json' \
  -d '{"title":"Созвон с командой","content":"Обсудить сроки","tags":["  Работа ","работа","Work"]}')
echo "$CREATED"
ID=$(printf '%s' "$CREATED" | sed -n 's/.*"id":"\([^"]*\)".*/\1/p')
echo "id = $ID"

section "3. Чтение созданной заметки"
call "$API/notes/$ID"

section "4. Ошибка: пустой тег"
call -X POST "$API/notes" -H 'Content-Type: application/json' -d '{"title":"Заметка","tags":["   "]}'

section "5. Ошибка: неизвестное поле"
call -X POST "$API/notes" -H 'Content-Type: application/json' -d '{"title":"Заметка","colour":"red"}'

section "6. Ошибка: битый JSON"
call -X POST "$API/notes" -H 'Content-Type: application/json' -d '{"title": '

section "7. PUT — полная замена"
call -X PUT "$API/notes/$ID" -H 'Content-Type: application/json' \
  -d '{"title":"Созвон перенесён","content":"Новый текст","tags":["Работа","Срочное"]}'

section "8. Повторный PUT тем же телом — идемпотентность (updatedAt не меняется)"
call -X PUT "$API/notes/$ID" -H 'Content-Type: application/json' \
  -d '{"title":"Созвон перенесён","content":"Новый текст","tags":["Работа","Срочное"]}'

section "9. PUT по несуществующему id"
call -X PUT "$API/notes/00000000-0000-4000-8000-000000000000" \
  -H 'Content-Type: application/json' -d '{"title":"Нет такой"}'

section "10. Метод не поддержан (PATCH)"
call -X PATCH "$API/notes/$ID" -H 'Content-Type: application/json' -d '{"title":"Нет"}'

section "11. Неизвестный маршрут"
call "$API/unknown"

section "12. Фильтр по тегам, mode=all по умолчанию"
curl -s --get "$API/notes" --data-urlencode "tags=работа,срочное" -w '\nHTTP %{http_code}\n'

section "13. Фильтр mode=any"
curl -s --get "$API/notes" --data-urlencode "tags=дом,работа" --data-urlencode "mode=any" -w '\nHTTP %{http_code}\n'

section "14. Ошибка: неверный mode"
curl -s --get "$API/notes" --data-urlencode "mode=wrong" -w '\nHTTP %{http_code}\n'

section "15. Аналитика: топ тегов"
call "$API/tags/top"

section "16. Ошибка: limit=0"
call "$API/tags/top?limit=0"

section "17. Удаление заметки"
call -X DELETE "$API/notes/$ID"

section "18. Повторное удаление — 404"
call -X DELETE "$API/notes/$ID"

section "19. Состояние хранилища: пустой файл (0 байт)"
: > "$STORAGE"
call "$API/notes"

section "20. Состояние хранилища: файла нет"
rm -f "$STORAGE"
call "$API/notes"

section "21. Состояние хранилища: файл испорчен"
printf '{ это не JSON' > "$STORAGE"
call "$API/notes"

section "22. Испорченный файл не затирается записью"
call -X POST "$API/notes" -H 'Content-Type: application/json' -d '{"title":"Новая"}'
echo "содержимое файла после попытки записи:"
cat "$STORAGE"
echo

section "Восстановление пустого хранилища"
printf '[]\n' > "$STORAGE"
echo "готово"
