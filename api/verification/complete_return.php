<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
verificationRequireMethod('POST');
$admin = requireAdminApi();
securityRequireCsrf();
$input = verificationInput();
$token = verificationTokenInput($input);
$code = is_string($input['code'] ?? null) ? trim($input['code']) : '';
$borrowIds = $input['borrow_ids'] ?? [];
$condition = is_string($input['return_condition'] ?? null) ? trim($input['return_condition']) : '';
if ($token === null || !preg_match('/^\d{2}$/', $code) || !is_array($borrowIds) || !$borrowIds
    || count($borrowIds) > 20 || $condition === '' || strlen($condition) > 500) {
    verificationResponse(['success' => false, 'message' => 'Token, code, selected items, and return condition are required.'], 400);
}
$ids = array_values(array_filter(array_map(static fn($id) => filter_var($id, FILTER_VALIDATE_INT), $borrowIds)));
if (count($ids) !== count($borrowIds)) {
    verificationResponse(['success' => false, 'message' => 'Invalid borrowing record selection.'], 400);
}
$hash = hash('sha256', $token);
try {
    $pdo->beginTransaction();
    $record = verificationLoadToken($pdo, $hash, true);
    if (!$record || $record['status'] !== 'active' || $record['service_type'] !== 'return'
        || (int)$record['scanned_by_admin_id'] !== (int)$admin['id']
        || verificationIsExpired((string)$record['expires_at'])) {
        throw new RuntimeException('This return verification is invalid, expired, or not scanned by this staff account.');
    }
    if (!is_string($record['code_hash']) || !hash_equals($record['code_hash'], otpCodeHash($code))
        || verificationIsExpired((string)$record['code_expires_at']) || (int)$record['code_attempts'] >= 5) {
        $attempt = min(5, (int)$record['code_attempts'] + 1);
        $pdo->prepare('UPDATE service_verification_tokens SET code_attempts = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$attempt, (int)$record['id']]);
        throw new RuntimeException('The short verification code is incorrect, expired, or locked.');
    }
    $user = verificationBorrowUser($pdo, 'return', (string)$record['service_ref'], (int)$record['user_id'], true);
    if (!$user) throw new RuntimeException('The youth account is no longer eligible.');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $select = $pdo->prepare(
        "SELECT borrow_id, item_id, quantity FROM borrow_records
         WHERE borrow_id IN ($placeholders) AND member_id = ? AND status IN ('Borrowed', 'Overdue')
         FOR UPDATE"
    );
    $select->execute(array_merge($ids, [$user['member_id']]));
    $rows = $select->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== count($ids)) throw new RuntimeException('One or more selected items are no longer active.');
    $update = $pdo->prepare(
        "UPDATE borrow_records SET status = 'Returned', return_date = CURRENT_TIMESTAMP,
            return_condition = ?, returned_by_admin_id = ?, return_verification_token_id = ?, updated_at = CURRENT_TIMESTAMP
         WHERE borrow_id = ? AND status IN ('Borrowed', 'Overdue')"
    );
    $stock = $pdo->prepare('UPDATE resources SET available = available + ?, updated_at = CURRENT_TIMESTAMP WHERE item_id = ?');
    foreach ($rows as $row) {
        $update->execute([$condition, $admin['id'], $record['id'], $row['borrow_id']]);
        if ($update->rowCount() !== 1) throw new RuntimeException('A borrowing record changed. Scan again.');
        $stock->execute([(int)$row['quantity'], $row['item_id']]);
    }
    $use = $pdo->prepare(
        "UPDATE service_verification_tokens SET status = 'used', used_at = CURRENT_TIMESTAMP,
            code_hash = NULL, code_expires_at = NULL, updated_at = CURRENT_TIMESTAMP
         WHERE id = ? AND status = 'active'"
    );
    $use->execute([$record['id']]);
    if ($use->rowCount() !== 1) throw new RuntimeException('This verification was already completed.');
    verificationAudit($pdo, 'confirm', 'success', (int)$record['id'], $hash, (int)$record['user_id'],
        (int)$admin['id'], 'return', (string)$record['service_ref']);
    $pdo->commit();
    verificationResponse(['success' => true, 'message' => 'Return recorded successfully.', 'returned_count' => count($rows)]);
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    verificationResponse(['success' => false, 'message' => $exception->getMessage()], 409);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[Return Verification] Completion failed: ' . $exception->getMessage());
    verificationResponse(['success' => false, 'message' => 'Unable to complete the return right now.'], 500);
}
