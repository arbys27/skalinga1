<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/supabase_storage.php';

$auth = requireAdminApi();
$requestId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$requestId || $requestId < 1) {
    http_response_code(404);
    exit;
}

try {
    $query = $pdo->prepare(
        'SELECT file_path, file_name, print_verified_at, verified_at
         FROM printing_requests
         WHERE request_id = ?
         LIMIT 1'
    );
    $query->execute([$requestId]);
    $request = $query->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        http_response_code(404);
        exit;
    }
    if (empty($request['print_verified_at']) && empty($request['verified_at'])) {
        http_response_code(403);
        exit;
    }

    $filePath = (string)($request['file_path'] ?? '');
    if (str_starts_with($filePath, 'uploads/printing/')) {
        $preview = filter_var($_GET['preview'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $location = 'get_upload.php?type=printing&id=' . $requestId
            . ($preview ? '&preview=1' : '');
        header('Location: ' . $location, true, 302);
        exit;
    }

    if ($filePath === '' || preg_match('/^[A-Za-z0-9._\/-]+$/', $filePath) !== 1) {
        http_response_code(404);
        exit;
    }

    $signedUrl = supabaseStorageCreateSignedUrl($filePath, 600);
    $preview = filter_var($_GET['preview'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if (!$preview && is_string($request['file_name'] ?? null) && $request['file_name'] !== '') {
        $signedUrl .= (str_contains($signedUrl, '?') ? '&' : '?')
            . 'download=' . rawurlencode($request['file_name']);
    }
    header('Cache-Control: private, no-store');
    header('Location: ' . $signedUrl, true, 302);
    exit;
} catch (Throwable $exception) {
    error_log('[Print] Signed document URL failed: ' . $exception->getMessage());
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unable to prepare the document download.';
}
