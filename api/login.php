<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/../includes/turnstile.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mail.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    authJsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$verification = verifyTurnstile(
    is_string($_POST['cf-turnstile-response'] ?? null) ? $_POST['cf-turnstile-response'] : '',
    'login'
);
if (!$verification['success']) {
    authJsonResponse(['success' => false, 'message' => $verification['message']], 400);
}

authStartSession();
foreach ([
    'auth_user_id', 'auth_role', 'auth_identifier', 'auth_name', 'member_id', 'user_id',
    'authenticated', 'admin_authenticated', 'admin_id', 'admin_username', 'admin_email',
    'admin_role', 'otp_verified_at', 'otp_reauth_required', 'otp_reauth_user_id',
    'otp_reauth_role', 'otp_reauth_identifier', 'otp_reauth_name', 'otp_reauth_member_id',
    'otp_reauth_email', 'otp_reauth_redirect', 'login_pending_type', 'login_otp_hash',
    'login_user_id', 'login_otp_expires_at', 'login_otp_attempts'
] as $sessionKey) {
    unset($_SESSION[$sessionKey]);
}
session_regenerate_id(true);

require_once __DIR__ . '/db_connect.php';

$identifier = trim(is_string($_POST['identifier'] ?? null) ? $_POST['identifier'] : '');
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
if ($identifier === '' || $password === '') {
    authJsonResponse(['success' => false, 'message' => 'Identifier and password are required.'], 400);
}

