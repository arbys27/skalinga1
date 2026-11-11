<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function normalizePhPhone(string $phone): string
{
    $phone = preg_replace('/[\s().-]+/', '', trim($phone)) ?? '';
    if ($phone === '') {
        throw new InvalidArgumentException('Enter a valid Philippine mobile number (09XXXXXXXXX).');
    }
    if (preg_match('/^09\d{9}$/', $phone)) {
        return '+63' . substr($phone, 1);
    }
    if (preg_match('/^9\d{9}$/', $phone)) {
        return '+63' . $phone;
    }
    if (preg_match('/^639\d{9}$/', $phone)) {
        return '+' . $phone;
    }
    if (preg_match('/^\+639\d{9}$/', $phone)) {
        return $phone;
    }

    throw new InvalidArgumentException('Enter a valid Philippine mobile number (09XXXXXXXXX).');
}

function sendOTP(string $mobile, string $message): array
{
    $config = skalingaConfig();
    $driver = strtolower(trim((string)($config['SMS_DRIVER'] ?? 'iprog')));
    $recipient = normalizePhPhone($mobile);
    if ($message === '' || strlen($message) > 670) {
        throw new InvalidArgumentException('The SMS message must contain 1 to 670 characters.');
    }
    if ($driver === 'log') {
        if (($config['APP_ENV'] ?? '') === 'production') {
            throw new RuntimeException('The SMS log driver is forbidden in production.');
        }
        error_log('[SMS:log] Youth activation SMS delivery is disabled; phone='
            . smsMaskPhone($recipient) . '; OTP was not logged or sent.');
        throw new RuntimeException('SMS log driver cannot deliver activation codes.');
    }
    if (!in_array($driver, ['iprog', 'unisms'], true)) {
        throw new RuntimeException('SMS_DRIVER must be iprog, unisms, or log.');
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required to send OTP messages.');
    }

    if ($driver === 'iprog') {
        $apiToken = trim((string)($config['IPROG_API_TOKEN'] ?? ''));
        if ($apiToken === '') {
            throw new RuntimeException('iProg SMS API token is not configured.');
        }

        $phoneNumber = ltrim($recipient, '+');
        $requestBody = [
            'api_token' => $apiToken,
            'phone_number' => $phoneNumber,
            'message' => $message,
        ];
        $provider = trim((string)($config['IPROG_SMS_PROVIDER'] ?? ''));
        if ($provider !== '') {
            $requestBody['sms_provider'] = $provider;
        }

        $curl = curl_init('https://www.iprogsms.com/api/v1/sms_messages');
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize the iProg SMS connection.');
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestBody, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $response = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);
        $responseBody = is_string($response) ? trim($response) : '';
        $safeResponse = smsRedactText($responseBody, [$apiToken, $phoneNumber, $recipient, smsExtractOtp($message)]);
        error_log('[iProgSMS] HTTP status=' . $status . '; phone=' . smsMaskPhone($recipient)
            . '; response=' . ($safeResponse !== '' ? $safeResponse : '[empty response]')
            . ($curlError !== '' ? '; cURL error=' . smsRedactText($curlError, [$apiToken]) : ''));

        if (!is_string($response) || $status < 200 || $status >= 300) {
            throw new RuntimeException('We couldn\'t send the code. Please try again.');
        }
        $decoded = json_decode($responseBody, true);
        $messageId = is_array($decoded) ? ($decoded['message_id'] ?? null) : null;
        if (!is_string($messageId) || $messageId === '') {
            error_log('[iProgSMS] Successful HTTP status had an invalid JSON response; phone='
                . smsMaskPhone($recipient));
            throw new RuntimeException('We couldn\'t send the code. Please try again.');
        }

        return ['provider' => 'iProgSMS', 'status' => $status, 'reference_id' => $messageId];
    }

    $apiKey = trim((string)($config['UNISMS_API_KEY'] ?? ''));
    $senderId = trim((string)($config['UNISMS_SENDER_ID'] ?? ''));
    if ($apiKey === '' || $senderId === '') {
        throw new RuntimeException('UniSMS API credentials and approved sender ID are not configured.');
    }

    $curl = curl_init('https://unismsapi.com/api/sms');
    if ($curl === false) {
        throw new RuntimeException('Unable to initialize the UniSMS connection.');
    }
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'content' => $message,
            'sender_id' => $senderId,
            'recipient' => $recipient,
        ], JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    $responseBody = is_string($response) ? trim($response) : '';
    $safeResponse = smsRedactText($responseBody, [$apiKey, $senderId, $recipient, smsExtractOtp($message)]);
    error_log('[UniSMS] HTTP status=' . $status . '; phone=' . smsMaskPhone($recipient)
        . '; response=' . ($safeResponse !== '' ? $safeResponse : '[empty response]')
        . ($curlError !== '' ? '; cURL error=' . smsRedactText($curlError, [$apiKey]) : ''));

    if (!is_string($response) || $status < 200 || $status >= 300) {
        throw new RuntimeException('We couldn\'t send the code. Please try again.');
    }
    $decoded = json_decode($responseBody, true);
    $messageResult = is_array($decoded) ? ($decoded['message'] ?? null) : null;
    if (!is_array($messageResult)
        || !isset($messageResult['reference_id'])
        || !is_string($messageResult['reference_id'])
        || $messageResult['reference_id'] === '') {
        error_log('[UniSMS] Successful HTTP status had an invalid JSON response; phone=' . smsMaskPhone($recipient));
        throw new RuntimeException('We couldn\'t send the code. Please try again.');
    }

    return ['provider' => 'UniSMS', 'status' => $status, 'reference_id' => $messageResult['reference_id']];
}

function smsMaskPhone(string $phone): string
{
    try {
        $normalized = normalizePhPhone($phone);
        return substr($normalized, 0, 6) . '****' . substr($normalized, -3);
    } catch (InvalidArgumentException) {
        return 'the registered number';
    }
}

function smsExtractOtp(string $message): string
{
    return preg_match('/\b(\d{6})\b/', $message, $matches) ? $matches[1] : '';
}

function smsRedactText(string $text, array $sensitiveValues = []): string
{
    foreach ($sensitiveValues as $value) {
        if (is_string($value) && $value !== '') {
            $text = str_replace($value, '[REDACTED]', $text);
        }
    }
    $text = preg_replace('/\s+/', ' ', $text) ?? '';
    return substr($text, 0, 1500);
}
