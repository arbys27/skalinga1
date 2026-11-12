<?php
declare(strict_types=1);
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';
$auth = requireAuth(['youth']);
try {
    $query = $pdo->prepare(
        "SELECT request_id, service_type, status, requested_at, processed_at
         FROM borrow_requests
         WHERE user_id = ? AND status = 'Pending'
         ORDER BY requested_at DESC
         LIMIT 10"
    );
    $query->execute([(int)$auth['id']]);
    $requests = $query->fetchAll(PDO::FETCH_ASSOC);
    authJsonResponse(['success' => true, 'data' => $requests, 'total' => count($requests)]);
} catch (Throwable $exception) {
    error_log('[Youth Borrow Requests] Pending request lookup failed: ' . $exception->getMessage());
    authJsonResponse(['success' => false, 'message' => 'Unable to load your pending requests.'], 500);
}
