<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/security_helpers.php';
require_once dirname(__DIR__, 2) . '/includes/request.php';

function verificationResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function verificationRequireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        verificationResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
}

function verificationInput(): array
{
    $raw = file_get_contents('php://input');
    $input = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    return is_array($input) ? $input : $_POST;
}

function verificationAudit(
    PDO $pdo,
    string $action,
    string $result,
    ?int $tokenId = null,
    ?string $tokenHash = null,
    ?int $userId = null,
    ?int $adminId = null,
    ?string $serviceType = null,
    ?string $serviceRef = null
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO service_verification_audit
            (token_id, token_hash, user_id, admin_id, action, result, service_type, service_ref, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $tokenId,
        $tokenHash,
        $userId,
        $adminId,
        $action,
        $result,
        $serviceType,
        $serviceRef,
        securityClientIp(),
    ]);
}

function verificationTokenInput(array $input): ?string
{
    $token = $input['token'] ?? null;
    if (!is_string($token)) {
        return null;
    }

    $token = trim($token);
    return preg_match('/^[a-f0-9]{64}$/i', $token) === 1 ? strtolower($token) : null;
}

function verificationServiceRequest(
    PDO $pdo,
    string $serviceType,
    string $serviceRef,
    string $memberId,
    string $purpose = 'claim',
    bool $forUpdate = false
): ?array
{
    if ($serviceType !== 'print' || preg_match('/^[1-9]\d{0,9}$/', $serviceRef) !== 1) {
        return null;
    }

    $status = $purpose === 'submit' ? 'Pending' : 'Completed';
    $sql = "SELECT request_id, member_id, member_name, document_title, status
            FROM printing_requests
            WHERE request_id = ? AND member_id = ? AND status = ?
            LIMIT 1";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$serviceRef, $memberId, $status]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    return $request ?: null;
}

function verificationLoadToken(PDO $pdo, string $tokenHash, bool $forUpdate = false): ?array
{
    $sql = 'SELECT id, user_id, service_type, service_ref, status, expires_at,
                   scanned_by_admin_id, otp_required, otp_hash, otp_expires_at, otp_attempts,
                   code_hash, code_expires_at, code_attempts, code_verified_at
            FROM service_verification_tokens
            WHERE token_hash = ?
            LIMIT 1';
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function verificationIsExpired(string $timestamp): bool
{
    $expires = strtotime($timestamp);
    return $expires === false || $expires <= time();
}

function verificationServiceLabel(string $serviceType, array $request): string
{
    if ($serviceType === 'print') {
        return 'Print request';
    }

    return 'Service request';
}
