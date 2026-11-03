<?php

const HEALTH_ANNOUNCEMENT_CATEGORIES = [
    'Dental Caravan',
    'Medical Mission',
    'Vaccination',
    'Free Check-up',
    'Mental Health',
    'Nutrition and Fitness',
    'Blood Donation',
    'Other',
];

const HEALTH_ANNOUNCEMENT_STATUSES = [
    'Draft',
    'Published',
    'Cancelled',
    'Archived',
];

function health_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function health_auth_or_fail(): array
{
    require_once dirname(__DIR__) . '/auth.php';
    return requireAdminApi();
}

function health_require_login(): array
{
    require_once dirname(__DIR__) . '/auth.php';
    return requireAuth();
}

function health_input(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function health_trim_null(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function health_validate_category(?string $category): ?string
{
    $category = health_trim_null($category);
    return $category !== null && in_array($category, HEALTH_ANNOUNCEMENT_CATEGORIES, true) ? $category : null;
}

function health_validate_status(?string $status): ?string
{
    $status = health_trim_null($status);
    return $status !== null && in_array($status, HEALTH_ANNOUNCEMENT_STATUSES, true) ? $status : null;
}

function health_validate_date(?string $date): ?string
{
    $date = health_trim_null($date);
    if ($date === null) {
        return null;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt && $dt->format('Y-m-d') === $date ? $date : null;
}

function health_validate_time(?string $time): ?string
{
    $time = health_trim_null($time);
    if ($time === null) {
        return null;
    }

    $formats = ['H:i', 'H:i:s'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $time);
        if ($dt && $dt->format($format) === $time) {
            return substr($time, 0, 5);
        }
    }

    return null;
}

function health_upload_image(?array $file, ?string $existingPath = null): ?string
{
    if (!$file || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid image upload');
    }

    if ((int)$file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Image must be 2MB or smaller');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, and WEBP images are allowed');
    }

    $root = dirname(__DIR__, 2);
    $dir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'health';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create upload directory');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $target = $dir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Failed to save uploaded image');
    }

    if ($existingPath) {
        $existingFull = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $existingPath);
        if (is_file($existingFull)) {
            @unlink($existingFull);
        }
    }

    return 'uploads/health/' . $filename;
}

function health_error_response(Throwable $exception): void
{
    error_log('[Health Announcements] Request failed: ' . $exception->getMessage());
    if ($exception instanceof PDOException) {
        health_response(['success' => false, 'message' => 'Unable to process the health announcement request.'], 500);
    }

    health_response(['success' => false, 'message' => $exception->getMessage()], 400);
}

require_once dirname(__DIR__, 2) . '/includes/request.php';

function health_log(PDO $pdo, array $auth, string $action, ?int $announcementId = null): void
{
    $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, role, action, identifier, ip_address) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $auth['id'] ?? null,
        $auth['role'] ?? null,
        $action,
        $announcementId !== null ? (string)$announcementId : null,
        clientIp(),
    ]);
}

function health_fetch(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM health_announcements WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function health_public_row(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'title' => $row['title'],
        'category' => $row['category'],
        'description' => $row['description'],
        'event_date' => $row['event_date'],
        'start_time' => $row['start_time'],
        'end_time' => $row['end_time'],
        'venue' => $row['venue'],
        'slots' => $row['slots'] !== null ? (int)$row['slots'] : null,
        'requirements' => $row['requirements'],
        'image_path' => $row['image_path'],
        'status' => $row['status'],
        'published_at' => $row['published_at'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ];
}
