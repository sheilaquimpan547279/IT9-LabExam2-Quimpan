<?php
declare(strict_types=1);

$databaseHost = getenv('DB_HOST') ?: '127.0.0.1';
$databaseName = getenv('DB_NAME') ?: 'student_enrollment';
$databaseUser = getenv('DB_USER') ?: 'root';
$databasePassword = getenv('DB_PASSWORD') ?: '';

$ports = array_values(array_filter(
    array_unique([
        getenv('DB_PORT') ?: null,
        '3306',
        '3307',
    ]),
    static fn ($port): bool => $port !== null && $port !== ''
));

$lastException = null;
foreach ($ports as $databasePort) {
    try {
        $pdo = new PDO(
            "mysql:host={$databaseHost};port={$databasePort};dbname={$databaseName};charset=utf8mb4",
            $databaseUser,
            $databasePassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        break;
    } catch (PDOException $exception) {
        $lastException = $exception;
    }
}

if (!isset($pdo)) {
    http_response_code(500);
    exit(
        'Unable to connect to MySQL. Start MySQL in XAMPP, import database.sql in phpMyAdmin, '
        . 'and check the database credentials in config.php. '
        . htmlspecialchars($lastException?->getMessage() ?? 'Unknown database error.', ENT_QUOTES, 'UTF-8')
    );
}