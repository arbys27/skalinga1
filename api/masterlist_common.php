<?php

require_once 'auth.php';
require_once __DIR__ . '/../includes/request.php';

function masterlistJsonResponse(array $payload, int $status = 200): void
{
    jsonResponse($payload, $status);
}

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeMasterlistValue(?string $value): string
{
    $value = trim((string)$value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function normalizeMasterlistName(?string $value): string
{
    return normalizeMasterlistValue($value);
}

function masterlistClientIp(): string
{
    return clientIp();
}

function enforceMasterlistRateLimit(PDO $pdo, string $attemptType, int $maxAttempts = 10): void
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip_address = ? AND attempt_type = ?
           AND attempted_at >= (CURRENT_TIMESTAMP - INTERVAL \'15 minutes\')'
    );
    $stmt->execute([masterlistClientIp(), $attemptType]);

    if ((int)$stmt->fetchColumn() >= $maxAttempts) {
        masterlistJsonResponse([
            'success' => false,
            'message' => 'Too many attempts. Please try again later.'
        ], 429);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (ip_address, attempt_type) VALUES (?, ?)'
    );
    $stmt->execute([masterlistClientIp(), $attemptType]);
}

function requireAdminMasterlistAccess(): void
{
    requireAdminApi();
}

function masterlistNameMatches(array $record, string $submittedName): bool
{
    $submittedName = normalizeMasterlistName($submittedName);
    $first = normalizeMasterlistName($record['first_name']);
    $middle = normalizeMasterlistName($record['middle_name'] ?? '');
    $last = normalizeMasterlistName($record['last_name']);

    $fullName = trim(implode(' ', array_filter([$first, $middle, $last])));
    $shortName = trim($first . ' ' . $last);

    return $submittedName !== '' && ($submittedName === $fullName || $submittedName === $shortName);
}

function masterlistFindRecord(PDO $pdo, string $voterId, string $fullName, string $birthday, bool $forUpdate = false): ?array
{
    $sql = 'SELECT id, voter_id, first_name, last_name, middle_name, birthday, age, sex, address,
                   purok_or_barangay, is_claimed, claimed_by_user_id
            FROM kk_masterlist
            WHERE LOWER(voter_id) = ? AND birthday = ?
            LIMIT 1';
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([normalizeMasterlistValue($voterId), $birthday]);
    $record = $stmt->fetch();

    if (!$record || !masterlistNameMatches($record, $fullName)) {
        return null;
    }

    return $record;
}
