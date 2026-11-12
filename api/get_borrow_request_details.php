<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';
requireAdminApi();
$requestId = filter_var($_GET['request_id'] ?? null, FILTER_VALIDATE_INT);
if (!$requestId || $requestId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid pending request is required.']);
    exit;
}
try {
    $requestQuery = $pdo->prepare(
        "SELECT request_id, service_type, member_id, status FROM borrow_requests
         WHERE request_id = ? AND status = 'Pending' LIMIT 1"
    );
    $requestQuery->execute([$requestId]);
    $request = $requestQuery->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pending request not found.']);
        exit;
    }
    $items = [];
    if ($request['service_type'] === 'return') {
        $query = $pdo->prepare(
            "SELECT br.borrow_id, br.item_id, br.quantity, br.due_date, br.status, r.name AS item_name
             FROM borrow_records br JOIN resources r ON r.item_id = br.item_id
             WHERE br.member_id = ? AND br.borrow_id = ANY(
                 SELECT unnest(return_borrow_ids) FROM borrow_requests WHERE request_id = ?
             ) AND br.status IN ('Borrowed', 'Overdue')
             ORDER BY br.due_date, br.borrow_id"
        );
        $query->execute([$request['member_id'], $requestId]);
        $items = $query->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode(['success' => true, 'request' => $request, 'items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('[Borrow Requests] Request detail lookup failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to retrieve request details.']);
}
