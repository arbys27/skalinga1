<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/verification/common.php';

verificationRequireMethod('POST');
$auth = requireAuth(['youth']);
securityRequireCsrf();
$input = verificationInput();
$requestId = filter_var($input['request_id'] ?? null, FILTER_VALIDATE_INT);
$tokenId = filter_var($input['token_id'] ?? null, FILTER_VALIDATE_INT);
$code = is_string($input['code'] ?? null) ? trim($input['code']) : '';
if (!$requestId || $requestId < 1 || !$tokenId || $tokenId < 1 || preg_match('/^\d{2}$/', $code) !== 1) {
    verificationResponse(['success' => false, 'message' => 'A request, token, and two-digit code are required.'], 400);
}

try {
    $pdo->beginTransaction();
    $query = $pdo->prepare(
        "SELECT svt.id, svt.token_hash, svt.user_id, svt.service_type, svt.service_ref,
                svt.expires_at,
                svt.status, svt.scanned_by_admin_id, svt.code_hash, svt.code_expires_at,
                svt.code_attempts
         FROM service_verification_tokens svt
         JOIN printing_requests pr
           ON pr.request_id = CAST(svt.service_ref AS integer)
          AND pr.print_verification_token_id = svt.id
         WHERE svt.id = ? AND svt.user_id = ? AND svt.service_type = 'print'
           AND svt.service_ref = ? AND pr.status IN ('Pending', 'AwaitingVerification')
         LIMIT 1
         FOR UPDATE"
    );
    $query->execute([$tokenId, (int)$auth['id'], (string)$requestId]);
    $record = $query->fetch(PDO::FETCH_ASSOC);
    if (!$record || $record['status'] !== 'active') {
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        verificationResponse(['success' => false, 'message' => 'This verification is no longer active.'], 409);
    }

    $valid = is_string($record['code_hash'])
        && $record['code_hash'] !== ''
        && is_string($record['code_expires_at'])
        && !verificationIsExpired($record['code_expires_at'])
        && !verificationIsExpired((string)$record['expires_at'])
        && (int)$record['code_attempts'] < 5
        && hash_equals($record['code_hash'], otpCodeHash($code));
    if (!$valid) {
        $attempts = min(5, (int)$record['code_attempts'] + 1);
        $failed = $pdo->prepare(
            "UPDATE service_verification_tokens
             SET code_attempts = ?, code_hash = CASE WHEN ? >= 5 THEN NULL ELSE code_hash END,
                 code_expires_at = CASE WHEN ? >= 5 THEN NULL ELSE code_expires_at END,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $failed->execute([$attempts, $attempts, $attempts, (int)$record['id']]);
        verificationAudit(
            $pdo, 'print_code_confirm', 'code_failed', (int)$record['id'], (string)$record['token_hash'],
            (int)$auth['id'], (int)$record['scanned_by_admin_id'], 'print', (string)$requestId
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'The code is incorrect, expired, or locked.'], 400);
    }

    $updateToken = $pdo->prepare(
        "UPDATE service_verification_tokens
         SET status = 'used', used_at = CURRENT_TIMESTAMP, code_hash = NULL,
             code_expires_at = NULL, code_verified_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
         WHERE id = ? AND status = 'active'"
    );
    $updateToken->execute([(int)$record['id']]);
    $updateRequest = $pdo->prepare(
        "UPDATE printing_requests
         SET print_verified_at = CURRENT_TIMESTAMP, print_verified_by_admin_id = ?,
             verified_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
         WHERE request_id = ? AND status IN ('Pending', 'AwaitingVerification')"
    );
    $updateRequest->execute([(int)$record['scanned_by_admin_id'], $requestId]);
    if ($updateToken->rowCount() !== 1 || $updateRequest->rowCount() !== 1) {
        $pdo->rollBack();
        verificationResponse(['success' => false, 'message' => 'This print request changed. Please scan again.'], 409);
    }

    verificationAudit(
        $pdo, 'print_code_confirm', 'success', (int)$record['id'], (string)$record['token_hash'],
        (int)$auth['id'], (int)$record['scanned_by_admin_id'], 'print', (string)$requestId
    );
    $pdo->prepare(
        'INSERT INTO activity_logs (user_id, role, action, identifier, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([
        (int)$auth['id'],
        'youth',
        'print_verification_completed',
        'print:' . (string)$requestId,
        securityClientIp(),
    ]);
    $pdo->commit();
    verificationResponse(['success' => true, 'message' => 'Print request verified successfully.']);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Print Verification] Code confirmation failed: ' . $exception->getMessage());
    verificationResponse(['success' => false, 'message' => 'Unable to confirm the print request right now.'], 500);
}
