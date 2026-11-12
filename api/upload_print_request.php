<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/request.php';
secureSessionStart();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Youth session required.']);
    exit;
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/../includes/supabase_storage.php';
securityRequireCsrf();

$title = isset($_POST['document_title']) && is_string($_POST['document_title'])
    ? trim($_POST['document_title'])
    : '';
$printType = $_POST['print_type'] ?? '';
$paperSize = $_POST['paper_size'] ?? '';
$copies = filter_var($_POST['copies'] ?? null, FILTER_VALIDATE_INT);
$storageObjectPath = null;
$storageUploaded = false;

if ($title === '' || strlen($title) > 120
    || !in_array($printType, ['Black & White', 'Colored'], true)
    || !in_array($paperSize, ['A4', 'Short', 'Long'], true)
    || !$copies || $copies < 1 || $copies > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid print details are required.']);
    exit;
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid document upload is required.']);
    exit;
}

$file = $_FILES['document'];
$originalName = basename((string)$file['name']);
$fileSize = (int)$file['size'];
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedTypes = [
    'pdf' => ['application/pdf'],
    'doc' => ['application/msword', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png']
];

if (!array_key_exists($extension, $allowedTypes) || $fileSize < 1 || $fileSize > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File type is not allowed or exceeds the 10 MB limit.']);
    exit;
}

$fileInfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $fileInfo->file((string)$file['tmp_name']);
if (!is_string($mimeType) || !in_array($mimeType, $allowedTypes[$extension], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'The uploaded file content does not match its file type.']);
    exit;
}

try {
    $memberQuery = $pdo->prepare(
        'SELECT u.member_id, u.status, yp.firstname, yp.lastname,
                km.first_name, km.middle_name, km.last_name
         FROM users u
         LEFT JOIN youth_profiles yp ON yp.user_id = u.id
         LEFT JOIN kk_masterlist km ON km.id = u.masterlist_id
         WHERE u.id = ?
         LIMIT 1'
    );
    $memberQuery->execute([(int)$_SESSION['user_id']]);
    $member = $memberQuery->fetch(PDO::FETCH_ASSOC);
    if (!$member || $member['status'] !== 'active') {
        throw new RuntimeException('An active youth account is required.');
    }

    $memberName = trim((string)$member['firstname'] . ' ' . (string)$member['lastname']);
    if ($memberName === '') {
        $memberName = trim(implode(' ', array_filter([
            $member['first_name'] ?? '',
            $member['middle_name'] ?? '',
            $member['last_name'] ?? ''
        ], static fn($part) => trim((string)$part) !== '')));
    }
    if ($memberName === '') {
        throw new RuntimeException('Member profile is incomplete.');
    }

    $storageObjectPath = 'SK-' . date('Y') . '-' . bin2hex(random_bytes(12)) . '.' . $extension;
    supabaseStorageUpload($storageObjectPath, (string)$file['tmp_name'], $mimeType);
    $storageUploaded = true;

    $pdo->beginTransaction();
    $insert = $pdo->prepare(
        "INSERT INTO printing_requests
            (member_id, member_name, document_title, file_path, file_name, file_size,
             print_type, paper_size, copies, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', CURRENT_TIMESTAMP)"
         . " RETURNING request_id"
    );
    $insert->execute([
        $member['member_id'],
        $memberName,
        $title,
        $storageObjectPath,
        $originalName,
        $fileSize,
        $printType,
        $paperSize,
        $copies
    ]);
    $requestId = (int)$insert->fetchColumn();
    $pdo->prepare(
        'INSERT INTO activity_logs (user_id, role, action, identifier, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([
        (int)$_SESSION['user_id'],
        'youth',
        'print_request_submitted',
        'print:' . (string)$requestId,
        securityClientIp(),
    ]);
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Print request submitted successfully.',
        'request_id' => $requestId,
        'data' => [
            'request_id' => $requestId,
            'document_title' => $title
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($storageUploaded && $storageObjectPath !== null) {
        try {
            supabaseStorageDelete($storageObjectPath);
        } catch (Throwable $cleanupException) {
            error_log('[Print] Supabase Storage cleanup failed: ' . $cleanupException->getMessage());
        }
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($storageUploaded && $storageObjectPath !== null) {
        try {
            supabaseStorageDelete($storageObjectPath);
        } catch (Throwable $cleanupException) {
            error_log('[Print] Supabase Storage cleanup failed: ' . $cleanupException->getMessage());
        }
    }
    error_log('[Print] Verified request failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to save the print request.']);
}
