<?php

// Простое API для заметок с тегами. Хранилище — JSON-файл.

header('Content-Type: application/json');

const STORAGE = __DIR__ . '/notes.json';

function loadNotes(): array
{
    if (!file_exists(STORAGE)) {
        return [];
    }

    return json_decode(file_get_contents(STORAGE), true) ?: [];
}

function saveNotes(array $notes): void
{
    file_put_contents(STORAGE, json_encode($notes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body = json_decode(file_get_contents('php://input'), true) ?? [];

$notes = loadNotes();

// GET /notes — список, можно фильтровать по тегам
if ($method === 'GET' && $path === '/notes') {
    $result = $notes;

    if (!empty($_GET['tags'])) {
        $filter = explode(',', $_GET['tags']);
        $result = array_filter($notes, function ($note) use ($filter) {
            return count(array_intersect($filter, $note['tags'])) > 0;
        });
    }

    echo json_encode(array_values($result), JSON_UNESCAPED_UNICODE);
    exit;
}

// GET /notes/{id}
if ($method === 'GET' && preg_match('#^/notes/(\d+)$#', $path, $m)) {
    foreach ($notes as $note) {
        if ($note['id'] == $m[1]) {
            echo json_encode($note, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

// POST /notes
if ($method === 'POST' && $path === '/notes') {
    if (empty($body['title'])) {
        http_response_code(400);
        echo json_encode(['error' => 'title is required']);
        exit;
    }

    $note = [
        'id' => count($notes) + 1,
        'title' => $body['title'],
        'content' => $body['content'] ?? '',
        'tags' => $body['tags'] ?? [],
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $notes[] = $note;
    saveNotes($notes);

    http_response_code(201);
    echo json_encode($note, JSON_UNESCAPED_UNICODE);
    exit;
}

// PUT /notes/{id}
if ($method === 'PUT' && preg_match('#^/notes/(\d+)$#', $path, $m)) {
    foreach ($notes as $i => $note) {
        if ($note['id'] == $m[1]) {
            $notes[$i]['title'] = $body['title'] ?? $note['title'];
            $notes[$i]['content'] = $body['content'] ?? $note['content'];
            $notes[$i]['tags'] = $body['tags'] ?? $note['tags'];
            $notes[$i]['updated_at'] = date('Y-m-d H:i:s');

            saveNotes($notes);
            echo json_encode($notes[$i], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

// DELETE /notes/{id}
if ($method === 'DELETE' && preg_match('#^/notes/(\d+)$#', $path, $m)) {
    $notes = array_values(array_filter($notes, fn($n) => $n['id'] != $m[1]));
    saveNotes($notes);

    echo json_encode(['ok' => true]);
    exit;
}

// GET /tags/top
if ($method === 'GET' && $path === '/tags/top') {
    $counts = [];

    foreach ($notes as $note) {
        foreach ($note['tags'] as $tag) {
            $counts[$tag] = ($counts[$tag] ?? 0) + 1;
        }
    }

    arsort($counts);
    $top = array_slice($counts, 0, 10, true);

    echo json_encode($top, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Unknown route']);
