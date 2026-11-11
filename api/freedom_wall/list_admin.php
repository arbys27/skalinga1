<?php

require_once dirname(__DIR__) . '/db_connect.php';
require_once __DIR__ . '/common.php';

requireAdminApi();
freedomWallRequireMethod('GET');

$status = $_GET['status'] ?? 'all';
$category = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? '';
$statuses = ['all', 'pending', 'approved', 'rejected'];
$categories = ['all', 'Feedback', 'Suggestion', 'Concern', 'Other'];
if (!is_string($status) || !in_array($status, $statuses, true)) {
    freedomWallResponse(['success' => false, 'message' => 'Invalid status filter.'], 422);
}
if (!is_string($category) || !in_array($category, $categories, true)) {
    freedomWallResponse(['success' => false, 'message' => 'Invalid category filter.'], 422);
}
if (!is_string($search) || freedomWallLength($search) > 120) {
    freedomWallResponse(['success' => false, 'message' => 'Search text is too long.'], 422);
}

try {
    $sql = 'SELECT p.id, p.user_id, p.category, p.title, p.body, p.is_anonymous,
                   p.status, p.rejection_reason, p.is_hidden, p.created_at,
                   p.moderated_at, p.approved_at, km.voter_id, u.member_id,
                   yp.firstname, yp.lastname, yp.phone
            FROM freedom_wall_posts p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN youth_profiles yp ON yp.user_id = p.user_id
            LEFT JOIN kk_masterlist km ON km.id = u.masterlist_id';
    $conditions = [];
    $params = [];
    if ($status !== 'all') {
        $conditions[] = 'p.status = ?';
        $params[] = $status;
    }
    if ($category !== 'all') {
        $conditions[] = 'p.category = ?';
        $params[] = $category;
    }
    if ($search !== '') {
        $conditions[] = '(p.title ILIKE ? OR p.body ILIKE ?)';
        $term = '%' . $search . '%';
        $params[] = $term;
        $params[] = $term;
    }
    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY p.created_at DESC LIMIT 500';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($posts as &$post) {
        $post['is_hidden'] = postgresBoolean($post['is_hidden']);
        $post['is_anonymous'] = postgresBoolean($post['is_anonymous']);
        $post['attention_flags'] = freedomWallAttentionFlags((string)$post['title'], (string)$post['body']);
        $post['needs_attention'] = $post['attention_flags'] !== [];
    }
    unset($post);
    freedomWallResponse(['success' => true, 'data' => $posts]);
} catch (PDOException $exception) {
    freedomWallDatabaseError($exception);
}
