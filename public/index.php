
<?php


require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: http://localhost:4200');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Odpowiadanie na żądania wstępne przeglądarki
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Funkcja zwracająca odpowiedź JSON
function jsonResponse(int $status, mixed $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Zamiana dokumentu MongoDB na zwykłe dane JSON
function formatTask($task): array
{
    return [
        'id' => (string) $task['_id'],
        'title' => (string) $task['title'],
        'completed' => (bool) ($task['completed'] ?? false),
        'createdAt' => isset($task['createdAt'])
            ? $task['createdAt']->toDateTime()->format(DATE_ATOM)
            : null
    ];
}

try {
    // Połączenie z MongoDB
    $client = new MongoDB\Client('mongodb://127.0.0.1:27017');
    $collection = $client->todo_app->tasks;

    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // POBIERANIE WSZYSTKICH ZADAŃ
    if ($path === '/api/tasks' && $method === 'GET') {

        $tasks = $collection->find(
            [],
            ['sort' => ['createdAt' => -1]]
        );

        $result = [];

        foreach ($tasks as $task) {
            $result[] = formatTask($task);
        }

        jsonResponse(200, $result);
    }

    // DODAWANIE NOWEGO ZADANIA
    if ($path === '/api/tasks' && $method === 'POST') {

        $data = json_decode(file_get_contents('php://input'), true);

        $title = trim($data['title'] ?? '');

        if ($title === '') {
            jsonResponse(400, ['error' => 'Tytul zadania jest wymagany']);
        }

        $insertResult = $collection->insertOne([
            'title' => $title,
            'completed' => false,
            'createdAt' => new MongoDB\BSON\UTCDateTime()
        ]);

        $task = $collection->findOne([
            '_id' => $insertResult->getInsertedId()
        ]);

        jsonResponse(201, formatTask($task));
    }

    // OPERACJE NA KONKRETNYM ZADANIU
    if (preg_match('#^/api/tasks/([a-fA-F0-9]{24})$#', $path, $matches)) {

        $id = new MongoDB\BSON\ObjectId($matches[1]);

        // EDYCJA ZADANIA
        if ($method === 'PATCH') {

            $data = json_decode(file_get_contents('php://input'), true);

            if (!is_array($data)) {
                jsonResponse(400, ['error' => 'Nieprawidlowe dane']);
            }

            $update = [];

            if (isset($data['title'])) {
                $title = trim($data['title']);

                if ($title === '') {
                    jsonResponse(400, ['error' => 'Tytul nie moze byc pusty']);
                }

                $update['title'] = $title;
            }

            if (array_key_exists('completed', $data)) {
                $update['completed'] = (bool) $data['completed'];
            }

            if (empty($update)) {
                jsonResponse(400, ['error' => 'Brak danych do zmiany']);
            }

            $collection->updateOne(
                ['_id' => $id],
                ['$set' => $update]
            );

            $task = $collection->findOne(['_id' => $id]);

            if ($task === null) {
                jsonResponse(404, ['error' => 'Nie znaleziono zadania']);
            }

            jsonResponse(200, formatTask($task));
        }

        // USUWANIE ZADANIA
        if ($method === 'DELETE') {

            $result = $collection->deleteOne(['_id' => $id]);

            if ($result->getDeletedCount() === 0) {
                jsonResponse(404, ['error' => 'Nie znaleziono zadania']);
            }

            jsonResponse(200, ['message' => 'Zadanie usuniete']);
        }
    }

    // Nie znaleziono odpowiedniej trasy
    jsonResponse(404, ['error' => 'Nie znaleziono endpointu']);

} catch (Throwable $e) {

    jsonResponse(500, [
        'error' => 'Blad serwera',
        'details' => $e->getMessage()
    ]);
}
