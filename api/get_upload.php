<?php

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/request.php';
require_once __DIR__ . '/../includes/supabase_storage.php';

authStartSession();
$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
$media = is_string($_GET['media'] ?? null) ? $_GET['media'] : 'photo';
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$auth = currentAuth();
$isAdmin = $auth !== null
    && in_array($auth['role'], ['admin', 'sk_official'], true)
    && !empty($_SESSION['admin_authenticated']);
$userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);

if (!in_array($type, ['printing', 'incident'], true) || !$id || $id < 1) {
    http_response_code(404);
    exit;
}

try {
    if ($type === 'printing') {
        $stmt = $pdo->prepare(
            'SELECT member_id, file_path, print_verified_at, verified_at
             FROM printing_requests WHERE request_id = ?'
        );
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        $filePath = $record['file_path'] ?? '';
        if (!$record) {
            http_response_code(404);
            exit;
        }
        if (empty($record['print_verified_at']) && empty($record['verified_at'])) {
            http_response_code(403);
            exit;
        }

        if (!$isAdmin) {
            if (!$userId || $userId < 1) {
                http_response_code(401);
                exit;
            }
            $memberQuery = $pdo->prepare('SELECT member_id FROM users WHERE id = ?');
            $memberQuery->execute([$userId]);
            if ($memberQuery->fetchColumn() !== $record['member_id']) {
                http_response_code(403);
                exit;
            }
        }
    } else {
        if (!in_array($media, ['photo', 'video'], true)) {
            http_response_code(404);
            exit;
        }
        $stmt = $pdo->prepare('SELECT member_id, photo_path, video_path FROM incidents WHERE id = ?');
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            http_response_code(404);
            exit;
        }

        if (!$isAdmin) {
            if (!$userId || $userId < 1) {
                http_response_code(401);
                exit;
            }
            $memberQuery = $pdo->prepare('SELECT member_id FROM users WHERE id = ?');
            $memberQuery->execute([$userId]);
            if ($memberQuery->fetchColumn() !== $record['member_id']) {
                http_response_code(403);
                exit;
            }
        }

        if ($media === 'video') {
            $filePath = $record['video_path'] ?? '';
        } else {
            $paths = json_decode((string)($record['photo_path'] ?? ''), true);
            if (!is_array($paths)) {
                $paths = [$record['photo_path']];
            }
            $fileIndex = filter_var($_GET['file'] ?? 0, FILTER_VALIDATE_INT);
            if ($fileIndex === false || $fileIndex < 0 || !isset($paths[$fileIndex])) {
                http_response_code(404);
                exit;
            }
            $filePath = $paths[$fileIndex];
        }
    }

    if ($type === 'incident' && is_string($filePath) && str_starts_with($filePath, 'incidents/')) {
        $allowedPathPattern = $media === 'video'
            ? '/^incidents\/[a-f0-9]{32}\.(?:mp4|mov)$/D'
            : '/^incidents\/[a-f0-9]{32}\.(?:jpg|png|gif)$/D';
        if (preg_match($allowedPathPattern, $filePath) !== 1) {
            http_response_code(404);
            exit;
        }

        $signedUrl = supabaseStorageCreateSignedUrl($filePath, 600, supabaseIncidentStorageBucket());
        header('Cache-Control: private, no-store');
        header('Location: ' . $signedUrl, true, 302);
        exit;
    }

    $expectedPrefix = 'uploads/' . ($type === 'incident' ? 'incidents/' : 'printing/');
    if (!is_string($filePath) || !str_starts_with($filePath, $expectedPrefix)) {
        http_response_code(404);
        exit;
    }
    $filename = substr($filePath, strlen($expectedPrefix));
    if ($filename === '' || basename($filename) !== $filename
        || !preg_match('/^[a-zA-Z0-9_.-]+$/', $filename)) {
        http_response_code(404);
        exit;
    }

    $root = dirname(__DIR__);
    $directory = realpath($root . DIRECTORY_SEPARATOR . $expectedPrefix);
    $fullPath = $directory === false ? false : realpath($directory . DIRECTORY_SEPARATOR . $filename);
    if ($fullPath === false || !str_starts_with($fullPath, $directory . DIRECTORY_SEPARATOR) || !is_file($fullPath)) {
        http_response_code(404);
        exit;
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($fullPath);
    if (!is_string($mimeType)) {
        throw new RuntimeException('Unable to determine uploaded file type.');
    }
    if ($type === 'incident' && !in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif'], true)) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($fullPath));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    $preview = filter_var($_GET['preview'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $disposition = $type === 'incident' || ($type === 'printing' && $preview)
        ? 'inline'
        : 'attachment';
    header('Content-Disposition: ' . $disposition . '; filename="' . addcslashes($filename, '"\\') . '"');
    readfile($fullPath);
} catch (Throwable $exception) {
    error_log('[Upload] Private download failed: ' . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unable to retrieve this file.';
}
