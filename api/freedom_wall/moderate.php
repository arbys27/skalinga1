<?php

require_once dirname(__DIR__) . '/db_connect.php';
require_once __DIR__ . '/common.php';

$auth = requireAdminApi();
freedomWallRequireMethod('POST');
securityRequireCsrf();

$input = freedomWallInput();
$id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
$action = isset($input['action']) && is_string($input['action']) ? trim($input['action']) : '';
$reason = isset($input['reason']) && is_string($input['reason']) ? trim($input['reason']) : '';
if (!$id || !in_array($action, ['approve', 'reject', 'hide', 'unhide'], true)) {
    freedomWallResponse(['success' => false, 'message' => 'Invalid moderation request.'], 422);
}
if (freedomWallLength($reason) > 2000) {
    freedomWallResponse(['success' => false, 'message' => 'Reason must be 2,000 characters or fewer.'], 422);
}

try {
    $pdo->beginTransaction();
    $find = $pdo->prepare('SELECT id, status, is_hidden FROM freedom_wall_posts WHERE id = ? FOR UPDATE');
    $find->execute([$id]);
    $post = $find->fetch(PDO::FETCH_ASSOC);
    if (!$post) {
        $pdo->rollBack();
        freedomWallResponse(['success' => false, 'message' => 'Post not found.'], 404);
    }

    if ($action === 'approve' || $action === 'reject') {
        if ($post['status'] !== 'pending') {
            $pdo->rollBack();
            freedomWallResponse(['success' => false, 'message' => 'Only pending posts can be approved or rejected.'], 409);
        }
        if ($action === 'approve') {
            $stmt = $pdo->prepare(
                "UPDATE freedom_wall_posts
                 SET status = 'approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP,
                     moderated_by = ?, moderated_at = CURRENT_TIMESTAMP, rejection_reason = NULL
                 WHERE id = ?"
            );
            $stmt->execute([$auth['id'], $auth['id'], $id]);
        } else {
            $stmt = $pdo->prepare(
                "UPDATE freedom_wall_posts
                 SET status = 'rejected', rejection_reason = ?, moderated_by = ?,
                     moderated_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );
            $stmt->execute([$reason !== '' ? $reason : null, $auth['id'], $id]);
        }
        freedomWallLog($pdo, $auth, 'freedom_wall_' . $action, (int)$id);
    } elseif ($action === 'hide') {
        if ($post['status'] !== 'approved') {
            $pdo->rollBack();
            freedomWallResponse(['success' => false, 'message' => 'Only approved posts can be hidden.'], 409);
        }
        $stmt = $pdo->prepare(
            'UPDATE freedom_wall_posts
             SET is_hidden = true, rejection_reason = ?, moderated_by = ?, moderated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([$reason !== '' ? $reason : null, $auth['id'], $id]);
        freedomWallLog($pdo, $auth, 'freedom_wall_hide', (int)$id);
    } else {
        if ($post['status'] !== 'approved' || !postgresBoolean($post['is_hidden'])) {
            $pdo->rollBack();
            freedomWallResponse(['success' => false, 'message' => 'Only hidden approved posts can be unhidden.'], 409);
        }
        $stmt = $pdo->prepare(
            'UPDATE freedom_wall_posts
             SET is_hidden = false, rejection_reason = NULL, moderated_by = ?, moderated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([$auth['id'], $id]);
        freedomWallLog($pdo, $auth, 'freedom_wall_unhide', (int)$id);
    }

    $pdo->commit();
    freedomWallResponse(['success' => true, 'message' => 'Moderation action saved.']);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    freedomWallDatabaseError($exception);
}
