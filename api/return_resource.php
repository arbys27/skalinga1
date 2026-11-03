<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$staff = requireAdminApi();
securityRequireCsrf();
$input = json_decode(file_get_contents('php://input'), true);
$input = is_array($input) ? $input : $_POST;
$borrowId = filter_var($input['borrow_id'] ?? null, FILTER_VALIDATE_INT);
$returnCondition = isset($input['return_condition']) && is_string($input['return_condition'])
    ? trim($input['return_condition'])
    : '';

if (!$borrowId || $borrowId < 1 || $returnCondition === '' || strlen($returnCondition) > 500) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid borrowing record and return condition are required.']);
    exit;
}

try {
    $pdo->beginTransaction();
    $select = $pdo->prepare(
        'SELECT item_id, status, quantity
         FROM borrow_records
         WHERE borrow_id = ?
         LIMIT 1
         FOR UPDATE'
    );
    $select->execute([$borrowId]);
    $record = $select->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new RuntimeException('Borrow record not found.');
    }
    if ($record['status'] === 'Returned') {
        throw new RuntimeException('Item already marked as returned.');
    }
    $updateBorrow = $pdo->prepare(
        "UPDATE borrow_records
         SET status = 'Returned', return_date = CURRENT_TIMESTAMP, return_condition = ?
         WHERE borrow_id = ? AND status <> 'Returned'"
    );
    $updateBorrow->execute([$returnCondition, $borrowId]);
    if ($updateBorrow->rowCount() !== 1) {
        throw new RuntimeException('The borrowing record could not be updated.');
    }

    $updateStock = $pdo->prepare(
        'UPDATE resources SET available = available + ? WHERE item_id = ?'
    );
    $updateStock->execute([(int)($record['quantity'] ?? 1), $record['item_id']]);
    if ($updateStock->rowCount() !== 1) {
        throw new RuntimeException('The resource inventory could not be updated.');
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Item marked as returned successfully.',
        'borrow_id' => $borrowId,
        'return_condition' => $returnCondition,
        'status' => 'Returned'
    ], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Borrow] Verified return failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to record the return.']);
}
