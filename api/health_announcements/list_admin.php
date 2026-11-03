<?php
require_once dirname(__DIR__) . '/db_connect.php';
require_once __DIR__ . '/common.php';

health_auth_or_fail();

try {
    $search = health_trim_null($_GET['search'] ?? null);
    $category = health_validate_category($_GET['category'] ?? null);
    $status = health_validate_status($_GET['status'] ?? null);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, min(100, (int)($_GET['per_page'] ?? ($_GET['perPage'] ?? 20))));
    $offset = ($page - 1) * $perPage;

    $where = [];
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

    if ($status !== null) {
        $where[] = 'status = ?';
        $params[] = $status;
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM health_announcements' . $whereSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT id, title, category, description, event_date, start_time, end_time, venue, slots, requirements, image_path, status, published_at, created_by, updated_by, created_at, updated_at
         FROM health_announcements' . $whereSql . '
         ORDER BY created_at DESC
         LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset
    );
    $stmt->execute($params);

    health_response([
        'success' => true,
        'data' => array_map('health_public_row', $stmt->fetchAll(PDO::FETCH_ASSOC)),
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0,
        ],
    ]);
} catch (Throwable $e) {
    health_error_response($e);
}
