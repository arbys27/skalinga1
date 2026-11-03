<?php
require_once __DIR__ . '/security_helpers.php';

header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

securityStartSession();
securityRequireCsrf();
$code = isset($_POST['otp']) && is_string($_POST['otp']) ? trim($_POST['otp']) : '';
if (!preg_match('/^\d{6}$/', $code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Enter the six-digit verification code.']);
    exit;
}

$storedHash = $_SESSION['registration_otp_hash'] ?? null;
$phone = $_SESSION['registration_phone'] ?? null;
$expiresAt = (int)($_SESSION['registration_otp_expires_at'] ?? 0);
$attempts = (int)($_SESSION['registration_otp_attempts'] ?? 0);
if (!is_string($storedHash) || !is_string($phone) || time() >= $expiresAt || $attempts >= 5) {
    unset(
        $_SESSION['registration_otp_hash'],
        $_SESSION['registration_phone'],
        $_SESSION['registration_otp_expires_at'],
        $_SESSION['registration_otp_attempts']
    );
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Code is invalid or expired. Request a new one.']);
    exit;
}

$candidateHash = otpCodeHash($code);
if (!hash_equals($storedHash, $candidateHash)) {
    $_SESSION['registration_otp_attempts'] = $attempts + 1;
    if ($_SESSION['registration_otp_attempts'] >= 5) {
        unset(
            $_SESSION['registration_otp_hash'],
            $_SESSION['registration_phone'],
            $_SESSION['registration_otp_expires_at'],
            $_SESSION['registration_otp_attempts']
        );
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code.']);
    exit;
}

$_SESSION['phone_verified'] = true;
$_SESSION['verified_phone'] = $phone;
unset(
    $_SESSION['registration_otp_hash'],
    $_SESSION['registration_phone'],
    $_SESSION['registration_otp_expires_at'],
    $_SESSION['registration_otp_attempts']
);

echo json_encode([
    'success' => true,
    'message' => 'Phone number verified successfully.',
    'phone' => otpMaskPhone($phone)
]);
