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

function submittedMasterlistName(array $input): string
{
    if (array_key_exists('first_name', $input) || array_key_exists('middle_name', $input) || array_key_exists('last_name', $input)) {
        $first = is_string($input['first_name'] ?? null) ? trim($input['first_name']) : '';
        $middle = is_string($input['middle_name'] ?? null) ? trim($input['middle_name']) : '';
        $last = is_string($input['last_name'] ?? null) ? trim($input['last_name']) : '';
        if ($first === '' || $last === '') {
            return '';
        }

        return normalizeMasterlistName(implode(' ', array_filter(
            [$first, $middle, $last],
            static fn(string $part): bool => $part !== ''
        )));
    }

    return is_string($input['full_name'] ?? null)
        ? normalizeMasterlistName($input['full_name'])
        : '';
}

function masterlistClientIp(): string
{
    return clientIp();
}

function enforceMasterlistRateLimit(PDO $pdo, string $attemptType, int $maxAttempts = 10): void
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*), MAX(attempted_at) FROM login_attempts
         WHERE ip_address = ? AND attempt_type = ?
           AND attempted_at >= (CURRENT_TIMESTAMP - INTERVAL \'1 hour\')'
    );
    $stmt->execute([masterlistClientIp(), $attemptType]);
    $attempts = $stmt->fetch(PDO::FETCH_NUM);
    $attemptCount = (int)($attempts[0] ?? 0);
    $lastAttempt = isset($attempts[1]) ? strtotime((string)$attempts[1]) : false;
    $lockoutSeconds = min(1800, 30 * (2 ** max(0, $attemptCount - 1)));

    if ($attemptCount >= $maxAttempts && $lastAttempt !== false && time() - $lastAttempt < $lockoutSeconds) {
        $waitSeconds = $lockoutSeconds - (time() - $lastAttempt);
        masterlistJsonResponse([
            'success' => false,
            'message' => 'Too many attempts. Please wait ' . $waitSeconds . ' seconds before trying again.'
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

function masterlistFindRecord(
    PDO $pdo,
    string $fullName,
    string $birthday,
    bool $forUpdate = false,
    ?int $masterlistId = null
): ?array
{
    $sql = 'SELECT id, voter_id, first_name, last_name, middle_name, birthday, age, sex, address,
                   purok_or_barangay, is_claimed, claimed_by_user_id
            FROM kk_masterlist
            WHERE birthday = ?';
    $params = [$birthday];
    if ($masterlistId !== null) {
        $sql .= ' AND id = ?';
        $params[] = $masterlistId;
    }
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    $matches = array_values(array_filter(
        $records,
        static fn(array $record): bool => masterlistNameMatches($record, $fullName)
    ));

    return count($matches) === 1 ? $matches[0] : null;
}
