<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security_helpers.php';
securityRequireCsrf();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$staff = requireAdminApi();
securityRequireCsrf();
$data = json_decode(file_get_contents('php://input'), true);
$data = is_array($data) ? $data : $_POST;
$itemId = isset($data['item_id']) && is_string($data['item_id']) ? trim($data['item_id']) : '';
$memberId = isset($data['member_id']) && is_string($data['member_id']) ? trim($data['member_id']) : '';
$quantity = filter_var($data['quantity'] ?? 1, FILTER_VALIDATE_INT);
$dueDate = isset($data['due_date']) && is_string($data['due_date']) ? $data['due_date'] : '';
$parsedDueDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);
$dateErrors = DateTimeImmutable::getLastErrors();

if ($itemId === '' || strlen($itemId) > 50 || $memberId === '' || strlen($memberId) > 50
    || !$quantity || $quantity < 1 || !$parsedDueDate
    || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
    || $parsedDueDate->format('Y-m-d') !== $dueDate) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid item, member, quantity, and due date are required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $memberQuery = $pdo->prepare(
        "SELECT u.status, yp.firstname, yp.lastname
         FROM users u
         LEFT JOIN youth_profiles yp ON yp.user_id = u.id
         WHERE u.member_id = ?
         LIMIT 1
         FOR UPDATE"
    );
    $memberQuery->execute([$memberId]);
    $member = $memberQuery->fetch(PDO::FETCH_ASSOC);
    if (!$member || $member['status'] !== 'active') {
        throw new RuntimeException('Member is not eligible for this service.');
    }

    $resourceQuery = $pdo->prepare(
        'SELECT available FROM resources WHERE item_id = ? LIMIT 1 FOR UPDATE'
    );
    $resourceQuery->execute([$itemId]);
    $resource = $resourceQuery->fetch(PDO::FETCH_ASSOC);
    if (!$resource) {
        throw new RuntimeException('Resource not found.');
    }
    if ((int)$resource['available'] < $quantity) {
        throw new RuntimeException('Not enough units are available.');
    }

    $borrowerName = trim((string)$member['firstname'] . ' ' . (string)$member['lastname']);
    if ($borrowerName === '') {
        throw new RuntimeException('Member profile is incomplete.');
    }

    $insert = $pdo->prepare(
        "INSERT INTO borrow_records
            (item_id, member_id, borrower_name, quantity, due_date, status)
         VALUES (?, ?, ?, ?, ?, 'Borrowed')
         RETURNING borrow_id"
    );
    $insert->execute([$itemId, $memberId, $borrowerName, $quantity, $dueDate]);
    $borrowId = (int)$insert->fetchColumn();

    $updateStock = $pdo->prepare(
        'UPDATE resources SET available = available - ? WHERE item_id = ? AND available >= ?'
    );
    $updateStock->execute([$quantity, $itemId, $quantity]);
    if ($updateStock->rowCount() !== 1) {
        throw new RuntimeException('Resource availability changed. Refresh and try again.');
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Item borrowed successfully.',
        'borrow_id' => $borrowId,
        'item_id' => $itemId,
        'member_id' => $memberId,
        'quantity' => $quantity,
        'due_date' => $dueDate,
        'status' => 'Borrowed'
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
    error_log('[Borrow] Failed to create verified borrow request: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to save the borrowing request.']);
}
