<?php
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

requireAdminApi();

$months = [];
$monthKeys = [];
$cursor = new DateTimeImmutable('first day of this month');
for ($offset = 11; $offset >= 0; $offset--) {
    $month = $cursor->modify("-{$offset} months");
    $monthKeys[] = $month->format('Y-m');
    $months[] = $month->format('M Y');
}
$rangeStart = $monthKeys[0] . '-01';

$monthlyCounts = static function (string $table, string $dateColumn, string $metric) use ($pdo, $monthKeys, $rangeStart): array {
    $values = array_fill_keys($monthKeys, 0);
    try {
        if (!preg_match('/^[a-z_]+$/', $table) || !preg_match('/^[a-z_]+$/', $dateColumn)) {
            throw new InvalidArgumentException('Invalid dashboard table or date column.');
        }
        if (!dashboardTableExists($pdo, $table)) {
            return ['labels' => array_map(
                static fn(string $key): string => (new DateTimeImmutable($key . '-01'))->format('M Y'),
                $monthKeys
            ), 'values' => array_values($values)];
        }
        $sql = "SELECT to_char({$dateColumn}, 'YYYY-MM') AS month_key, COUNT(*) AS total
                FROM {$table}
                WHERE {$dateColumn} >= ?
                  AND {$dateColumn} < CURRENT_DATE + INTERVAL '1 day'
                GROUP BY month_key";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rangeStart]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (array_key_exists($row['month_key'], $values)) {
                $values[$row['month_key']] = (int)$row['total'];
            }
        }
    } catch (Throwable $exception) {
        error_log('[Dashboard Charts] ' . $metric . ' query failed: ' . $exception->getMessage());
    }
    return ['labels' => $months, 'values' => array_values($values)];
};

$eventsByCategory = ['labels' => [], 'values' => []];
try {
    if (dashboardTableExists($pdo, 'events')) {
        $rows = dashboardRows(
            $pdo,
            "SELECT COALESCE(NULLIF(event_type, ''), status, 'Uncategorized') AS label, COUNT(*) AS total
             FROM events
             GROUP BY label
             ORDER BY total DESC, label
             LIMIT 10",
            [],
            'events_by_category'
        );
        $eventsByCategory = [
            'labels' => array_map(static fn(array $row): string => (string)$row['label'], $rows),
            'values' => array_map(static fn(array $row): int => (int)$row['total'], $rows)
        ];
    }
} catch (Throwable $exception) {
    error_log('[Dashboard Charts] events_by_category query failed: ' . $exception->getMessage());
}

$resourcesTop = ['labels' => [], 'values' => []];
try {
    if (dashboardTableExists($pdo, 'borrow_records') && dashboardTableExists($pdo, 'resources')) {
        $rows = dashboardRows(
            $pdo,
            "SELECT r.name AS label, COUNT(br.borrow_id) AS total
             FROM resources r
             INNER JOIN borrow_records br ON br.item_id = r.item_id
             GROUP BY r.item_id, r.name
             ORDER BY total DESC, r.name
             LIMIT 6",
            [],
            'resources_top'
        );
        $resourcesTop = [
            'labels' => array_map(static fn(array $row): string => (string)$row['label'], $rows),
            'values' => array_map(static fn(array $row): int => (int)$row['total'], $rows)
        ];
    }
} catch (Throwable $exception) {
    error_log('[Dashboard Charts] resources_top query failed: ' . $exception->getMessage());
}

jsonResponse([
    'success' => true,
    'youth_trend' => $monthlyCounts('users', 'created_at', 'youth_trend'),
    'events_by_category' => $eventsByCategory,
    'resources_top' => $resourcesTop,
    'health_by_month' => $monthlyCounts('health_announcements', 'created_at', 'health_by_month')
]);
