<?php

// Database configuration is read from environment variables, with
// local development defaults. See .env.example.
$host   = getenv('DB_HOST')     ?: 'localhost';
$dbname = getenv('DB_NAME')     ?: 'sistema_ferias';
$dbuser = getenv('DB_USER')     ?: 'root';
$dbpass = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbuser,
        $dbpass
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Avoid leaking internal database details to the client.
    error_log('Database connection error: ' . $e->getMessage());
    http_response_code(500);
    die('Erro na ligação à base de dados.');
}
