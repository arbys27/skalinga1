<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
verificationRequireMethod('POST');
$admin = requireAdminApi();
securityRequireCsrf();
$input = verificationInput();
$token = verificationTokenInput($input);
$code = is_string($input['code'] ?? null) ? trim($input['code']) : '';
$itemId = is_string($input['item_id'] ?? null) ? trim($input['item_id']) : '';
$quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT);
$dueDate = is_string($input['due_date'] ?? null) ? trim($input['due_date']) : '';
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);
$errors = DateTimeImmutable::getLastErrors();
if ($token === null || !preg_match('/^\d{2}$/', $code) || $itemId === '' || strlen($itemId) > 50
    || !$quantity || $quantity < 1 || !$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))
    || $date->format('Y-m-d') !== $dueDate || $date < new DateTimeImmutable('today')) {
    verificationResponse(['success' => false, 'message' => 'Valid token, code, item, quantity, and future due date are required.'], 400);
}
$hash = hash('sha256', $token);
try {
    $pdo->beginTransaction();
    $record = verificationLoadToken($pdo, $hash, true);
    if (!$record || $record['status'] !== 'active' || $record['service_type'] !== 'borrow'
        || (int)$record['scanned_by_admin_id'] !== (int)$admin['id']
        || verificationIsExpired((string)$record['expires_at'])) {
        throw new RuntimeException('This borrow verification is invalid, expired, or not scanned by this staff account.');
    }
    if (!is_string($record['code_hash']) || !hash_equals($record['code_hash'], otpCodeHash($code))
        || verificationIsExpired((string)$record['code_expires_at']) || (int)$record['code_attempts'] >= 5) {
        $attempt = min(5, (int)$record['code_attempts'] + 1);
        $pdo->prepare('UPDATE service_verification_tokens SET code_attempts = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$attempt, (int)$record['id']]);
        throw new RuntimeException('The short verification code is incorrect, expired, or locked.');
    }
    $user = verificationBorrowUser($pdo, 'borrow', (string)$record['service_ref'], (int)$record['user_id'], true);
    if (!$user) {
        throw new RuntimeException('The youth account is no longer eligible.');
    }
    $resource = $pdo->prepare('SELECT name, available FROM resources WHERE item_id = ? FOR UPDATE');
    $resource->execute([$itemId]);
    $stock = $resource->fetch(PDO::FETCH_ASSOC);
    if (!$stock || (int)$stock['available'] < $quantity) {
        throw new RuntimeException('The requested quantity is not available.');
    }
    $insert = $pdo->prepare(
        "INSERT INTO borrow_records
            (item_id, member_id, borrower_name, quantity, due_date, status, borrow_verification_token_id, borrowed_by_admin_id)
         VALUES (?, ?, ?, ?, ?, 'Borrowed', ?, ?) RETURNING borrow_id"
    );
    $insert->execute([$itemId, $user['member_id'], $user['display_name'], $quantity, $dueDate, $record['id'], $admin['id']]);
    $borrowId = (int)$insert->fetchColumn();
    $stockUpdate = $pdo->prepare('UPDATE resources SET available = available - ?, updated_at = CURRENT_TIMESTAMP WHERE item_id = ? AND available >= ?');
    $stockUpdate->execute([$quantity, $itemId, $quantity]);
    if ($stockUpdate->rowCount() !== 1) {
        throw new RuntimeException('Inventory could not be updated.');
    }
    $use = $pdo->prepare(
        "UPDATE service_verification_tokens
         SET status = 'used', used_at = CURRENT_TIMESTAMP, code_hash = NULL, code_expires_at = NULL,
             scanned_by_admin_id = ?, updated_at = CURRENT_TIMESTAMP
         WHERE id = ? AND status = 'active'"
    );
    $use->execute([$admin['id'], $record['id']]);
    if ($use->rowCount() !== 1) {
        throw new RuntimeException('This verification was already completed.');
    }
    verificationAudit($pdo, 'confirm', 'success', (int)$record['id'], $hash, (int)$record['user_id'],
        (int)$admin['id'], 'borrow', (string)$record['service_ref']);
    $pdo->commit();
    verificationResponse(['success' => true, 'message' => 'Borrow recorded successfully.', 'borrow_id' => $borrowId]);
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    verificationResponse(['success' => false, 'message' => $exception->getMessage()], 409);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[Borrow Verification] Completion failed: ' . $exception->getMessage());
    verificationResponse(['success' => false, 'message' => 'Unable to complete the borrow right now.'], 500);
}
