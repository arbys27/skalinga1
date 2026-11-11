<?php

require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/../includes/sms.php';
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

securityStartSession();
securityRequireCsrf();
$masterlistId = (int)($_SESSION['activation_masterlist_id'] ?? 0);
if ($masterlistId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Verify your masterlist record before requesting a code.']);
    exit;
}

$rawPhone = is_string($_POST['phone'] ?? null) ? $_POST['phone'] : '';
try {
    $phone = normalizePhPhone($rawPhone);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
    exit;
}

$reference = '';
$code = '';
try {
    $record = $pdo->prepare(
        'SELECT id FROM kk_masterlist WHERE id = ? AND is_claimed = FALSE LIMIT 1'
    );
    $record->execute([$masterlistId]);
    if (!$record->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This youth record is no longer available for activation.']);
        exit;
    }

    $exists = $pdo->prepare('SELECT id FROM youth_profiles WHERE phone = ? LIMIT 1');
    $exists->execute([$phone]);
    if ($exists->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Phone number already registered.']);
        exit;
    }

    $phoneHash = hash('sha256', $phone);
    $ip = securityClientIp();
    $usage = $pdo->prepare(
        "SELECT
            COUNT(*) FILTER (WHERE phone_hash = ? AND created_at >= CURRENT_TIMESTAMP - INTERVAL '1 hour') AS phone_hour,
            COUNT(*) FILTER (WHERE phone_hash = ? AND created_at >= CURRENT_TIMESTAMP - INTERVAL '1 day') AS phone_day,
            COUNT(*) FILTER (WHERE ip = ? AND created_at >= CURRENT_TIMESTAMP - INTERVAL '1 hour') AS ip_hour,
            COUNT(*) FILTER (WHERE ip = ? AND created_at >= CURRENT_TIMESTAMP - INTERVAL '1 day') AS ip_day,
            MAX(last_sent_at) FILTER (WHERE phone_hash = ?) AS last_phone_send
         FROM registration_sms_challenges"
    );
    $usage->execute([$phoneHash, $phoneHash, $ip, $ip, $phoneHash]);
    $limits = $usage->fetch(PDO::FETCH_ASSOC) ?: [];
    $lastPhoneSend = !empty($limits['last_phone_send']) ? strtotime((string)$limits['last_phone_send']) : false;
    if ($lastPhoneSend !== false && time() - $lastPhoneSend < 60) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Please wait ' . (60 - max(0, time() - $lastPhoneSend)) . ' seconds before requesting another code.'
        ]);
        exit;
    }
    if ((int)($limits['phone_hour'] ?? 0) >= 3 || (int)($limits['phone_day'] ?? 0) >= 10
        || (int)($limits['ip_hour'] ?? 0) >= 10 || (int)($limits['ip_day'] ?? 0) >= 20) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many verification requests. Please try again later.']);
        exit;
    }

    $pdo->exec(
        "DELETE FROM registration_sms_challenges
         WHERE expires_at < CURRENT_TIMESTAMP OR consumed_at < CURRENT_TIMESTAMP - INTERVAL '1 day'"
    );
    $code = otpGenerateCode();
    $challengeId = loginChallengeUuid();
    $pdo->beginTransaction();
    $pdo->prepare(
        'UPDATE registration_sms_challenges
         SET consumed_at = CURRENT_TIMESTAMP
         WHERE masterlist_id = ? AND phone_hash = ? AND consumed_at IS NULL'
    )->execute([$masterlistId, $phoneHash]);
    $pdo->prepare(
        "INSERT INTO registration_sms_challenges
            (id, masterlist_id, phone, phone_hash, code_hash, expires_at, ip, last_sent_at)
         VALUES (?::uuid, ?, ?, ?, ?, CURRENT_TIMESTAMP + INTERVAL '5 minutes', ?, CURRENT_TIMESTAMP)"
    )->execute([$challengeId, $masterlistId, $phone, $phoneHash, otpCodeHash($code), $ip]);
    $pdo->commit();
    error_log('[Registration SMS] Challenge created; phone=' . otpMaskPhone($phone) . '; masterlist=' . $masterlistId . '.');

    try {
        $delivery = sendOTP(
            $phone,
            'Your SKalinga verification code is ' . $code . '. It expires in 5 minutes.'
        );
    } catch (Throwable $exception) {
        $pdo->prepare('UPDATE registration_sms_challenges SET consumed_at = CURRENT_TIMESTAMP WHERE id = ?::uuid')
            ->execute([$challengeId]);
        unset($_SESSION['registration_sms_challenge_id'], $_SESSION['verified_activation_challenge_id']);
        throw $exception;
    }

    $_SESSION['registration_sms_challenge_id'] = $challengeId;
    unset($_SESSION['verified_activation_challenge_id']);
    error_log('[Registration SMS] Delivery accepted; provider=' . $delivery['provider']
        . '; status=' . $delivery['status'] . '; phone=' . otpMaskPhone($phone) . '.');
    echo json_encode([
        'success' => true,
        'message' => 'A verification code was sent to ' . otpMaskPhone($phone) . '. Check your messages.',
        'phone' => otpMaskPhone($phone),
        'normalizedPhone' => $phone,
        'expiresIn' => 300,
        'resendAfter' => 60
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $reference = securityLogException('Registration SMS Error', $exception, [
        $code,
        $rawPhone,
        (string)(skalingaConfig()['UNISMS_API_KEY'] ?? '')
    ]);
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'We couldn\'t send the code. Please try again. Reference: ' . $reference,
        'reference' => $reference
    ]);
}
