<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
verificationRequireMethod('POST');
$admin = requireAdminApi();
securityRequireCsrf();
$input = verificationInput();
$token = verificationTokenInput($input);
if ($token === null) {
    verificationAudit($pdo, 'reject', 'invalid_token_format', null, null, null, (int)$admin['id']);
    verificationResponse(['success' => false, 'message' => 'A valid verification token is required.'], 400);
}
$tokenHash = hash('sha256', $token);
$reason = is_string($input['reason'] ?? null) ? trim($input['reason']) : '';
if (strlen($reason) > 500) {
    verificationResponse(['success' => false, 'message' => 'The rejection reason is too long.'], 400);
}

try {
    $pdo->beginTransaction();
    $record = verificationLoadToken($pdo, $tokenHash, true);
    if ($record && $record['status'] === 'active' && verificationIsExpired((string)$record['expires_at'])) {
        $expire = $pdo->prepare(
            "UPDATE service_verification_tokens
             SET status = 'expired', otp_hash = NULL, otp_expires_at = NULL, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $expire->execute([(int)$record['id']]);
        verificationAudit(
            $pdo, 'reject', 'expired', (int)$record['id'], $tokenHash,
            (int)$record['user_id'], (int)$admin['id'],
            (string)$record['service_type'], (string)$record['service_ref']
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'This verification has expired.'], 410);
    }

    if (!$record || $record['status'] !== 'active') {
        verificationAudit(
            $pdo, 'reject', 'not_active', $record ? (int)$record['id'] : null, $tokenHash,
            $record ? (int)$record['user_id'] : null, (int)$admin['id'],
            $record['service_type'] ?? null, $record['service_ref'] ?? null
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'This verification is invalid or no longer active.'], 409);
    }

    if ((int)($record['scanned_by_admin_id'] ?? 0) !== (int)$admin['id']) {
        verificationAudit(
            $pdo, 'reject', 'scan_required', (int)$record['id'], $tokenHash,
            (int)$record['user_id'], (int)$admin['id'],
            (string)$record['service_type'], (string)$record['service_ref']
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'Validate this token with your staff account before rejecting.'], 409);
    }

    if ((string)$record['service_type'] !== 'print') {
        $pdo->rollBack();
        verificationResponse(['success' => false, 'message' => 'This service type is not currently supported.'], 400);
    }
    $requestQuery = $pdo->prepare(
        "SELECT pr.request_id
         FROM printing_requests pr
         JOIN users u ON u.member_id = pr.member_id AND u.id = ?
         WHERE pr.request_id = ? AND pr.status IN ('Completed', 'AwaitingVerification')
         LIMIT 1
         FOR UPDATE OF pr"
    );
    $requestQuery->execute([(int)$record['user_id'], (int)$record['service_ref']]);
    if (!$requestQuery->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();
        verificationResponse(['success' => false, 'message' => 'The linked service request is no longer available.'], 409);
    }

    $rejectRequest = $pdo->prepare(
        "UPDATE printing_requests
         SET status = 'Rejected',
             notes = CASE WHEN NULLIF(notes, '') IS NULL THEN ? ELSE notes || E'\\n' || ? END,
             updated_at = CURRENT_TIMESTAMP
         WHERE request_id = ? AND status IN ('Completed', 'AwaitingVerification')"
    );
    $rejectionNote = 'Verification rejected' . ($reason !== '' ? ': ' . $reason : '.');
    $rejectRequest->execute([$rejectionNote, $rejectionNote, (int)$record['service_ref']]);
    $cancel = $pdo->prepare(
        "UPDATE service_verification_tokens
         SET status = 'cancelled', otp_hash = NULL, otp_expires_at = NULL,
             scanned_by_admin_id = ?, updated_at = CURRENT_TIMESTAMP
         WHERE id = ? AND status = 'active'"
    );
    $cancel->execute([(int)$admin['id'], (int)$record['id']]);
    verificationAudit(
        $pdo, 'reject', 'success', (int)$record['id'], $tokenHash, (int)$record['user_id'],
        (int)$admin['id'], (string)$record['service_type'], (string)$record['service_ref']
    );
    $pdo->prepare(
        'INSERT INTO activity_logs (user_id, role, action, identifier, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([
        (int)$admin['id'],
        $admin['role'],
        'service_verification_rejected',
        'print:' . (string)$record['service_ref'],
        securityClientIp(),
    ]);
    $pdo->commit();
    verificationResponse(['success' => true, 'message' => 'Print request rejected and verification token cancelled.']);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Service Verification] Rejection failed: ' . $exception->getMessage());
    verificationResponse(['success' => false, 'message' => 'Unable to reject this verification right now.'], 500);
}
