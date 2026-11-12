<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
verificationRequireMethod('POST');
$admin = requireAdminApi();
securityRequireCsrf();
$input = verificationInput();
$requestId = filter_var($input['request_id'] ?? null, FILTER_VALIDATE_INT);
$items = $input['items'] ?? null;
$dueDate = is_string($input['due_date'] ?? null) ? trim($input['due_date']) : '';
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);
$errors = DateTimeImmutable::getLastErrors();
if (!$requestId || $requestId < 1 || !is_array($items) || !$items || count($items) > 20
    || !$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))
    || $date->format('Y-m-d') !== $dueDate || $date < new DateTimeImmutable('today')) {
    verificationResponse(['success' => false, 'message' => 'A valid pending request, selected items, quantities, and due date are required.'], 400);
}
$requestedItems = [];
foreach ($items as $item) {
    if (!is_array($item) || !is_string($item['item_id'] ?? null)) {
        verificationResponse(['success' => false, 'message' => 'Each selected inventory item must include an item ID and quantity.'], 400);
    }
    $itemId = trim($item['item_id']);
    $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
    if ($itemId === '' || strlen($itemId) > 50 || !$quantity || $quantity < 1 || isset($requestedItems[$itemId])) {
        verificationResponse(['success' => false, 'message' => 'Selected inventory items and quantities must be valid and unique.'], 400);
    }
    $requestedItems[$itemId] = $quantity;
}
ksort($requestedItems, SORT_STRING);
try {
    $pdo->beginTransaction();
    $requestQuery = $pdo->prepare(
        "SELECT br.request_id, br.user_id, br.member_id, br.verification_token_id,
                svt.service_ref, svt.user_id AS token_user_id, svt.service_type AS token_service_type,
                svt.status AS token_status, svt.code_verified_at
         FROM borrow_requests br
         JOIN service_verification_tokens svt ON svt.id = br.verification_token_id
         WHERE br.request_id = ? AND br.service_type = 'borrow' AND br.status = 'Pending'
         LIMIT 1 FOR UPDATE OF br"
    );
    $requestQuery->execute([$requestId]);
    $request = $requestQuery->fetch(PDO::FETCH_ASSOC);
    if (!$request || (int)$request['user_id'] !== (int)$request['service_ref']
        || (int)$request['token_user_id'] !== (int)$request['user_id']
        || $request['token_service_type'] !== 'borrow' || $request['token_status'] !== 'used'
        || $request['code_verified_at'] === null) {
        throw new RuntimeException('This pending borrow request is invalid or already processed.');
    }
    $placeholders = implode(',', array_fill(0, count($requestedItems), '?'));
    $resource = $pdo->prepare("SELECT item_id, available FROM resources WHERE item_id IN ($placeholders) ORDER BY item_id FOR UPDATE");
    $resource->execute(array_keys($requestedItems));
    $stocks = [];
    foreach ($resource->fetchAll(PDO::FETCH_ASSOC) as $stock) {
        $stocks[(string)$stock['item_id']] = (int)$stock['available'];
    }
    if (count($stocks) !== count($requestedItems)) {
        throw new RuntimeException('One or more selected inventory items no longer exist.');
    }
    foreach ($requestedItems as $itemId => $quantity) {
        if ($stocks[$itemId] < $quantity) {
            throw new RuntimeException('Not enough stock is available for ' . $itemId . '.');
        }
    }
    $userQuery = $pdo->prepare(
        "SELECT COALESCE(NULLIF(TRIM(CONCAT_WS(' ', yp.firstname, yp.lastname)), ''), u.member_id) AS display_name
         FROM users u LEFT JOIN youth_profiles yp ON yp.user_id = u.id
         WHERE u.id = ? AND u.status = 'active'"
    );
    $userQuery->execute([(int)$request['user_id']]);
    $name = $userQuery->fetchColumn();
    if (!is_string($name) || $name === '') throw new RuntimeException('The youth account is no longer active.');
    $insert = $pdo->prepare(
        "INSERT INTO borrow_records
            (item_id, member_id, borrower_name, quantity, due_date, status, borrow_verification_token_id, borrowed_by_admin_id)
         VALUES (?, ?, ?, ?, ?, 'Borrowed', ?, ?) RETURNING borrow_id"
    );
    $stockUpdate = $pdo->prepare('UPDATE resources SET available = available - ?, updated_at = CURRENT_TIMESTAMP WHERE item_id = ? AND available >= ?');
    $borrowIds = [];
    foreach ($requestedItems as $itemId => $quantity) {
        $insert->execute([$itemId, $request['member_id'], $name, $quantity, $dueDate, $request['verification_token_id'], $admin['id']]);
        $borrowIds[] = (int)$insert->fetchColumn();
        $stockUpdate->execute([$quantity, $itemId, $quantity]);
        if ($stockUpdate->rowCount() !== 1) throw new RuntimeException('Inventory availability changed. Refresh and retry.');
    }
    $finish = $pdo->prepare(
        "UPDATE borrow_requests SET status = 'Completed', processed_at = CURRENT_TIMESTAMP,
            processed_by_admin_id = ?, updated_at = CURRENT_TIMESTAMP WHERE request_id = ? AND status = 'Pending'"
    );
    $finish->execute([(int)$admin['id'], $requestId]);
    if ($finish->rowCount() !== 1) throw new RuntimeException('This request was already processed.');
    verificationAudit($pdo, 'process_request', 'success', (int)$request['verification_token_id'], null,
        (int)$request['user_id'], (int)$admin['id'], 'borrow', (string)$requestId);
    $pdo->commit();
    verificationResponse(['success' => true, 'message' => 'Borrowing completed.', 'borrow_ids' => $borrowIds]);
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    verificationResponse(['success' => false, 'message' => $exception->getMessage()], 409);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[Borrow Verification] Admin processing failed: ' . $exception->getMessage());
    verificationResponse(['success' => false, 'message' => 'Unable to process this borrowing request.'], 500);
}
