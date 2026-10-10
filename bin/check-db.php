<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $version = App\Database::connection()->query('SELECT version()')->fetchColumn();
    echo "Connexion OK : $version\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Connexion impossible : ' . $e->getMessage() . "\n");
    exit(1);
}
