<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
verificationRequireMethod('GET');
$auth = requireAuth(['youth']);
$tokenId = filter_var($_GET['token_id'] ?? null, FILTER_VALIDATE_INT);
if (!$tokenId || $tokenId < 1) {
    verificationResponse(['success' => false, 'message' => 'A valid verification request is required.'], 400);
}
try {
    $query = $pdo->prepare(
        'SELECT id, service_type, status, expires_at, scanned_by_admin_id,
                code_expires_at, code_verified_at
         FROM service_verification_tokens
         WHERE id = ? AND user_id = ? AND service_type IN (\'borrow\', \'return\')
         LIMIT 1'
    );
    $query->execute([$tokenId, (int)$auth['id']]);
    $token = $query->fetch(PDO::FETCH_ASSOC);
    if (!$token) {
        verificationResponse(['success' => false, 'message' => 'Verification request not found.'], 404);
    }
    $requestQuery = $pdo->prepare('SELECT request_id, status FROM borrow_requests WHERE verification_token_id = ? LIMIT 1');
    $requestQuery->execute([$tokenId]);
    $request = $requestQuery->fetch(PDO::FETCH_ASSOC);
    $scanned = $token['scanned_by_admin_id'] !== null
        && is_string($token['code_expires_at'])
        && !verificationIsExpired($token['code_expires_at'])
        && !verificationIsExpired((string)$token['expires_at']);
    verificationResponse([
        'success' => true,
        'scanned' => $scanned,
        'code_verified' => $token['code_verified_at'] !== null,
        'status' => (string)$token['status'],
        'request_status' => $request['status'] ?? null,
        'request_id' => isset($request['request_id']) ? (int)$request['request_id'] : null,
        'code_expires_in' => $scanned ? max(0, strtotime((string)$token['code_expires_at']) - time()) : null,
    ]);
} catch (Throwable $exception) {
    error_log('[Borrow Verification] Status lookup failed: ' . $exception->getMessage());
    verificationResponse(['success' => false, 'message' => 'Unable to retrieve verification status.'], 500);
}
