<?php
require_once 'db_connect.php';
require_once __DIR__ . '/security_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$emailValue = is_array($input) ? ($input['email'] ?? null) : null;
$otpValue = is_array($input) ? ($input['otp_code'] ?? null) : null;
$email = is_string($emailValue) ? trim($emailValue) : '';
$otpCode = is_string($otpValue) ? trim($otpValue) : '';
$password = is_array($input) ? ($input['password'] ?? '') : '';

$errors = [];
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Enter a valid email address.';
}
if (!preg_match('/^\d{6}$/', $otpCode)) {
    $errors[] = 'Enter the 6-digit OTP.';
}
if (!is_string($password) || strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
}

if ($errors) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
    exit;
}

try {
    $userStmt = $pdo->prepare(
        'SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND status = ? LIMIT 1'
    );
    $userStmt->execute([$email, 'active']);
    $user = $userStmt->fetch();

    if (!$user) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP. Request a new code and try again.']);
        exit;
    }

    $pdo->beginTransaction();
    $resetStmt = $pdo->prepare(
        'SELECT id, otp_code, otp_attempts
         FROM password_resets
        WHERE user_id = ? AND is_used = FALSE AND expires_at > CURRENT_TIMESTAMP
         ORDER BY created_at DESC, id DESC
         LIMIT 1
         FOR UPDATE'
    );
    $resetStmt->execute([$user['id']]);
    $reset = $resetStmt->fetch();

    if (!$reset || (int)$reset['otp_attempts'] >= 5) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP. Request a new code and try again.']);
        exit;
    }

    if (!hash_equals((string)$reset['otp_code'], otpCodeHash($otpCode))) {
        $attempts = (int)$reset['otp_attempts'] + 1;
        $attemptStmt = $pdo->prepare(
            'UPDATE password_resets SET otp_attempts = ?, is_used = ? WHERE id = ?'
        );
        $attemptStmt->bindValue(1, $attempts, PDO::PARAM_INT);
        $attemptStmt->bindValue(2, $attempts >= 5, PDO::PARAM_BOOL);
        $attemptStmt->bindValue(3, (int)$reset['id'], PDO::PARAM_INT);
        $attemptStmt->execute();
        $pdo->commit();

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP. Request a new code and try again.']);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $updateStmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $updateStmt->execute([$passwordHash, $user['id']]);

    $usedStmt = $pdo->prepare('UPDATE password_resets SET is_used = TRUE WHERE id = ?');
    $usedStmt->execute([$reset['id']]);
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully. Sign in with your new password.'
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Password Reset Error] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to reset the password right now.']);
}
