<?php

const PUBLIC_DISCLOSURE_CATEGORIES = [
    'Budget and Finance',
    'Programs and Projects',
    'Memos and Resolutions',
    'Other',
];

function disclosure_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function disclosure_error_response(Throwable $exception): void
{
    error_log('[Public Disclosures] Request failed: ' . $exception->getMessage());
    if ($exception instanceof PDOException) {
        disclosure_response(['success' => false, 'message' => 'Unable to process the disclosure request.'], 500);
    }

    disclosure_response(['success' => false, 'message' => $exception->getMessage()], 400);
}

function disclosure_admin_auth(): array
{
    require_once dirname(__DIR__) . '/auth.php';
    return requireAdminApi();
}

function disclosure_public_auth(): array
{
    require_once dirname(__DIR__) . '/auth.php';
    return requireAuth(['youth']);
}

function disclosure_text(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function disclosure_category(mixed $value): ?string
{
    $value = disclosure_text($value);
    return $value !== null && in_array($value, PUBLIC_DISCLOSURE_CATEGORIES, true) ? $value : null;
}

function disclosure_upload(?array $file): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Please select a document to upload');
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The document upload failed');
    }
    if ((int)$file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('Documents must be 10MB or smaller');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
    if ($finfo) {
        finfo_close($finfo);
    }
    $allowed = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only PDF, Word, Excel, JPG, and PNG files are allowed');
    }

    $root = dirname(__DIR__, 2);
    $dir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'disclosures';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create document upload directory');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $target = $dir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Failed to save the uploaded document');
    }

    return [
        'original_name' => basename((string)$file['name']),
        'file_path' => 'uploads/disclosures/' . $filename,
        'mime_type' => (string)$mime,
        'file_size' => (int)$file['size'],
    ];
}

function disclosure_row(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'title' => $row['title'],
        'category' => $row['category'],
        'description' => $row['description'],
        'original_name' => $row['original_name'],
        'file_path' => $row['file_path'],
        'mime_type' => $row['mime_type'],
        'file_size' => (int)$row['file_size'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
    ];
}
