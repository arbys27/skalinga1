<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../masterlist_common.php';

function dashboardTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables
         WHERE table_schema = current_schema() AND table_name = ?
         LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function dashboardCount(PDO $pdo, string $sql, array $params, string $metric): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $exception) {
        error_log('[Dashboard] ' . $metric . ' query failed: ' . $exception->getMessage());
        return 0;
    }
}

function dashboardRows(PDO $pdo, string $sql, array $params, string $metric): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        error_log('[Dashboard] ' . $metric . ' query failed: ' . $exception->getMessage());
        return [];
    }
}
