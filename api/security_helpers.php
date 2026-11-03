<?php

require_once dirname(__DIR__) . '/includes/request.php';

function securityStartSession(): void
{
    secureSessionStart();
}

function securityCsrfToken(): string
{
    securityStartSession();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function securityRequireCsrf(): void
{
    securityStartSession();
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!is_string($provided) || !is_string($expected) || $expected === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing CSRF token.']);
        exit;
    }
}

function securityClientIp(): string
{
    return clientIp();
}

function securityUserAgentHash(): string
{
    return hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

function otpHmacSecret(): string
{
    require_once dirname(__DIR__) . '/includes/config.php';
    return skalingaConfig()['SITE_KEY'];
}

function otpCodeHash(string $code): string
{
    return hash_hmac('sha256', $code, otpHmacSecret());
}

function otpGenerateCode(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function otpMaskPhone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (!is_string($digits) || strlen($digits) < 5) {
        return 'the registered number';
    }

    return substr($digits, 0, 2) . str_repeat('*', max(0, strlen($digits) - 5)) . substr($digits, -3);
}

function otpFormatPhone(string $phone): string
{
    $phone = trim($phone);
    if (preg_match('/^09\d{9}$/', $phone)) {
        return '+63' . substr($phone, 1);
    }
    if (preg_match('/^\+639\d{9}$/', $phone)) {
        return $phone;
    }

    throw new InvalidArgumentException('The registered mobile number is not in a supported format.');
}

function otpReserveSend(PDO $pdo, string $purpose, string $subject): void
{
    securityStartSession();
    $now = time();
    $userKey = hash('sha256', $purpose . ':user:' . $subject);
    $ipKey = hash('sha256', $purpose . ':ip:' . securityClientIp());
    $limits = $_SESSION['auth_otp_send_limits'] ?? [];
    $limits = is_array($limits) ? $limits : [];

    $userTimestamps = is_array($limits[$userKey] ?? null)
        ? array_values(array_filter($limits[$userKey], static fn($timestamp) => is_int($timestamp) && $timestamp > $now - 3600))
        : [];
    $ipTimestamps = is_array($limits[$ipKey] ?? null)
        ? array_values(array_filter($limits[$ipKey], static fn($timestamp) => is_int($timestamp) && $timestamp > $now - 3600))
        : [];

    $recentUserSend = $userTimestamps !== [] ? max($userTimestamps) : 0;
    $recentIpSend = $ipTimestamps !== [] ? max($ipTimestamps) : 0;

    if (count($userTimestamps) >= 5 || count($ipTimestamps) >= 5) {
        throw new RuntimeException('OTP sending is temporarily rate limited.');
    }

    if (($recentUserSend > 0 && $now - $recentUserSend < 60)
        || ($recentIpSend > 0 && $now - $recentIpSend < 60)) {
        throw new RuntimeException('OTP sending is temporarily rate limited.');
    }

    $limits[$userKey] = $userTimestamps;
    $limits[$ipKey] = $ipTimestamps;
    $limits[$userKey][] = $now;
    $limits[$ipKey][] = $now;
    $_SESSION['auth_otp_send_limits'] = $limits;
}

function otpSendSms(string $registeredPhone, string $code, string $purpose): void
{
    $config = skalingaConfig();
    $url = $config['SKALINGA_SMS_API_URL'] ?? null;
    $apiKey = $config['SKALINGA_SMS_API_KEY'] ?? null;
    if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false
        || parse_url($url, PHP_URL_SCHEME) !== 'https'
        || !is_string($apiKey) || $apiKey === '') {
        throw new RuntimeException('SMS provider settings are not configured.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required to send OTP messages.');
    }

    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Unable to initialize the SMS provider connection.');
    }
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'recipient' => otpFormatPhone($registeredPhone),
            'message' => 'Your SKalinga verification code is ' . $code . '. It expires in 5 minutes.'
        ], JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if (!is_string($response) || $status < 200 || $status >= 300) {
        error_log('[OTP] SMS provider delivery failed for purpose ' . $purpose . '; HTTP ' . $status . '; ' . $curlError);
        throw new RuntimeException('Unable to send the verification code right now.');
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded) || ($decoded['success'] ?? false) !== true) {
        error_log('[OTP] SMS provider rejected delivery for purpose ' . $purpose . '; HTTP ' . $status);
        throw new RuntimeException('Unable to send the verification code right now.');
    }
}
