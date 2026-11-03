<?php
require_once dirname(__DIR__) . '/db_connect.php';
require_once __DIR__ . '/common.php';

health_require_login();

try {
    $search = health_trim_null($_GET['search'] ?? null);
    $category = health_validate_category($_GET['category'] ?? null);
    $period = health_trim_null($_GET['period'] ?? null) ?? 'upcoming';
    $period = in_array($period, ['upcoming', 'past', 'all'], true) ? $period : 'upcoming';

    $where = ["status IN ('Published', 'Cancelled')"];
    $params = [];

    if ($search !== null) {
        $where[] = '(title ILIKE ? OR description ILIKE ? OR venue ILIKE ? OR requirements ILIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }

    if ($category !== null) {
        $where[] = 'category = ?';
        $params[] = $category;
    }

    if ($period === 'upcoming') {
        $where[] = 'event_date >= CURRENT_DATE';
    } elseif ($period === 'past') {
        $where[] = 'event_date < CURRENT_DATE';
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $orderSql = match ($period) {
        'past' => ' ORDER BY event_date DESC, start_time DESC, created_at DESC',
        'all' => ' ORDER BY CASE WHEN event_date >= CURRENT_DATE THEN 0 ELSE 1 END, event_date ASC, start_time ASC, created_at DESC',
        default => ' ORDER BY event_date ASC, start_time ASC, created_at DESC',
    };

    $stmt = $pdo->prepare(
        'SELECT id, title, category, description, event_date, start_time, end_time, venue, slots, requirements, image_path, status, published_at, created_at, updated_at
         FROM health_announcements' . $whereSql . $orderSql
    );
    $stmt->execute($params);

    health_response([
        'success' => true,
        'data' => array_map('health_public_row', $stmt->fetchAll(PDO::FETCH_ASSOC)),
    ]);
} catch (Throwable $e) {
    health_error_response($e);
}
