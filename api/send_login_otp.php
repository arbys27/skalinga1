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
$phoneInput = isset($_POST['phone']) && is_string($_POST['phone']) ? trim($_POST['phone']) : '';
$password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
$trustDevice = ($_POST['trust_device'] ?? '') === '1';

if (!preg_match('/^09\d{9}$/', $phoneInput) || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid phone number and password are required.']);
    exit;
}

try {
    $select = $pdo->prepare(
        'SELECT u.id, u.member_id, u.password_hash, u.status, u.last_otp_verified_at,
                p.phone, p.firstname, p.lastname
         FROM users u
         INNER JOIN youth_profiles p ON p.user_id = u.id
         WHERE p.phone = ?
         LIMIT 1'
    );
    $select->execute([$phoneInput]);
    $user = $select->fetch(PDO::FETCH_ASSOC);
    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid phone number or password.']);
        exit;
    }
    if ($user['status'] !== 'active') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Account is inactive. Please contact support.']);
        exit;
    }

    if ($trustDevice && !empty($user['last_otp_verified_at'])
        && strtotime((string)$user['last_otp_verified_at']) > time() - (30 * 86400)) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['member_id'] = $user['member_id'];
        $_SESSION['phone'] = $user['phone'];
        $_SESSION['authenticated'] = true;
        echo json_encode([
            'success' => true,
            'message' => 'Login successful.',
            'skip_otp' => true,
            'redirect' => 'youth-portal.html'
        ]);
        exit;
    }

    otpReserveSend($pdo, 'login', (string)$user['id']);
    $code = otpGenerateCode();
    otpSendSms((string)$user['phone'], $code, 'login');
    $_SESSION['login_otp_hash'] = otpCodeHash($code);
    $_SESSION['login_phone'] = $user['phone'];
    $_SESSION['login_user_id'] = (int)$user['id'];
    $_SESSION['login_member_id'] = $user['member_id'];
    $_SESSION['login_firstname'] = $user['firstname'];
    $_SESSION['login_lastname'] = $user['lastname'];
    $_SESSION['login_trust_device'] = $trustDevice;
    $_SESSION['login_otp_expires_at'] = time() + 300;
    $_SESSION['login_otp_attempts'] = 0;

    echo json_encode([
        'success' => true,
        'message' => 'A login code was sent to ' . otpMaskPhone((string)$user['phone']) . '.',
        'skip_otp' => false,
        'phone' => otpMaskPhone((string)$user['phone'])
    ]);
} catch (RuntimeException $exception) {
    $limited = strpos($exception->getMessage(), 'rate limited') !== false;
    http_response_code($limited ? 429 : 503);
    error_log('[Login OTP] Request failed: ' . $exception->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $limited
            ? 'OTP sending is temporarily rate limited. Try again later.'
            : 'Unable to send the login code right now.'
    ]);
} catch (Throwable $exception) {
    error_log('[Login OTP] Request failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to process the login request.']);
}
