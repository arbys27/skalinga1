<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
verificationRequireMethod('POST');
$admin = requireAdminApi();
securityRequireCsrf();
$input = verificationInput();
$token = verificationTokenInput($input);
$tokenHash = hash('sha256', $token ?? (is_string($input['token'] ?? null) ? trim($input['token']) : 'invalid'));

try {
    $pdo->beginTransaction();
    $record = $token ? verificationLoadToken($pdo, $tokenHash, true) : null;
    if (!$record) {
        verificationAudit($pdo, 'scan', 'invalid_token', null, $tokenHash, null, (int)$admin['id']);
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'This verification token is invalid or no longer active.'], 404);
    }

    if ($record['status'] !== 'active') {
        verificationAudit(
            $pdo, 'scan', 'not_active', (int)$record['id'], $tokenHash, (int)$record['user_id'],
            (int)$admin['id'], (string)$record['service_type'], (string)$record['service_ref']
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'This verification token is invalid or no longer active.'], 409);
    }

    if (verificationIsExpired((string)$record['expires_at'])) {
        $expire = $pdo->prepare(
            "UPDATE service_verification_tokens
             SET status = 'expired', otp_hash = NULL, otp_expires_at = NULL, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $expire->execute([(int)$record['id']]);
        verificationAudit(
            $pdo, 'scan', 'expired', (int)$record['id'], $tokenHash, (int)$record['user_id'],
            (int)$admin['id'], (string)$record['service_type'], (string)$record['service_ref']
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'This verification token has expired.'], 410);
    }

    $requestQuery = $pdo->prepare(
        "SELECT pr.request_id, pr.member_id, pr.document_title, pr.status,
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', NULLIF(yp.firstname, ''), NULLIF(LEFT(yp.lastname, 1) || '.', ''))), ''),
                         NULLIF(TRIM(CONCAT_WS(' ', NULLIF(km.first_name, ''), NULLIF(LEFT(km.last_name, 1) || '.', ''))), ''),
                         'Youth member') AS display_name
         FROM printing_requests pr
         JOIN users u ON u.member_id = pr.member_id AND u.id = ?
         LEFT JOIN youth_profiles yp ON yp.user_id = u.id
         LEFT JOIN kk_masterlist km ON km.id = u.masterlist_id
         WHERE pr.request_id = ? AND pr.status IN ('Completed', 'AwaitingVerification')
         LIMIT 1
         FOR UPDATE OF pr"
    );
    $requestQuery->execute([(int)$record['user_id'], (int)$record['service_ref']]);
    $request = $requestQuery->fetch(PDO::FETCH_ASSOC);
    if (!$request || (string)$record['service_type'] !== 'print') {
        $cancel = $pdo->prepare(
            "UPDATE service_verification_tokens
             SET status = 'cancelled', otp_hash = NULL, otp_expires_at = NULL, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $cancel->execute([(int)$record['id']]);
        verificationAudit(
            $pdo, 'scan', 'service_unavailable', (int)$record['id'], $tokenHash, (int)$record['user_id'],
            (int)$admin['id'], (string)$record['service_type'], (string)$record['service_ref']
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'The linked service request is no longer available.'], 409);
    }

    $markScanned = $pdo->prepare(
        'UPDATE service_verification_tokens
         SET scanned_by_admin_id = ?, updated_at = CURRENT_TIMESTAMP
         WHERE id = ?'
    );
    $markScanned->execute([(int)$admin['id'], (int)$record['id']]);
    verificationAudit(
        $pdo, 'scan', 'success', (int)$record['id'], $tokenHash, (int)$record['user_id'],
        (int)$admin['id'], (string)$record['service_type'], (string)$record['service_ref']
    );
    $pdo->commit();

    verificationResponse([
        'success' => true,
        'verification' => [
            'otp_required' => postgresBoolean($record['otp_required']),
            'youth_name' => $request['display_name'],
            'status' => $request['status'],
            'service' => verificationServiceLabel((string)$record['service_type'], $request),
        ],
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Service Verification] Token scan failed: ' . $exception->getMessage());
    verificationResponse(['success' => false, 'message' => 'Unable to validate this verification right now.'], 500);
}
