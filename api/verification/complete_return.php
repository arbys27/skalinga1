<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
verificationRequireMethod('POST');
$admin = requireAdminApi();
securityRequireCsrf();
$input = verificationInput();
$requestId = filter_var($input['request_id'] ?? null, FILTER_VALIDATE_INT);
$borrowIds = $input['borrow_ids'] ?? [];
$condition = is_string($input['return_condition'] ?? null) ? trim($input['return_condition']) : '';
if (!$requestId || $requestId < 1 || !is_array($borrowIds) || !$borrowIds || count($borrowIds) > 20
    || $condition === '' || strlen($condition) > 500) {
    verificationResponse(['success' => false, 'message' => 'A pending return request, selected items, and return condition are required.'], 400);
}
$ids = array_values(array_filter(array_map(static fn($id) => filter_var($id, FILTER_VALIDATE_INT), $borrowIds)));
if (count($ids) !== count($borrowIds) || count(array_unique($ids)) !== count($ids)) {
    verificationResponse(['success' => false, 'message' => 'Invalid borrowing record selection.'], 400);
}
try {
    $pdo->beginTransaction();
    $requestQuery = $pdo->prepare(
        "SELECT br.request_id, br.user_id, br.member_id, br.verification_token_id,
                br.return_borrow_ids,
                svt.user_id AS token_user_id, svt.service_type AS token_service_type,
                svt.service_ref, svt.status AS token_status, svt.code_verified_at
         FROM borrow_requests br
         JOIN service_verification_tokens svt ON svt.id = br.verification_token_id
         WHERE br.request_id = ? AND br.service_type = 'return' AND br.status = 'Pending'
         LIMIT 1 FOR UPDATE"
    );
    $requestQuery->execute([$requestId]);
    $request = $requestQuery->fetch(PDO::FETCH_ASSOC);
    if (!$request || (int)$request['token_user_id'] !== (int)$request['user_id']
        || (int)$request['service_ref'] !== (int)$request['user_id']
        || $request['token_service_type'] !== 'return' || $request['token_status'] !== 'used'
        || $request['code_verified_at'] === null) {
        throw new RuntimeException('This pending return request is invalid or already processed.');
    }
    $places = implode(',', array_fill(0, count($ids), '?'));
    $select = $pdo->prepare(
        "SELECT borrow_id, item_id, quantity FROM borrow_records
         WHERE borrow_id IN ($places) AND member_id = ? AND borrow_id = ANY(CAST(? AS bigint[]))
           AND status IN ('Borrowed', 'Overdue')
         ORDER BY borrow_id
         FOR UPDATE"
    );
    $select->execute(array_merge($ids, [$request['member_id'], $request['return_borrow_ids']]));
    $rows = $select->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== count($ids)) throw new RuntimeException('One or more selected items no longer belong to this youth or are already returned.');
    $update = $pdo->prepare(
        "UPDATE borrow_records SET status = 'Returned', return_date = CURRENT_TIMESTAMP,
            return_condition = ?, returned_by_admin_id = ?, return_verification_token_id = ?, updated_at = CURRENT_TIMESTAMP
         WHERE borrow_id = ? AND status IN ('Borrowed', 'Overdue')"
    );
    $stock = $pdo->prepare('UPDATE resources SET available = available + ?, updated_at = CURRENT_TIMESTAMP WHERE item_id = ?');
    foreach ($rows as $row) {
        $update->execute([$condition, (int)$admin['id'], (int)$request['verification_token_id'], (int)$row['borrow_id']]);
        if ($update->rowCount() !== 1) throw new RuntimeException('A borrowing record changed. Refresh and retry.');
        $stock->execute([(int)$row['quantity'], $row['item_id']]);
        if ($stock->rowCount() !== 1) throw new RuntimeException('Inventory could not be restored.');
    }
    $finish = $pdo->prepare(
        "UPDATE borrow_requests SET status = 'Completed', processed_at = CURRENT_TIMESTAMP,
            processed_by_admin_id = ?, updated_at = CURRENT_TIMESTAMP WHERE request_id = ? AND status = 'Pending'"
    );
    $finish->execute([(int)$admin['id'], $requestId]);
    if ($finish->rowCount() !== 1) throw new RuntimeException('This request was already processed.');
    verificationAudit($pdo, 'process_request', 'success', (int)$request['verification_token_id'], null,
        (int)$request['user_id'], (int)$admin['id'], 'return', (string)$requestId);
    $pdo->commit();
    verificationResponse(['success' => true, 'message' => 'Return completed.', 'returned_count' => count($rows)]);
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    verificationResponse(['success' => false, 'message' => $exception->getMessage()], 409);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[Return Verification] Admin processing failed: ' . $exception->getMessage());
    verificationResponse(['success' => false, 'message' => 'Unable to process this return request.'], 500);
}
