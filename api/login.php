<?php

require_once 'db_connect.php';
require_once 'auth.php';

authStartSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

require_once __DIR__ . '/../includes/request.php';

$identifier = trim($_POST['identifier'] ?? '');
$password = $_POST['password'] ?? '';
$ipAddress = clientIp();

if ($identifier === '' || $password === '') {
    authJsonResponse(['success' => false, 'message' => 'Identifier and password are required.'], 400);
}

$attemptType = 'login';
$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM login_attempts
     WHERE ip_address = ? AND attempt_type = ?
       AND attempted_at >= (CURRENT_TIMESTAMP - INTERVAL \'15 minutes\')'
);
$stmt->execute([$ipAddress, $attemptType]);
if ((int)$stmt->fetchColumn() >= 10) {
    authJsonResponse(['success' => false, 'message' => 'Too many login attempts. Please try again later.'], 429);
}
$stmt = $pdo->prepare('INSERT INTO login_attempts (ip_address, attempt_type) VALUES (?, ?)');
$stmt->execute([$ipAddress, $attemptType]);

$normalizedIdentifier = function_exists('mb_strtolower')
    ? mb_strtolower(preg_replace('/\s+/u', ' ', $identifier), 'UTF-8')
    : strtolower(preg_replace('/\s+/', ' ', $identifier));
$phoneIdentifier = preg_replace('/\D+/', '', $identifier);

try {
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
        error_log('[Auth Login] Identifier collision detected: ' . $identifier);
        authJsonResponse(['success' => false, 'message' => 'This identifier is assigned to more than one account. Contact the SK Office.'], 409);
    }

    $account = $admin ?: $youth;
    if (!$account || ($account['status'] ?? '') !== 'active' || !password_verify($password, $account['password_hash'])) {
        authJsonResponse(['success' => false, 'message' => 'Invalid credentials.'], 401);
    }

    $isAdmin = is_array($admin);
    $role = $isAdmin ? (($account['role'] ?? '') === 'superadmin' ? 'admin' : 'sk_official') : 'youth';
    $name = $isAdmin
        ? $account['username']
        : trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''));
    $accountIdentifier = $isAdmin ? $account['username'] : ($account['email'] ?: ($account['phone'] ?: $account['member_id']));

    $pdo->beginTransaction();
    $update = $pdo->prepare($isAdmin
        ? 'UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?'
        : 'UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?'
    );
    $update->execute([$account['id']]);

    $activity = $pdo->prepare(
        'INSERT INTO activity_logs (user_id, role, action, identifier, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    );
    $activity->execute([(int)$account['id'], $role, 'login_success', $accountIdentifier, $ipAddress]);
    $pdo->commit();

    session_regenerate_id(true);
    $_SESSION['auth_user_id'] = (int)$account['id'];
    $_SESSION['auth_role'] = $role;
    $_SESSION['auth_identifier'] = $accountIdentifier;
    $_SESSION['auth_name'] = $name;
    $_SESSION['member_id'] = $account['member_id'] ?? null;
    $_SESSION['user_id'] = $isAdmin ? null : (int)$account['id'];
    $_SESSION['admin_authenticated'] = $isAdmin;
    $_SESSION['admin_id'] = $isAdmin ? (int)$account['id'] : null;
    $_SESSION['admin_username'] = $isAdmin ? $account['username'] : null;
    $_SESSION['admin_email'] = $isAdmin ? $account['email'] : null;
    $_SESSION['admin_role'] = $isAdmin ? ($account['role'] === 'superadmin' ? 'superadmin' : 'staff') : null;

    error_log("[Auth Login] {$role} account {$accountIdentifier} logged in");

    $requestedRedirect = basename(parse_url($_POST['redirect'] ?? '', PHP_URL_PATH) ?: '');
    $youthPages = ['youth-portal.html', 'events.html', 'borrow.html', 'print.html', 'health.html', 'report.html', 'profile.html', 'public-disclosure.html'];
    $adminPages = ['admin-dashboard.html', 'admin-masterlist.html', 'youth-registry.html', 'dashboard-events.html', 'dashboard-resources.html', 'dashboard-printing.html', 'dashboard-incidents.html', 'admin-health-announcements.html', 'dashboard-public_disclosure.html', 'dashboard-settings.html'];
    $redirect = $isAdmin
        ? (in_array($requestedRedirect, $adminPages, true) ? $requestedRedirect : 'admin-dashboard.html')
        : (in_array($requestedRedirect, $youthPages, true) ? $requestedRedirect : 'youth-portal.html');

    authJsonResponse([
        'success' => true,
        'role' => $role,
        'name' => $name,
        'member_id' => $account['member_id'] ?? null,
        'redirect' => $redirect
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Auth Login Error] ' . $exception->getMessage());
    authJsonResponse(['success' => false, 'message' => 'Unable to sign in right now. Please try again later.'], 500);
}
