<?php
require_once dirname(__DIR__) . '/db_connect.php';
require_once __DIR__ . '/common.php';

$auth = health_auth_or_fail();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    health_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

try {
    $data = health_input();
    $id = (int)($data['id'] ?? 0);
    if ($id < 1) {
        health_response(['success' => false, 'message' => 'Announcement ID is required'], 422);
    }

    $existing = health_fetch($pdo, $id);
    if (!$existing) {
        health_response(['success' => false, 'message' => 'Announcement not found'], 404);
    }

    $stmt = $pdo->prepare('UPDATE health_announcements SET status = "Archived", updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([(int)$auth['id'], $id]);

    health_log($pdo, $auth, 'health_announcement_archive', $id);

    health_response([
        'success' => true,
        'message' => 'Health announcement archived',
        'id' => $id,
    ]);
} catch (Throwable $e) {
    health_error_response($e);
}
