<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/verification/common.php';

verificationRequireMethod('GET');
$auth = requireAuth(['youth', 'admin', 'sk_official']);
$requestId = filter_var($_GET['request_id'] ?? null, FILTER_VALIDATE_INT);
if (!$requestId || $requestId < 1) {
    verificationResponse(['success' => false, 'message' => 'A valid print request is required.'], 400);
}

try {
    $query = $pdo->prepare(
        "SELECT pr.request_id, pr.member_id, pr.member_name, pr.document_title, pr.print_type,
                pr.paper_size, pr.copies, pr.status, pr.file_name, pr.print_verified_at,
                pr.print_verified_by_admin_id, pr.verified_at,
                svt.id AS token_id, svt.expires_at, svt.scanned_by_admin_id, svt.code_expires_at,
                svt.code_verified_at
         FROM printing_requests pr
         LEFT JOIN service_verification_tokens svt
           ON svt.id = pr.print_verification_token_id
         WHERE pr.request_id = ?
         LIMIT 1"
    );
    $query->execute([$requestId]);
    $request = $query->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        verificationResponse(['success' => false, 'message' => 'Print request not found.'], 404);
    }

    if ($auth['role'] === 'youth') {
        $owner = $pdo->prepare('SELECT member_id FROM users WHERE id = ? LIMIT 1');
        $owner->execute([(int)$auth['id']]);
        if ((string)$owner->fetchColumn() !== (string)$request['member_id']) {
            verificationResponse(['success' => false, 'message' => 'Print request not found.'], 404);
        }
    }

    $verified = ($request['print_verified_at'] ?? null) !== null
        || ($request['verified_at'] ?? null) !== null;
    $scanned = ($request['scanned_by_admin_id'] ?? null) !== null
        && ($request['code_expires_at'] ?? null) !== null
        && !verificationIsExpired((string)$request['code_expires_at'])
        && !verificationIsExpired((string)$request['expires_at']);
    $codeVerified = ($request['code_verified_at'] ?? null) !== null;

    verificationResponse([
        'success' => true,
        'request_id' => (int)$request['request_id'],
        'status' => (string)$request['status'],
        'scanned' => $scanned,
        'code_expires_in' => $scanned
            ? max(0, strtotime((string)$request['code_expires_at']) - time())
            : null,
        'code_verified' => $codeVerified || $verified,
        'verified' => $verified,
        'details' => $verified ? [
            'request_id' => (int)$request['request_id'],
            'member_name' => (string)$request['member_name'],
            'member_id' => (string)$request['member_id'],
            'document_title' => (string)$request['document_title'],
            'print_type' => (string)$request['print_type'],
            'paper_size' => (string)$request['paper_size'],
            'copies' => (int)$request['copies'],
            'file_name' => (string)$request['file_name'],
            'file_url' => 'api/get_upload.php?type=printing&id=' . (int)$request['request_id'],
        ] : null,
    ]);
} catch (Throwable $exception) {
    error_log('[Print Verification] Status lookup failed: ' . $exception->getMessage());
    verificationResponse(['success' => false, 'message' => 'Unable to retrieve print verification status.'], 500);
}
