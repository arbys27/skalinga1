<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/security_helpers.php';

header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

securityStartSession();
securityRequireCsrf();
$phone = isset($_POST['phone']) && is_string($_POST['phone']) ? trim($_POST['phone']) : '';
if (!preg_match('/^09\d{9}$/', $phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Enter a valid 11-digit mobile number.']);
    exit;
}

try {
    $exists = $pdo->prepare('SELECT id FROM youth_profiles WHERE phone = ? LIMIT 1');
    $exists->execute([$phone]);
    if ($exists->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Phone number already registered.']);
        exit;
    }

    otpReserveSend($pdo, 'registration', $phone);
    $code = otpGenerateCode();
    otpSendSms($phone, $code, 'registration');
    $_SESSION['registration_otp_hash'] = otpCodeHash($code);
    $_SESSION['registration_phone'] = $phone;
    $_SESSION['registration_otp_expires_at'] = time() + 300;
    $_SESSION['registration_otp_attempts'] = 0;
    $_SESSION['phone_verified'] = false;
    unset($_SESSION['verified_phone']);

    echo json_encode([
        'success' => true,
        'message' => 'A verification code was sent to ' . otpMaskPhone($phone) . '.',
        'phone' => otpMaskPhone($phone)
    ]);
} catch (RuntimeException $exception) {
    $limited = strpos($exception->getMessage(), 'rate limited') !== false;
    http_response_code($limited ? 429 : 503);
    error_log('[Registration OTP] Delivery failed: ' . $exception->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $limited
            ? 'OTP sending is temporarily rate limited. Try again later.'
            : 'Unable to send the verification code right now.'
    ]);
} catch (Throwable $exception) {
    error_log('[Registration OTP] Request failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to process the verification request.']);
}
