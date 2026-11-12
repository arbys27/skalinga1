<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
verificationRequireMethod('POST');
$auth = requireAuth(['youth']);
securityRequireCsrf();
$input = verificationInput();
$tokenId = filter_var($input['token_id'] ?? null, FILTER_VALIDATE_INT);
$code = is_string($input['code'] ?? null) ? trim($input['code']) : '';
if (!$tokenId || $tokenId < 1 || preg_match('/^\d{2}$/', $code) !== 1) {
    verificationResponse(['success' => false, 'message' => 'A verification request and two-digit code are required.'], 400);
}
try {
    $pdo->beginTransaction();
    $query = $pdo->prepare(
        'SELECT id, token_hash, user_id, service_type, service_ref, status, expires_at,
                scanned_by_admin_id, code_hash, code_expires_at, code_attempts
         FROM service_verification_tokens
         WHERE id = ? AND user_id = ? AND service_type IN (\'borrow\', \'return\')
         LIMIT 1 FOR UPDATE'
    );
    $query->execute([$tokenId, (int)$auth['id']]);
    $token = $query->fetch(PDO::FETCH_ASSOC);
    if (!$token || $token['status'] !== 'active' || $token['scanned_by_admin_id'] === null
        || verificationIsExpired((string)$token['expires_at'])) {
        $pdo->rollBack();
        verificationResponse(['success' => false, 'message' => 'This verification is invalid, expired, or has not been scanned.'], 409);
    }
    $valid = is_string($token['code_hash']) && $token['code_hash'] !== ''
        && is_string($token['code_expires_at']) && !verificationIsExpired($token['code_expires_at'])
        && (int)$token['code_attempts'] < 5
        && hash_equals($token['code_hash'], otpCodeHash($code));
    if (!$valid) {
        $attempts = min(5, (int)$token['code_attempts'] + 1);
        $update = $pdo->prepare(
            'UPDATE service_verification_tokens
             SET code_attempts = ?, code_hash = CASE WHEN ? >= 5 THEN NULL ELSE code_hash END,
                 code_expires_at = CASE WHEN ? >= 5 THEN NULL ELSE code_expires_at END,
                 updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        $update->execute([$attempts, $attempts, $attempts, $tokenId]);
        verificationAudit($pdo, 'youth_code_confirm', 'code_failed', (int)$tokenId, (string)$token['token_hash'],
            (int)$auth['id'], (int)$token['scanned_by_admin_id'], (string)$token['service_type'], (string)$token['service_ref']);
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'The code is incorrect, expired, or locked.'], 400);
    }
    $user = verificationBorrowUser($pdo, (string)$token['service_type'], (string)$token['service_ref'], (int)$auth['id'], true);
    if (!$user) {
        $pdo->rollBack();
        verificationResponse(['success' => false, 'message' => 'The youth account is no longer eligible.'], 403);
    }
    $returnBorrowIds = [];
    if ($token['service_type'] === 'return') {
        $activeItems = $pdo->prepare(
            "SELECT borrow_id FROM borrow_records
             WHERE member_id = ? AND status IN ('Borrowed', 'Overdue')
             ORDER BY due_date, borrow_id
             FOR UPDATE"
        );
        $activeItems->execute([(string)$user['member_id']]);
        $returnBorrowIds = array_map('intval', $activeItems->fetchAll(PDO::FETCH_COLUMN));
        if (!$returnBorrowIds) {
            $pdo->rollBack();
            verificationResponse(['success' => false, 'message' => 'There are no active items to return. Refresh and scan again.'], 409);
        }
    }
    $existing = $pdo->prepare('SELECT request_id FROM borrow_requests WHERE verification_token_id = ?');
    $existing->execute([$tokenId]);
    if ($existing->fetchColumn()) {
        $pdo->rollBack();
        verificationResponse(['success' => false, 'message' => 'This request has already been submitted.'], 409);
    }
    $updateToken = $pdo->prepare(
        "UPDATE service_verification_tokens
         SET status = 'used', used_at = CURRENT_TIMESTAMP, code_hash = NULL,
             code_expires_at = NULL, code_verified_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
         WHERE id = ? AND status = 'active'"
    );
    $updateToken->execute([$tokenId]);
    if ($updateToken->rowCount() !== 1) {
        $pdo->rollBack();
        verificationResponse(['success' => false, 'message' => 'This verification was already completed.'], 409);
    }
    $insert = $pdo->prepare(
        "INSERT INTO borrow_requests
            (service_type, user_id, member_id, verification_token_id, return_borrow_ids)
         VALUES (?, ?, ?, ?, CAST(? AS bigint[])) RETURNING request_id"
    );
    $insert->execute([
        $token['service_type'],
        (int)$auth['id'],
        $user['member_id'],
        $tokenId,
        '{' . implode(',', $returnBorrowIds) . '}',
    ]);
    $requestId = (int)$insert->fetchColumn();
    verificationAudit($pdo, 'youth_code_confirm', 'success', (int)$tokenId, (string)$token['token_hash'],
        (int)$auth['id'], (int)$token['scanned_by_admin_id'], (string)$token['service_type'], (string)$requestId);
    $pdo->commit();
    verificationResponse([
        'success' => true,
        'message' => 'Your request was verified and is now waiting for staff to process.',
        'request_id' => $requestId,
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[Borrow Verification] Youth code confirmation failed: ' . $exception->getMessage());
    verificationResponse(['success' => false, 'message' => 'Unable to confirm this request right now.'], 500);
}
