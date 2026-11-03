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
$code = isset($_POST['otp']) && is_string($_POST['otp']) ? trim($_POST['otp']) : '';
if (!preg_match('/^\d{6}$/', $code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Enter the six-digit login code.']);
    exit;
}

$storedHash = $_SESSION['login_otp_hash'] ?? null;
$userId = (int)($_SESSION['login_user_id'] ?? 0);
$expiresAt = (int)($_SESSION['login_otp_expires_at'] ?? 0);
$attempts = (int)($_SESSION['login_otp_attempts'] ?? 0);
if (!is_string($storedHash) || $userId < 1 || time() >= $expiresAt || $attempts >= 5) {
    unset($_SESSION['login_otp_hash'], $_SESSION['login_user_id'], $_SESSION['login_otp_expires_at'], $_SESSION['login_otp_attempts']);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Login code is invalid or expired. Request a new one.']);
    exit;
}

if (!hash_equals($storedHash, otpCodeHash($code))) {
    $_SESSION['login_otp_attempts'] = $attempts + 1;
    if ($_SESSION['login_otp_attempts'] >= 5) {
        unset($_SESSION['login_otp_hash'], $_SESSION['login_user_id'], $_SESSION['login_otp_expires_at'], $_SESSION['login_otp_attempts']);
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired login code.']);
    exit;
}

try {
    $userId = (int)$_SESSION['login_user_id'];
    $memberId = (string)($_SESSION['login_member_id'] ?? '');
    $phone = (string)($_SESSION['login_phone'] ?? '');
    $trustDevice = !empty($_SESSION['login_trust_device']);
    $update = $trustDevice
        ? $pdo->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP, last_otp_verified_at = CURRENT_TIMESTAMP WHERE id = ?')
        : $pdo->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?');
    $update->execute([$userId]);

    session_regenerate_id(true);
    unset(
        $_SESSION['login_otp_hash'],
        $_SESSION['login_phone'],
        $_SESSION['login_user_id'],
        $_SESSION['login_member_id'],
        $_SESSION['login_firstname'],
        $_SESSION['login_lastname'],
        $_SESSION['login_trust_device'],
        $_SESSION['login_otp_expires_at'],
        $_SESSION['login_otp_attempts']
    );
    $_SESSION['user_id'] = $userId;
    $_SESSION['member_id'] = $memberId;
    $_SESSION['phone'] = $phone;
    $_SESSION['authenticated'] = true;

    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'member_id' => $memberId,
        'redirect' => 'youth-portal.html'
    ]);
} catch (Throwable $exception) {
    error_log('[Login OTP] Completion failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to complete login right now.']);
}
