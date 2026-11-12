<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
verificationRequireMethod('POST');
$auth = requireAuth(['youth']);
securityRequireCsrf();
$input = verificationInput();
$tokenId = filter_var($input['token_id'] ?? null, FILTER_VALIDATE_INT);
$cancelRequest = filter_var($input['cancel_request'] ?? false, FILTER_VALIDATE_BOOLEAN);
if (!$tokenId || $tokenId < 1) {
    verificationAudit($pdo, 'cancel', 'invalid_token_id', null, null, (int)$auth['id']);
    verificationResponse(['success' => false, 'message' => 'A valid token ID is required.'], 400);
}

try {
    $pdo->beginTransaction();
    $query = $pdo->prepare(
        "SELECT id, token_hash, service_type, service_ref, status, expires_at
         FROM service_verification_tokens
         WHERE id = ? AND user_id = ?
         LIMIT 1
         FOR UPDATE"
    );
    $query->execute([$tokenId, $auth['id']]);
    $token = $query->fetch(PDO::FETCH_ASSOC);
    if (!$token || $token['status'] !== 'active' || verificationIsExpired((string)$token['expires_at'])) {
        if ($token && $token['status'] === 'active') {
            $expire = $pdo->prepare(
                "UPDATE service_verification_tokens
                 SET status = 'expired', otp_hash = NULL, otp_expires_at = NULL, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );
            $expire->execute([$tokenId]);
        }
        verificationAudit(
            $pdo,
            'cancel',
            $token ? 'not_active' : 'not_found',
            $token ? (int)$token['id'] : null,
            $token ? (string)$token['token_hash'] : null,
            (int)$auth['id'],
            null,
            $token['service_type'] ?? null,
            $token['service_ref'] ?? null
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'This active verification could not be cancelled.'], 409);
    }

    $update = $pdo->prepare(
        "UPDATE service_verification_tokens
         SET status = 'cancelled', otp_hash = NULL, otp_expires_at = NULL, updated_at = CURRENT_TIMESTAMP
         WHERE id = ?"
    );
    $update->execute([$tokenId]);
    if ($cancelRequest && (string)$token['service_type'] === 'print') {
        $requestUpdate = $pdo->prepare(
            "UPDATE printing_requests
             SET status = 'Rejected',
                 notes = CASE WHEN NULLIF(notes, '') IS NULL
                     THEN 'Cancelled by youth before verification.'
                     ELSE notes || E'\\nCancelled by youth before verification.'
                 END,
                 updated_at = CURRENT_TIMESTAMP
             WHERE request_id = ? AND status = 'AwaitingVerification'"
        );
        $requestUpdate->execute([(int)$token['service_ref']]);
        if ($requestUpdate->rowCount() === 1) {
            $pdo->prepare(
                'INSERT INTO activity_logs (user_id, role, action, identifier, ip_address)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([
                (int)$auth['id'],
                'youth',
                'print_request_cancelled',
                'print:' . (string)$token['service_ref'],
                securityClientIp(),
            ]);
        }
    }
    verificationAudit(
        $pdo,
        'cancel',
        'success',
        (int)$token['id'],
        (string)$token['token_hash'],
        (int)$auth['id'],
        null,
        (string)$token['service_type'],
        (string)$token['service_ref']
    );
    $pdo->commit();
    verificationResponse(['success' => true, 'message' => 'Verification token cancelled.']);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Service Verification] Token cancellation failed: ' . $exception->getMessage());
    verificationResponse(['success' => false, 'message' => 'Unable to cancel the verification token.'], 500);
}
