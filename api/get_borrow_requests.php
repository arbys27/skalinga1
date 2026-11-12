<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';
requireAdminApi();
try {
    $query = $pdo->query(
        "SELECT br.request_id, br.service_type, br.member_id, br.requested_at,
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', yp.firstname, yp.lastname)), ''), br.member_id) AS borrower_name
         FROM borrow_requests br
         LEFT JOIN youth_profiles yp ON yp.user_id = br.user_id
         WHERE br.status = 'Pending'
         ORDER BY br.requested_at ASC"
    );
    $requests = $query->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $requests, 'total' => count($requests)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('[Borrow Requests] Pending request query failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to retrieve pending requests.']);
}
