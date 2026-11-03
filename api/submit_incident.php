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

$savedPaths = [];
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
    if (is_array($files) && !empty($files['name'])) {
        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
        $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];
        if (count($names) > 5) {
            throw new RuntimeException('A maximum of five photos may be attached.');
        }

        $uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'incidents';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException('Unable to prepare the photo upload directory.');
        }
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif'
        ];
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);

        foreach ($names as $index => $name) {
            if (($errors[$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (($errors[$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                || !is_string($tmpNames[$index] ?? null)
                || (int)($sizes[$index] ?? 0) < 1
                || (int)$sizes[$index] > 5 * 1024 * 1024) {
                throw new RuntimeException('Each attached photo must be under 5 MB and upload successfully.');
            }
            $mime = $fileInfo->file($tmpNames[$index]);
            if (!is_string($mime) || !isset($allowedTypes[$mime])) {
                throw new RuntimeException('Only JPG, PNG, and GIF photos are allowed.');
            }

            $filename = 'incident_' . bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mime];
            $target = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
            if (!move_uploaded_file($tmpNames[$index], $target)) {
                throw new RuntimeException('Unable to save an attached photo.');
            }
            $savedPaths[] = 'uploads/incidents/' . $filename;
        }
    }

    $photoPath = $savedPaths ? json_encode($savedPaths, JSON_THROW_ON_ERROR) : null;
    $pdo->beginTransaction();
    $insert = $pdo->prepare(
        'INSERT INTO incidents
            (member_id, category, description, location, urgency, status, photo_path)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         RETURNING id'
    );
    $insert->execute([
        $member['member_id'],
        $category,
        $description,
        $location,
        $urgency,
        'pending',
        $photoPath
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
    foreach ($savedPaths as $path) {
        $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_file($absolutePath)) {
            unlink($absolutePath);
        }
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($savedPaths as $path) {
        $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_file($absolutePath)) {
            unlink($absolutePath);
        }
    }
    error_log('[Incident] Verified submission failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to save the incident report.']);
}
