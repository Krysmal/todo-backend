
<?php

require __DIR__ . '/vendor/autoload.php';

try {
    // Połączenie z MongoDB działającym w Dockerze
    $client = new MongoDB\Client('mongodb://127.0.0.1:27017');

    // Wybór bazy danych i kolekcji
    $collection = $client->todo_app->tasks;

    // Pobranie wszystkich zadań
    $tasks = $collection->find();

    echo "Polaczenie z MongoDB udane!\n\n";
    echo "Lista zadan:\n";

    foreach ($tasks as $task) {
        echo "- " . $task['title'] . "\n";
    }

} catch (Throwable $e) {
    echo "Wystapil blad: " . $e->getMessage() . "\n";
}
