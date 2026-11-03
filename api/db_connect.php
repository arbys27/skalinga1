<?php

declare(strict_types=1);

try {
    require_once __DIR__ . '/../includes/config.php';
    $config = skalingaConfig();
    $dsn = sprintf(
        'pgsql:host=%s;port=5432;dbname=postgres;sslmode=require',
        $config['DB_HOST']
    );
    $pdo = new PDO(
        $dsn,
        $config['DB_USER'],
        $config['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10,
        ]
    );
    $pdo->exec("SET TIME ZONE 'Asia/Manila'");
} catch (Throwable $exception) {
    error_log('[SKalinga] PostgreSQL connection/configuration failed: ' . $exception->getMessage());
    if (PHP_SAPI !== 'cli') {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    } else {
        fwrite(STDERR, "SKalinga database connection failed. Check DB environment variables (or includes/config.local.php locally) and PHP pdo_pgsql.\n");
    }
    exit(1);
}

$conn = $pdo;

function postgresBoolean(mixed $value): bool
{
    return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
}
