<?php

require_once __DIR__ . '/../includes/request.php';
secureSessionStart();
header('Content-Type: application/json; charset=utf-8');

require_once 'db_connect.php';
require_once 'auth.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/youth_lifecycle.php';

requireAdminApi();
securityRequireCsrf();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$newStatus = is_string($_POST['status'] ?? null) ? trim($_POST['status']) : '';
$reason = is_string($_POST['reason'] ?? null) ? trim($_POST['reason']) : '';
$memberId = is_string($_POST['member_id'] ?? null) ? trim($_POST['member_id']) : '';
$masterlistId = filter_var($_POST['masterlist_id'] ?? null, FILTER_VALIDATE_INT);

if (!in_array($newStatus, ['active', 'inactive'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status change']);
    exit;
}
if ($newStatus === 'inactive' && $reason === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A reason is required to mark a youth inactive']);
    exit;
}
if (strlen($reason) > 500) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Reason must be 500 characters or fewer']);
    exit;
}
if (($memberId === '') === ($masterlistId === false || $masterlistId === null)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Provide one youth or masterlist record']);
    exit;
}

$adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
$adminUsername = (string)($_SESSION['admin_username'] ?? 'admin');
if ($newStatus === 'active' && $reason === '') {
    $reason = 'Reactivated by admin.';
}

try {
    $pdo->beginTransaction();
    if ($memberId !== '') {
        $stmt = $pdo->prepare('SELECT id, status FROM public.users WHERE member_id = ? FOR UPDATE');
        $stmt->execute([$memberId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        $recordType = 'user';
        $recordId = $record ? (int)$record['id'] : 0;
        $currentStatus = $record['status'] ?? null;
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, status, claimed_by_user_id
             FROM public.kk_masterlist WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([(int)$masterlistId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($record && $record['claimed_by_user_id'] !== null) {
            $recordType = 'user';
            $recordId = (int)$record['claimed_by_user_id'];
            $stmt = $pdo->prepare('SELECT status FROM public.users WHERE id = ? FOR UPDATE');
            $stmt->execute([$recordId]);
            $currentStatus = $stmt->fetchColumn();
        } else {
            $recordType = 'masterlist';
            $recordId = $record ? (int)$record['id'] : 0;
            $currentStatus = $record['status'] ?? null;
        }
    }

    if (!$record || $currentStatus === false || $currentStatus === null) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Youth record not found']);
        exit;
    }
    if ($currentStatus === 'archived') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Age-archived records cannot be reactivated or manually changed']);
        exit;
    }

    $changed = setYouthLifecycleStatus(
        $pdo,
        $recordType,
        $recordId,
        $newStatus,
        $reason,
        $adminId,
        $adminUsername
    );
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $changed ? 'Youth status updated successfully' : 'Youth already has that status',
        'status' => $newStatus
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Youth Status Update] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update youth status']);
}
