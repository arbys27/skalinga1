<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
verificationRequireMethod('POST');
$auth = requireAuth(['youth']);
securityRequireCsrf();
$input = verificationInput();

$serviceType = is_string($input['service_type'] ?? null) ? trim($input['service_type']) : '';
$purpose = is_string($input['purpose'] ?? null) ? trim($input['purpose']) : 'claim';
$serviceRef = is_scalar($input['request_id'] ?? null) ? trim((string)$input['request_id']) : '';
$supported = $serviceType === 'print'
    ? in_array($purpose, ['submit', 'claim'], true) && $serviceRef !== ''
    : in_array($serviceType, ['borrow', 'return'], true) && $purpose === 'claim';
if (!$supported) {
    verificationAudit(
        $pdo,
        'generate',
        'invalid_request',
        null,
        null,
        (int)$auth['id'],
        null,
        strlen($serviceType) <= 50 ? $serviceType : null,
        preg_match('/^[1-9]\d{0,9}$/', $serviceRef) === 1 ? $serviceRef : null
    );
    verificationResponse([
        'success' => false,
        'message' => 'A supported service type and request ID are required.',
    ], 400);
}

$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);

try {
    $pdo->beginTransaction();
    $userQuery = $pdo->prepare(
        "SELECT u.id, u.member_id
         FROM users u
         WHERE u.id = ? AND u.status = 'active'
         LIMIT 1
         FOR UPDATE"
    );
    $userQuery->execute([$auth['id']]);
    $user = $userQuery->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $pdo->rollBack();
        verificationResponse(['success' => false, 'message' => 'An active youth account is required.'], 403);
    }
    if (in_array($serviceType, ['borrow', 'return'], true)) {
        $serviceRef = (string)$user['id'];
    }

    $request = $serviceType === 'print'
        ? verificationServiceRequest($pdo, $serviceType, $serviceRef, (string)$user['member_id'], $purpose, true)
        : verificationBorrowUser($pdo, $serviceType, (string)$user['id'], (int)$auth['id'], true);
    if (!$request) {
        verificationAudit(
            $pdo,
            'generate',
            'request_not_eligible',
            null,
            null,
            (int)$auth['id'],
            null,
            $serviceType,
            $serviceRef
        );
        $pdo->commit();
        verificationResponse([
            'success' => false,
            'message' => $serviceType === 'print' && $purpose === 'submit'
                ? 'This print request is not awaiting verification for your account.'
                : ($serviceType === 'print'
                    ? 'This request is not an eligible, unclaimed print request for your account.'
                    : 'Unable to verify this youth account for the requested service.'),
        ], 404);
    }
    if ($serviceType === 'return') {
        $activeItems = $pdo->prepare(
            "SELECT 1 FROM borrow_records
             WHERE member_id = ? AND status IN ('Borrowed', 'Overdue')
             LIMIT 1"
        );
        $activeItems->execute([(string)$user['member_id']]);
        if (!$activeItems->fetchColumn()) {
            verificationAudit($pdo, 'generate', 'no_active_loans', null, null, (int)$auth['id'], null, $serviceType, $serviceRef);
            $pdo->commit();
            verificationResponse(['success' => false, 'message' => 'You do not have any active items to return.'], 409);
        }
    }

    $rateQuery = $pdo->prepare(
        "SELECT COUNT(*)
         FROM service_verification_tokens
         WHERE user_id = ? AND created_at >= CURRENT_TIMESTAMP - INTERVAL '1 hour'"
    );
    $rateQuery->execute([$auth['id']]);
    if ((int)$rateQuery->fetchColumn() >= 5) {
        verificationAudit($pdo, 'generate', 'rate_limited', null, $tokenHash, (int)$auth['id'], null, $serviceType, $serviceRef);
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'Verification generation limit reached. Try again later.'], 429);
    }

    $settingsQuery = $pdo->prepare(
        'SELECT token_ttl_seconds, otp_required
         FROM service_verification_settings
         WHERE service_type = ?'
    );
    $settingsQuery->execute([$serviceType]);
    $settings = $settingsQuery->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        verificationAudit(
            $pdo,
            'generate',
            'service_not_configured',
            null,
            null,
            (int)$auth['id'],
            null,
            $serviceType,
            $serviceRef
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'Verification is not configured for this service.'], 400);
    }

    $insert = $pdo->prepare(
        'INSERT INTO service_verification_tokens
            (user_id, service_type, service_ref, token_hash, expires_at, otp_required,
             code_hash, code_expires_at)
         VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP + (CAST(? AS integer) * INTERVAL \'1 second\'), ?, NULL, NULL)
         RETURNING id, expires_at'
    );
    $insert->execute([
        (int)$auth['id'],
        $serviceType,
        $serviceRef,
        $tokenHash,
        (int)$settings['token_ttl_seconds'],
        $purpose === 'submit' ? true : postgresBoolean($settings['otp_required']),
    ]);
    $created = $insert->fetch(PDO::FETCH_ASSOC);
    verificationAudit(
        $pdo,
        'generate',
        'success',
        (int)$created['id'],
        $tokenHash,
        (int)$auth['id'],
        null,
        $serviceType,
        $serviceRef
    );
    $pdo->commit();

    verificationResponse([
        'success' => true,
        'token' => $token,
        'token_id' => (int)$created['id'],
        'expires_at' => $created['expires_at'],
        'expires_in' => (int)$settings['token_ttl_seconds'],
        'otp_required' => $purpose === 'submit' ? true : postgresBoolean($settings['otp_required']),
        'purpose' => $purpose,
        'request' => [
            'request_id' => $serviceType === 'print' ? (int)$request['request_id'] : null,
            'member_id' => (string)$request['member_id'],
            'member_name' => (string)($request['member_name'] ?? $request['display_name'] ?? ''),
            'document_title' => $serviceType === 'print' ? (string)$request['document_title'] : null,
        ],
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Service Verification] Token generation failed: ' . $exception->getMessage());
    verificationResponse(['success' => false, 'message' => 'Unable to generate a verification token right now.'], 500);
}
