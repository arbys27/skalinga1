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
        'SELECT file_path, file_name
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
    $filePath = (string)($request['file_path'] ?? '');
    if (str_starts_with($filePath, 'uploads/printing/')) {
        $preview = ($_GET['preview'] ?? '') === '1';
        $location = 'get_upload.php?type=printing&id=' . $requestId
            . ($preview ? '&preview=1' : '');
        header('Location: ' . $location, true, 302);
        exit;
    }

    $segments = explode('/', $filePath);
    if ($filePath === '' || preg_match('/^[A-Za-z0-9._\/-]+$/', $filePath) !== 1
        || in_array('', $segments, true) || in_array('.', $segments, true)
        || in_array('..', $segments, true)) {
        http_response_code(404);
        exit;
    }

    $signedUrl = supabaseStorageCreateSignedUrl($filePath, 600);
    $curl = curl_init($signedUrl);
    if ($curl === false) {
        throw new RuntimeException('Unable to initialize the Supabase document download.');
    }
    curl_setopt_array($curl, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $fileContents = curl_exec($curl);
    $downloadError = curl_error($curl);
    $downloadStatus = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $contentType = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    curl_close($curl);

    if ($fileContents === false || $downloadError !== '') {
        throw new RuntimeException(
            'Supabase Storage document fetch failed: '
            . ($downloadError !== '' ? $downloadError : 'no response')
        );
    }
    if ($downloadStatus < 200 || $downloadStatus >= 300) {
        $detail = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $fileContents) ?? '');
        error_log(
            '[Print] Supabase document fetch failed for request ' . $requestId
            . ' (HTTP ' . $downloadStatus . ')'
            . ($detail !== '' ? ': ' . substr($detail, 0, 500) : '.')
        );
        if ($downloadStatus === 404) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Document not found in storage.';
            exit;
        }
        throw new RuntimeException('Supabase Storage document fetch returned HTTP ' . $downloadStatus . '.');
    }

    $fileName = basename((string)($request['file_name'] ?? 'document'));
    $fileName = preg_replace('/[\x00-\x1F\x7F]/', '', $fileName) ?: 'document';
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $preview = ($_GET['preview'] ?? '') === '1';
    $previewContentTypes = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'txt' => 'text/plain',
    ];
    $inline = $preview && isset($previewContentTypes[$extension]);
    $responseContentType = $inline
        ? $previewContentTypes[$extension]
        : trim(explode(';', $contentType, 2)[0]);
    if (preg_match('~^[A-Za-z0-9.+-]+/[A-Za-z0-9.+-]+$~D', $responseContentType) !== 1) {
        $responseContentType = 'application/octet-stream';
    }
    $fallbackName = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName) ?: 'document';

    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Content-Type: ' . $responseContentType);
    header('Content-Length: ' . strlen($fileContents));
    header(
        'Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
        . '; filename="' . $fallbackName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName)
    );
    echo $fileContents;
} catch (Throwable $exception) {
    error_log('[Print] Document proxy failed: ' . $exception->getMessage());
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unable to prepare the document download.';
}
