<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
verificationRequireMethod('POST');
$admin = requireAdminApi();
securityRequireCsrf();
$input = verificationInput();
$token = verificationTokenInput($input);
$requestedServiceRef = is_scalar($input['request_id'] ?? null)
    ? trim((string)$input['request_id'])
    : null;
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
    if ($requestedServiceRef !== null && $requestedServiceRef !== (string)$record['service_ref']) {
        verificationAudit(
            $pdo, 'scan', 'request_mismatch', (int)$record['id'], $tokenHash,
            (int)$record['user_id'], (int)$admin['id'],
            (string)$record['service_type'], $requestedServiceRef
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'This QR does not belong to the selected print request.'], 409);
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

    if (in_array((string)$record['service_type'], ['borrow', 'return'], true)) {
        $user = verificationBorrowUser($pdo, (string)$record['service_type'], (string)$record['service_ref'], (int)$record['user_id'], true);
        if (!$user) {
            $pdo->rollBack();
            verificationResponse(['success' => false, 'message' => 'The youth account is no longer eligible.'], 409);
        }
        $active = [];
        if ((string)$record['service_type'] === 'return') {
            $items = $pdo->prepare(
                "SELECT br.borrow_id, br.item_id, br.quantity, br.due_date, br.status, r.name AS item_name
                 FROM borrow_records br
                 JOIN resources r ON r.item_id = br.item_id
                 WHERE br.member_id = ? AND br.status IN ('Borrowed', 'Overdue')
                 ORDER BY br.due_date, br.borrow_id
                 FOR UPDATE OF br"
            );
            $items->execute([(string)$user['member_id']]);
            $active = $items->fetchAll(PDO::FETCH_ASSOC);
            if (!$active) {
                $pdo->rollBack();
                verificationResponse(['success' => false, 'message' => 'This youth has no active borrowed items.'], 409);
            }
        }
        $markScanned = $pdo->prepare(
            'UPDATE service_verification_tokens
             SET scanned_by_admin_id = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND status = \'active\''
        );
        $markScanned->execute([(int)$admin['id'], (int)$record['id']]);
        verificationAudit($pdo, 'scan', 'success', (int)$record['id'], $tokenHash, (int)$record['user_id'],
            (int)$admin['id'], (string)$record['service_type'], (string)$record['service_ref']);
        $pdo->commit();
        verificationResponse([
            'success' => true,
            'verification' => [
                'service' => (string)$record['service_type'] === 'borrow' ? 'Borrow resources' : 'Return resources',
                'youth_name' => (string)$user['display_name'],
                'member_id' => (string)$user['member_id'],
                'items' => $active,
                'verification_code_required' => true,
                'code_expires_in' => 180,
            ],
        ]);
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
         WHERE pr.request_id = ? AND pr.status IN ('Completed', 'Pending', 'AwaitingVerification')
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

    $verificationCode = null;
    if ((string)$record['service_type'] === 'print'
        && in_array((string)$request['status'], ['Pending', 'AwaitingVerification'], true)) {
        if (is_string($record['code_hash'] ?? null)
            && $record['code_hash'] !== ''
            && is_string($record['code_expires_at'] ?? null)
            && !verificationIsExpired($record['code_expires_at'])) {
            $pdo->rollBack();
            verificationResponse([
                'success' => false,
                'message' => 'A verification code is already active for this request. Ask the youth to enter it.',
            ], 409);
        }
        $verificationCode = str_pad((string)random_int(0, 99), 2, '0', STR_PAD_LEFT);
        $markScanned = $pdo->prepare(
            "UPDATE service_verification_tokens
             SET scanned_by_admin_id = ?, code_hash = ?, code_expires_at = CURRENT_TIMESTAMP + INTERVAL '5 minutes',
                 code_attempts = 0, code_verified_at = NULL, updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND status = 'active'"
        );
        $markScanned->execute([
            (int)$admin['id'],
            otpCodeHash($verificationCode),
            (int)$record['id'],
        ]);
    } else {
        $markScanned = $pdo->prepare(
            'UPDATE service_verification_tokens
             SET scanned_by_admin_id = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $markScanned->execute([(int)$admin['id'], (int)$record['id']]);
    }
    if ($markScanned->rowCount() !== 1) {
        $pdo->rollBack();
        verificationResponse(['success' => false, 'message' => 'This verification token is no longer active.'], 409);
    }
    if ($verificationCode !== null) {
        $linkRequest = $pdo->prepare(
            'UPDATE printing_requests
             SET print_verification_token_id = ?, updated_at = CURRENT_TIMESTAMP
             WHERE request_id = ? AND status IN (\'Pending\', \'AwaitingVerification\')'
        );
        $linkRequest->execute([(int)$record['id'], (int)$record['service_ref']]);
    }
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
            'code_expires_in' => $verificationCode !== null ? 300 : null,
            'verification_code' => $verificationCode,
        ],
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Service Verification] Token scan failed: ' . $exception->getMessage());
    verificationResponse(['success' => false, 'message' => 'Unable to validate this verification right now.'], 500);
}
