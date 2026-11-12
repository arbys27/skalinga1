<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
verificationRequireMethod('POST');
$admin = requireAdminApi();
securityRequireCsrf();
$input = verificationInput();
$token = verificationTokenInput($input);
$otp = is_string($input['otp'] ?? null) ? trim($input['otp']) : '';
if ($token === null) {
    verificationAudit($pdo, 'confirm', 'invalid_token_format', null, null, null, (int)$admin['id']);
    verificationResponse(['success' => false, 'message' => 'A valid verification token is required.'], 400);
}
$tokenHash = hash('sha256', $token);

try {
    $pdo->beginTransaction();
    $record = verificationLoadToken($pdo, $tokenHash, true);
    if (!$record || $record['status'] !== 'active') {
        verificationAudit(
            $pdo, 'confirm', 'not_active', $record ? (int)$record['id'] : null, $tokenHash,
            $record ? (int)$record['user_id'] : null, (int)$admin['id'],
            $record['service_type'] ?? null, $record['service_ref'] ?? null
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'This verification token is invalid or no longer active.'], 409);
    }

    if (verificationIsExpired((string)$record['expires_at'])) {
        $expire = $pdo->prepare(
            "UPDATE service_verification_tokens SET status = 'expired', otp_hash = NULL, otp_expires_at = NULL,
                updated_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        $expire->execute([(int)$record['id']]);
        verificationAudit(
            $pdo, 'confirm', 'expired', (int)$record['id'], $tokenHash, (int)$record['user_id'],
            (int)$admin['id'], (string)$record['service_type'], (string)$record['service_ref']
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'This verification token has expired.'], 410);
    }

    if ((int)($record['scanned_by_admin_id'] ?? 0) !== (int)$admin['id']) {
        verificationAudit(
            $pdo, 'confirm', 'scan_required', (int)$record['id'], $tokenHash,
            (int)$record['user_id'], (int)$admin['id'],
            (string)$record['service_type'], (string)$record['service_ref']
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'Validate this token with your staff account before confirming.'], 409);
    }

    if (postgresBoolean($record['otp_required'])) {
        $otpValid = preg_match('/^\d{6}$/', $otp) === 1
            && is_string($record['otp_hash'])
            && $record['otp_hash'] !== ''
            && is_string($record['otp_expires_at'])
            && !verificationIsExpired((string)$record['otp_expires_at'])
            && (int)$record['otp_attempts'] < 5
            && hash_equals((string)$record['otp_hash'], otpCodeHash($otp));
        if (!$otpValid) {
            $attempts = min(5, (int)$record['otp_attempts'] + 1);
            $wrongOtp = $pdo->prepare(
                'UPDATE service_verification_tokens
                 SET otp_attempts = ?, otp_hash = CASE WHEN ? >= 5 THEN NULL ELSE otp_hash END,
                     otp_expires_at = CASE WHEN ? >= 5 THEN NULL ELSE otp_expires_at END,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $wrongOtp->execute([$attempts, $attempts, $attempts, (int)$record['id']]);
            verificationAudit(
                $pdo, 'confirm', 'otp_failed', (int)$record['id'], $tokenHash, (int)$record['user_id'],
                (int)$admin['id'], (string)$record['service_type'], (string)$record['service_ref']
            );
            $pdo->commit();
            verificationResponse(['success' => false, 'message' => 'The OTP is missing, incorrect, expired, or locked.'], 400);
        }
    }

    if ((string)$record['service_type'] !== 'print') {
        $pdo->rollBack();
        verificationResponse(['success' => false, 'message' => 'This service type is not currently supported.'], 400);
    }
    $requestQuery = $pdo->prepare(
        "SELECT pr.request_id, pr.status
         FROM printing_requests pr
         JOIN users u ON u.member_id = pr.member_id AND u.id = ?
         WHERE pr.request_id = ? AND pr.status IN ('Completed', 'AwaitingVerification')
         LIMIT 1
         FOR UPDATE OF pr"
    );
    $requestQuery->execute([(int)$record['user_id'], (int)$record['service_ref']]);
    $requestStatus = $requestQuery->fetch(PDO::FETCH_ASSOC);
    if (!$requestStatus) {
        $cancel = $pdo->prepare(
            "UPDATE service_verification_tokens
             SET status = 'cancelled', otp_hash = NULL, otp_expires_at = NULL, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $cancel->execute([(int)$record['id']]);
        verificationAudit(
            $pdo, 'confirm', 'service_unavailable', (int)$record['id'], $tokenHash,
            (int)$record['user_id'], (int)$admin['id'],
            (string)$record['service_type'], (string)$record['service_ref']
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'The linked service request is no longer available.'], 409);
    }

    if (!is_string($requestStatus['status'] ?? null)) {
        $pdo->rollBack();
        verificationResponse(['success' => false, 'message' => 'The linked service request changed. Scan again.'], 409);
    }
    $isSubmit = $requestStatus['status'] === 'AwaitingVerification';

    $update = $isSubmit
        ? $pdo->prepare(
            "UPDATE printing_requests
             SET status = 'Pending', verified_at = CURRENT_TIMESTAMP, verified_by_admin_id = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE request_id = ? AND status = 'AwaitingVerification'"
        )
        : $pdo->prepare(
            "UPDATE printing_requests
             SET status = 'Claimed', claimed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE request_id = ? AND status = 'Completed'"
        );
    $update->execute($isSubmit
        ? [(int)$admin['id'], (int)$record['service_ref']]
        : [(int)$record['service_ref']]);
    if ($update->rowCount() !== 1) {
        $pdo->rollBack();
        verificationResponse(['success' => false, 'message' => 'The linked service request changed. Scan again.'], 409);
    }

    $use = $pdo->prepare(
        "UPDATE service_verification_tokens
         SET status = 'used', used_at = CURRENT_TIMESTAMP, otp_hash = NULL, otp_expires_at = NULL,
             scanned_by_admin_id = ?, updated_at = CURRENT_TIMESTAMP
         WHERE id = ? AND status = 'active'"
    );
    $use->execute([(int)$admin['id'], (int)$record['id']]);
    if ($use->rowCount() !== 1) {
        $pdo->rollBack();
        verificationResponse(['success' => false, 'message' => 'This verification was already completed.'], 409);
    }

    verificationAudit(
        $pdo, 'confirm', 'success', (int)$record['id'], $tokenHash, (int)$record['user_id'],
        (int)$admin['id'], (string)$record['service_type'], (string)$record['service_ref']
    );
    $activity = $pdo->prepare(
        'INSERT INTO activity_logs (user_id, role, action, identifier, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    );
    $activity->execute([
        (int)$admin['id'],
        $admin['role'],
        'service_verification_completed',
        'print:' . (string)$record['service_ref'],
        securityClientIp(),
    ]);
    $pdo->commit();
    verificationResponse([
        'success' => true,
        'message' => $isSubmit
            ? 'Print request verification confirmed.'
            : 'Print request claim confirmed.',
        'status' => $isSubmit ? 'Pending' : 'Claimed',
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Service Verification] Confirmation failed: ' . $exception->getMessage());
    verificationResponse(['success' => false, 'message' => 'Unable to complete this verification right now.'], 500);
}