try {
    $ipAddress = securityClientIp();
    $attemptStmt = $pdo->prepare(
        'SELECT COUNT(*), MAX(attempted_at) FROM login_attempts
         WHERE ip_address = ? AND attempt_type = ?
           AND attempted_at >= (CURRENT_TIMESTAMP - INTERVAL \'1 hour\')'
    );
    $attemptStmt->execute([$ipAddress, 'login']);
    $attempts = $attemptStmt->fetch(PDO::FETCH_NUM);
    $attemptCount = (int)($attempts[0] ?? 0);
    $lastAttempt = isset($attempts[1]) ? strtotime((string)$attempts[1]) : false;
    $lockoutSeconds = 180;
    if ($attemptCount >= 5 && $lastAttempt !== false && time() - $lastAttempt < $lockoutSeconds) {
        $waitSeconds = $lockoutSeconds - (time() - $lastAttempt);
        authJsonResponse([
            'success' => false,
            'message' => 'Too many login attempts. Please wait ' . $waitSeconds . ' seconds before trying again.'
        ], 429);
    }
    if ($attemptCount >= 5) {
        $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = ? AND attempt_type = ?')
            ->execute([$ipAddress, 'login']);
    }

    $recordFailedAttempt = static function () use ($pdo, $ipAddress): void {
        $pdo->prepare('INSERT INTO login_attempts (ip_address, attempt_type) VALUES (?, ?)')
            ->execute([$ipAddress, 'login']);
    };

    $normalizedIdentifier = function_exists('mb_strtolower')
        ? mb_strtolower(preg_replace('/\s+/u', ' ', $identifier), 'UTF-8')
        : strtolower(preg_replace('/\s+/', ' ', $identifier));
    $phoneIdentifier = preg_replace('/\D+/', '', $identifier);

    $adminStmt = $pdo->prepare(
        'SELECT id, username, email, password_hash, role, status
         FROM admins
         WHERE LOWER(username) = ? OR LOWER(email) = ?
         LIMIT 1'
    );
    $adminStmt->execute([$normalizedIdentifier, $normalizedIdentifier]);
    $admin = $adminStmt->fetch() ?: null;

    $youthStmt = $pdo->prepare(
        'SELECT u.id, u.email, u.member_id, u.password_hash, u.status,
                p.phone, p.firstname, p.lastname
         FROM users u
         LEFT JOIN youth_profiles p ON p.user_id = u.id
         WHERE (u.email IS NOT NULL AND LOWER(u.email) = ?)
            OR p.phone = ?
         LIMIT 1'
    );
    $youthStmt->execute([$normalizedIdentifier, $phoneIdentifier]);
    $youth = $youthStmt->fetch() ?: null;

    if ($admin && $youth) {
        error_log('[Auth Login] Identifier collision detected.');
        $recordFailedAttempt();
        authJsonResponse(['success' => false, 'message' => 'Invalid credentials.'], 401);
    }

    $account = $admin ?: $youth;
    $passwordHash = is_array($account) ? (string)$account['password_hash'] : '$2y$10$92IXUNpkjOQ0QOQ0QOQ0QeOQ0QOQ0QOQ0QOQ0QOQ0QOQ0QOQ0QOQ0Q';
    if (!$account) {
        password_verify($password, password_hash('SKalinga unknown account dummy password', PASSWORD_DEFAULT));
    }
    $passwordMatches = password_verify($password, $passwordHash);
    if (!$account || !$passwordMatches || strtolower((string)($account['status'] ?? '')) !== 'active') {
        $recordFailedAttempt();
        authJsonResponse(['success' => false, 'message' => 'Invalid credentials.'], 401);
    }

    $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = ? AND attempt_type = ?')
        ->execute([$ipAddress, 'login']);

    $isAdmin = is_array($admin);
    $role = $isAdmin ? (($account['role'] ?? '') === 'superadmin' ? 'admin' : 'sk_official') : 'youth';
    $name = $isAdmin
        ? (string)$account['username']
        : trim((string)($account['firstname'] ?? '') . ' ' . (string)($account['lastname'] ?? ''));
    error_log('[Auth Login] Password verified; role=' . $role . '.');
    $accountIdentifier = $isAdmin
        ? (string)$account['username']
        : (string)(($account['email'] ?? null) ?: (($account['phone'] ?? null) ?: $account['member_id']));
    $requestedRedirect = basename(parse_url($_POST['redirect'] ?? '', PHP_URL_PATH) ?: '');
    $youthPages = ['youth-portal.html', 'events.html', 'borrow.html', 'print.html', 'health.html', 'report.html', 'profile.html', 'public-disclosure.html'];
    $adminPages = ['admin-dashboard.html', 'admin-masterlist.html', 'youth-registry.html', 'dashboard-events.html', 'dashboard-resources.html', 'dashboard-printing.html', 'dashboard-incidents.html', 'admin-health-announcements.html', 'dashboard-public_disclosure.html', 'dashboard-settings.html', 'admin-service-verification.html'];
    $redirect = $isAdmin
        ? (in_array($requestedRedirect, $adminPages, true) ? $requestedRedirect : 'admin-dashboard.html')
        : (in_array($requestedRedirect, $youthPages, true) ? $requestedRedirect : 'youth-portal.html');

    $config = skalingaConfig();
    $otpMode = strtolower(trim((string)($config['LOGIN_OTP_MODE'] ?? 'email')));
    if (!in_array($otpMode, ['email', 'off'], true)) {
        throw new RuntimeException('LOGIN_OTP_MODE must be email or off.');
    }

    if ($otpMode === 'off') {
        error_log('[Auth Login WARNING] LOGIN_OTP_MODE=off; authenticated password-only login for role ' . $role . '.');
        completePasswordOnlyLogin($pdo, $account, $role, $accountIdentifier, $name, $redirect, $isAdmin);
        $_SESSION['login_otp_off'] = true;
        authJsonResponse([
            'success' => true,
            'status' => 'authenticated',
            'role' => $role,
            'redirect' => $redirect,
            'otp_off' => true
        ]);
    }

    $email = trim((string)($account['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        authJsonResponse([
            'success' => false,
            'message' => 'Your account has no email address yet. Please contact the SK Office.'
        ], 403);
    }

    $recentChallenge = $pdo->prepare(
        'SELECT 1 FROM login_challenges
         WHERE consumed_at IS NULL AND expires_at > CURRENT_TIMESTAMP
           AND last_sent_at > CURRENT_TIMESTAMP - INTERVAL \'60 seconds\'
           AND ((user_type = ? AND user_id = ?) OR ip = ?)
         LIMIT 1'
    );
    $recentChallenge->execute([$role, (int)$account['id'], $ipAddress]);
    if ($recentChallenge->fetchColumn()) {
        authJsonResponse([
            'success' => false,
            'message' => 'A valid code was sent recently. Check your email or wait before requesting another.'
        ], 429);
    }

    $hourly = $pdo->prepare(
        'SELECT COUNT(*) FROM login_challenges
         WHERE created_at >= CURRENT_TIMESTAMP - INTERVAL \'1 hour\'
           AND ((user_type = ? AND user_id = ?) OR ip = ?)'
    );
    $hourly->execute([$role, (int)$account['id'], $ipAddress]);
    if ((int)$hourly->fetchColumn() >= 5) {
        authJsonResponse([
            'success' => false,
            'message' => 'Too many verification requests. Please try again in an hour.'
        ], 429);
    }

    $pdo->exec('DELETE FROM login_challenges WHERE expires_at < CURRENT_TIMESTAMP OR consumed_at < CURRENT_TIMESTAMP - INTERVAL \'1 day\'');
    $code = otpGenerateCode();
    $rawToken = bin2hex(random_bytes(32));
    $challengeId = loginChallengeUuid();
    $insert = $pdo->prepare(
        'INSERT INTO login_challenges
         (id, user_type, user_id, token_hash, code_hash, expires_at, ip, user_agent, redirect_to)
         VALUES (?::uuid, ?, ?, ?, ?, CURRENT_TIMESTAMP + INTERVAL \'10 minutes\', ?, ?, ?)'
    );
    $insert->execute([
        $challengeId,
        $role,
        (int)$account['id'],
        hash('sha256', $rawToken),
        otpCodeHash($code),
        $ipAddress,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
        $redirect
    ]);
    error_log('[Auth Login] Email challenge created; role=' . $role . '; email=' . otpMaskEmail($email) . '.');

    try {
        sendLoginOtpEmail($email, $name, $code, 10, $pdo);
    } catch (Throwable $exception) {
        $pdo->prepare('DELETE FROM login_challenges WHERE id = ?::uuid')->execute([$challengeId]);
        throw $exception;
    }

    setLoginChallengeCookie($rawToken, time() + 600);
    error_log('[Auth Login] Email challenge delivery succeeded; role=' . $role . '; email=' . otpMaskEmail($email) . '.');
    authJsonResponse([
        'success' => true,
        'status' => 'otp_required',
        'maskedEmail' => otpMaskEmail($email),
        'expiresIn' => 600,
        'resendAfter' => 60
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $reference = securityLogException('Auth Login Error', $exception, [$password, $code ?? '']);
    if ($exception instanceof MailQuotaException) {
        authJsonResponse([
            'success' => false,
            'message' => 'Email codes are unavailable until next month. Please contact the SK Office.',
            'reference' => $reference
        ], 503);
    }
    $limited = str_contains(strtolower($exception->getMessage()), 'rate limited');
    authJsonResponse([
        'success' => false,
        'message' => $limited
            ? 'Please wait before requesting another verification code.'
            : 'We couldn\'t send your code. Please try again. Reference: ' . $reference,
        'reference' => $reference
    ], $limited ? 429 : 503);
}

function completePasswordOnlyLogin(
    PDO $pdo,
    array $account,
    string $role,
    string $identifier,
    string $name,
    string $redirect,
    bool $isAdmin
): void {
    $userId = (int)$account['id'];
    $pdo->beginTransaction();
    if ($role === 'youth') {
        $pdo->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?')->execute([$userId]);
    } else {
        $pdo->prepare('UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?')->execute([$userId]);
    }
    $pdo->prepare(
        'INSERT INTO activity_logs (user_id, role, action, identifier, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$userId, $role, 'login_success', $identifier, securityClientIp()]);
    $pdo->commit();

    establishLoginSession($account, $role, $identifier, $name, $isAdmin);
}
