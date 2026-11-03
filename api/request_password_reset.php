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
$email = is_string($emailValue) ? trim($emailValue) : '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Enter a valid email address.']);
    exit;
}

$genericResponse = [
    'success' => true,
    'message' => 'If an active youth account uses this email, an OTP will be sent shortly.'
];
$appConfig = skalingaConfig();
$emailJsOrigin = $appConfig['EMAILJS_ORIGIN'] ?? 'http://localhost';
$serviceId = $appConfig['EMAILJS_SERVICE_ID'] ?? '';
$templateId = $appConfig['EMAILJS_TEMPLATE_ID'] ?? '';
$publicKey = $appConfig['EMAILJS_PUBLIC_KEY'] ?? '';

try {
    $userStmt = $pdo->prepare(
        'SELECT id, email FROM users WHERE LOWER(email) = LOWER(?) AND status = ? LIMIT 1'
    );
    $userStmt->execute([$email, 'active']);
    $user = $userStmt->fetch();

    if (!$user) {
        echo json_encode($genericResponse);
        exit;
    }

    if ($serviceId === '' || $templateId === '' || $publicKey === '') {
        throw new RuntimeException('EmailJS settings are not configured.');
    }
    otpReserveSend($pdo, 'password-reset', (string)$user['id']);
    $otpCode = otpGenerateCode();
    $otpHash = otpCodeHash($otpCode);

    $pdo->beginTransaction();

    $deleteStmt = $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND is_used = FALSE');
    $deleteStmt->execute([$user['id']]);

    $insertStmt = $pdo->prepare(
        'INSERT INTO password_resets (user_id, otp_code, otp_attempts, is_used, expires_at, created_at)
         VALUES (?, ?, 0, FALSE, CURRENT_TIMESTAMP + INTERVAL \'5 minutes\', CURRENT_TIMESTAMP)'
    );
    $insertStmt->execute([$user['id'], $otpHash]);

    $emailPayload = json_encode([
        'service_id' => $serviceId,
        'template_id' => $templateId,
        'user_id' => $publicKey,
        'template_params' => [
            'to_email' => $user['email'],
            'email' => $user['email'],
            'user_email' => $user['email'],
            'recipient_email' => $user['email'],
            'otp' => $otpCode
        ]
    ]);

    $curl = curl_init('https://api.emailjs.com/api/v1.0/email/send');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $emailPayload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Origin: ' . $emailJsOrigin
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15
    ]);
    $emailResponse = curl_exec($curl);
    $emailStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($emailResponse === false || $emailStatus < 200 || $emailStatus >= 300) {
        $pdo->rollBack();
        error_log(
            '[Password Reset] EmailJS send failed; HTTP ' . $emailStatus .
            '; cURL error: ' . $curlError .
            '; response: ' . (is_string($emailResponse) ? $emailResponse : '')
        );
        http_response_code(502);
        $message = 'Unable to send the password reset email right now. Please try again later.';
        if ($emailStatus === 403) {
            $message = 'Email service rejected the request. Verify the EmailJS configuration, EMAILJS_ORIGIN, and allowed origins.';
        } elseif ($emailStatus === 422 && stripos((string)$emailResponse, 'recipient') !== false) {
            $message = 'EmailJS could not determine the recipient. Set the template To Email field to {{to_email}}.';
        }
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
        exit;
    }

    $pdo->commit();
    echo json_encode($genericResponse);
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (strpos($exception->getMessage(), 'rate limited') !== false) {
        echo json_encode($genericResponse);
        exit;
    }
    error_log('[Password Reset Request Error] ' . $exception->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Unable to send the password reset code right now.']);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Password Reset Request Error] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to process the password reset request right now.'
    ]);
}
