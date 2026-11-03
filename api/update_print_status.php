<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$staff = requireAdminApi();
securityRequireCsrf();
$input = json_decode(file_get_contents('php://input'), true);
$requestId = filter_var(is_array($input) ? ($input['request_id'] ?? null) : null, FILTER_VALIDATE_INT);
$status = is_array($input) && is_string($input['status'] ?? null) ? $input['status'] : '';
$allowedStatuses = ['Pending', 'Printing', 'Completed', 'Claimed'];

if (!$requestId || $requestId < 1 || !in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid print request and status are required.']);
    exit;
}

try {
    $pdo->beginTransaction();
    $requestQuery = $pdo->prepare(
        'SELECT request_id, member_id, status
         FROM printing_requests
         WHERE request_id = ?
         LIMIT 1
         FOR UPDATE'
    );
    $requestQuery->execute([$requestId]);
    $request = $requestQuery->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        throw new RuntimeException('Print request not found.');
    }

    $claimedAt = $status === 'Claimed' ? date('Y-m-d H:i:s') : null;
    $update = $pdo->prepare(
        'UPDATE printing_requests
         SET status = ?, updated_at = CURRENT_TIMESTAMP, claimed_at = ?
         WHERE request_id = ?'
    );
    $update->execute([$status, $claimedAt, $requestId]);
    $activity = $pdo->prepare(
        'INSERT INTO activity_logs (user_id, role, action, identifier, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    );
    $activity->execute([
        (int)$staff['id'],
        $staff['role'],
        'print_status_update',
        (string)$requestId,
        clientIp(),
    ]);
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully.',
        'request_id' => $requestId,
        'status' => $status
    ], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Print] Status update failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update the print request status.']);
}
