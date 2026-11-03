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
    $action = health_trim_null($data['action'] ?? null);

    if ($id < 1 || $action === null) {
        health_response(['success' => false, 'message' => 'Announcement ID and action are required'], 422);
    }

    $existing = health_fetch($pdo, $id);
    if (!$existing) {
        health_response(['success' => false, 'message' => 'Announcement not found'], 404);
    }

    $nextStatus = match ($action) {
        'publish' => 'Published',
        'unpublish' => 'Draft',
        'cancel' => 'Cancelled',
        default => null,
    };

    if ($nextStatus === null) {
        health_response(['success' => false, 'message' => 'Invalid action'], 422);
    }

    $publishedAt = $existing['published_at'];
    if ($nextStatus === 'Published' && $publishedAt === null) {
        $publishedAt = date('Y-m-d H:i:s');
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'UPDATE health_announcements
         SET status = ?, published_at = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
         WHERE id = ?'
    );
    $stmt->execute([$nextStatus, $publishedAt, (int)$auth['id'], $id]);

    health_log($pdo, $auth, $nextStatus === 'Cancelled' ? 'health_announcement_cancel' : 'health_announcement_status', $id);
    $pdo->commit();

    health_response([
        'success' => true,
        'message' => 'Announcement status updated',
        'id' => $id,
        'status' => $nextStatus,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    health_error_response($e);
}
