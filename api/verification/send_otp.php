<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once dirname(__DIR__, 2) . '/includes/mail.php';

verificationRequireMethod('POST');
$admin = requireAdminApi();
securityRequireCsrf();
$input = verificationInput();
$token = verificationTokenInput($input);
if ($token === null) {
    verificationResponse(['success' => false, 'message' => 'A valid verification token is required.'], 400);
}

$tokenHash = hash('sha256', $token);

try {
    $pdo->beginTransaction();
    $record = verificationLoadToken($pdo, $tokenHash, true);
    if (!$record || $record['status'] !== 'active' || verificationIsExpired((string)$record['expires_at'])) {
        verificationAudit(
            $pdo,
            'send_otp',
            'not_active',
            $record ? (int)$record['id'] : null,
            $tokenHash,
            $record ? (int)$record['user_id'] : null,
            (int)$admin['id']
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'This verification token is invalid or expired.'], 409);
    }

    if ((int)($record['scanned_by_admin_id'] ?? 0) !== (int)$admin['id']) {
        $pdo->rollBack();
        verificationResponse(['success' => false, 'message' => 'Validate this token with your staff account first.'], 409);
    }

    if (!postgresBoolean($record['otp_required'])) {
        $pdo->rollBack();
        verificationResponse(['success' => false, 'message' => 'Email confirmation is not required for this token.'], 400);
    }

    if (is_string($record['otp_expires_at'] ?? null)
        && $record['otp_expires_at'] !== ''
        && !verificationIsExpired($record['otp_expires_at'])) {
        $pdo->rollBack();
        verificationResponse(['success' => false, 'message' => 'A confirmation code was already sent.'], 429);
    }

    $recipient = $pdo->prepare(
        "SELECT u.email,
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', yp.firstname, yp.lastname)), ''), u.member_id) AS name
         FROM users u
         LEFT JOIN youth_profiles yp ON yp.user_id = u.id
         WHERE u.id = ? AND u.status = 'active'
         LIMIT 1"
    );
    $recipient->execute([(int)$record['user_id']]);
    $user = $recipient->fetch(PDO::FETCH_ASSOC);
    if (!$user || !filter_var((string)$user['email'], FILTER_VALIDATE_EMAIL)) {
        verificationAudit(
            $pdo,
            'send_otp',
            'recipient_unavailable',
            (int)$record['id'],
            $tokenHash,
            (int)$record['user_id'],
            (int)$admin['id']
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'The youth account has no valid email address.'], 422);
    }

    $code = otpGenerateCode();
    $update = $pdo->prepare(
        "UPDATE service_verification_tokens
         SET otp_hash = ?, otp_expires_at = CURRENT_TIMESTAMP + INTERVAL '5 minutes',
             otp_attempts = 0, updated_at = CURRENT_TIMESTAMP
         WHERE id = ? AND status = 'active'"
    );
    $update->execute([otpCodeHash($code), (int)$record['id']]);
    $pdo->commit();

    sendLoginOtpEmail((string)$user['email'], (string)$user['name'], $code, 5, $pdo);
    verificationAudit(
        $pdo,
        'send_otp',
        'success',
        (int)$record['id'],
        $tokenHash,
        (int)$record['user_id'],
        (int)$admin['id'],
        (string)$record['service_type'],
        (string)$record['service_ref']
    );
    verificationResponse(['success' => true, 'message' => 'A confirmation code was sent to the youth email address.']);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Service Verification] Email OTP failed: ' . $exception->getMessage());
    verificationResponse(['success' => false, 'message' => 'Unable to send the confirmation code right now.'], 500);
}
