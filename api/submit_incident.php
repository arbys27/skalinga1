<?php
require_once __DIR__ . '/../includes/request.php';
secureSessionStart();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Youth session required.']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/../includes/supabase_storage.php';
securityRequireCsrf();

$category = isset($_POST['category']) && is_string($_POST['category']) ? trim($_POST['category']) : '';
$description = isset($_POST['description']) && is_string($_POST['description']) ? trim($_POST['description']) : '';
$location = isset($_POST['location']) && is_string($_POST['location']) ? trim($_POST['location']) : '';
$urgency = isset($_POST['urgency']) && is_string($_POST['urgency']) ? trim($_POST['urgency']) : 'low';
$validCategories = ['safety', 'bullying', 'environment', 'health', 'infrastructure', 'emergency', 'other'];
$validUrgencies = ['low', 'medium', 'high', 'emergency'];

if (!in_array($category, $validCategories, true)
    || !in_array($urgency, $validUrgencies, true) || $description === '' || strlen($description) > 10000
    || $location === '' || strlen($location) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid incident details are required.']);
    exit;
}

$uploadedObjectPaths = [];
$photoObjectPaths = [];
$videoObjectPath = null;
$incidentBucket = null;
try {
    $memberQuery = $pdo->prepare(
        'SELECT u.member_id, u.status
         FROM users u
         WHERE u.id = ?
         LIMIT 1'
    );
    $memberQuery->execute([(int)$_SESSION['user_id']]);
    $member = $memberQuery->fetch(PDO::FETCH_ASSOC);
    if (!$member || $member['status'] !== 'active') {
        throw new RuntimeException('An active youth account is required.');
    }

    $files = $_FILES['photo'] ?? null;
    $validatedUploads = [];
    if ($files !== null) {
        if (!is_array($files)) {
            throw new RuntimeException('The attached photos could not be read.');
        }

        $names = is_array($files['name'] ?? null) ? $files['name'] : [$files['name'] ?? ''];
        $tmpNames = is_array($files['tmp_name'] ?? null) ? $files['tmp_name'] : [$files['tmp_name'] ?? ''];
        $errors = is_array($files['error'] ?? null) ? $files['error'] : [$files['error'] ?? UPLOAD_ERR_NO_FILE];
        $sizes = is_array($files['size'] ?? null) ? $files['size'] : [$files['size'] ?? 0];
        if (count($names) > 5) {
            throw new RuntimeException('A maximum of five photos may be attached.');
        }

        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif'
        ];
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);

        foreach ($names as $index => $name) {
            $uploadError = $errors[$index] ?? UPLOAD_ERR_NO_FILE;
            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $tmpName = $tmpNames[$index] ?? null;
            if ($uploadError !== UPLOAD_ERR_OK
                || !is_string($tmpName)
                || !is_uploaded_file($tmpName)
                || (int)($sizes[$index] ?? 0) < 1
                || (int)$sizes[$index] > 5 * 1024 * 1024) {
                throw new RuntimeException('Each attached photo must be 5 MB or smaller and upload successfully.');
            }

            $mimeType = $fileInfo->file($tmpName);
            if (!is_string($mimeType) || !isset($allowedTypes[$mimeType])) {
                throw new RuntimeException('Only JPG, PNG, and GIF photos are allowed.');
            }

            $validatedUploads[] = [
                'tmp_name' => $tmpName,
                'mime_type' => $mimeType,
                'extension' => $allowedTypes[$mimeType],
            ];
        }
    }

    $videoUpload = null;
    $videoFile = $_FILES['video'] ?? null;
    if ($videoFile !== null) {
        if (!is_array($videoFile)) {
            throw new RuntimeException('The attached video could not be read.');
        }

        $uploadError = $videoFile['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($uploadError !== UPLOAD_ERR_NO_FILE) {
            $tmpName = $videoFile['tmp_name'] ?? null;
            $size = (int)($videoFile['size'] ?? 0);
            if ($uploadError !== UPLOAD_ERR_OK
                || !is_string($tmpName)
                || !is_uploaded_file($tmpName)
                || $size < 1
                || $size > 50 * 1024 * 1024) {
                throw new RuntimeException('The attached video must be 50 MB or smaller and upload successfully.');
            }

            $allowedVideoTypes = [
                'video/mp4' => 'mp4',
                'video/quicktime' => 'mov',
            ];
            $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
            if (!is_string($mimeType) || !isset($allowedVideoTypes[$mimeType])) {
                throw new RuntimeException('Only MP4 and MOV video clips are allowed.');
            }

            $videoUpload = [
                'tmp_name' => $tmpName,
                'mime_type' => $mimeType,
                'extension' => $allowedVideoTypes[$mimeType],
            ];
        }
    }

    if ($validatedUploads || $videoUpload !== null) {
        $incidentBucket = supabaseIncidentStorageBucket();
    }

    foreach ($validatedUploads as $upload) {
        $objectPath = 'incidents/' . bin2hex(random_bytes(16)) . '.' . $upload['extension'];
        $uploadedObjectPaths[] = $objectPath;
        $photoObjectPaths[] = $objectPath;
        supabaseStorageUpload($objectPath, $upload['tmp_name'], $upload['mime_type'], $incidentBucket);
    }

    if ($videoUpload !== null) {
        $videoObjectPath = 'incidents/' . bin2hex(random_bytes(16)) . '.' . $videoUpload['extension'];
        $uploadedObjectPaths[] = $videoObjectPath;
        supabaseStorageUpload(
            $videoObjectPath,
            $videoUpload['tmp_name'],
            $videoUpload['mime_type'],
            $incidentBucket
        );
    }

    $photoPath = $photoObjectPaths ? json_encode($photoObjectPaths, JSON_THROW_ON_ERROR) : null;
    $pdo->beginTransaction();
    $insert = $pdo->prepare(
        'INSERT INTO incidents
            (member_id, category, description, location, urgency, status, photo_path, video_path)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         RETURNING id'
    );
    $insert->execute([
        $member['member_id'],
        $category,
        $description,
        $location,
        $urgency,
        'pending',
        $photoPath,
        $videoObjectPath
    ]);
    $incidentId = (int)$insert->fetchColumn();
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Incident report submitted successfully.',
        'incident_id' => $incidentId
    ], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($uploadedObjectPaths as $objectPath) {
        try {
            supabaseStorageDelete($objectPath, $incidentBucket);
        } catch (Throwable $cleanupException) {
            error_log('[Incident] Supabase Storage cleanup failed: ' . $cleanupException->getMessage());
        }
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($uploadedObjectPaths as $objectPath) {
        try {
            supabaseStorageDelete($objectPath, $incidentBucket);
        } catch (Throwable $cleanupException) {
            error_log('[Incident] Supabase Storage cleanup failed: ' . $cleanupException->getMessage());
        }
    }
    error_log('[Incident] Verified submission failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to save the incident report.']);
}
