<?php
// API: Delete Incident (Admin)

// Start session
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../includes/request.php';
    secureSessionStart();
}

header('Content-Type: application/json');

require 'db_connect.php';
require_once 'auth.php';
require_once __DIR__ . '/../includes/supabase_storage.php';
requireAdminApi();

// Check if admin is authenticated
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$incident_id = $data['incident_id'] ?? null;

if (!$incident_id) {
    echo json_encode(['success' => false, 'error' => 'Missing incident ID']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT photo_path, video_path FROM incidents WHERE id = ?');
    $stmt->execute([$incident_id]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$incident) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Incident not found']);
        exit;
    }

    $photoPaths = json_decode((string)($incident['photo_path'] ?? ''), true);
    if (!is_array($photoPaths)) {
        $photoPaths = !empty($incident['photo_path']) ? [$incident['photo_path']] : [];
    }
    if (!empty($incident['video_path'])) {
        $photoPaths[] = $incident['video_path'];
    }

    $stmt = $pdo->prepare('DELETE FROM incidents WHERE id = ?');
    $stmt->execute([$incident_id]);

    $cleanupWarning = false;
    foreach ($photoPaths as $photoPath) {
        if (!is_string($photoPath)) {
            continue;
        }

        try {
            if (preg_match('/^incidents\/[a-f0-9]{32}\.(?:jpg|png|gif|mp4|mov)$/D', $photoPath) === 1) {
                supabaseStorageDelete($photoPath, supabaseIncidentStorageBucket());
            } elseif (str_starts_with($photoPath, 'uploads/incidents/')) {
                $filename = substr($photoPath, strlen('uploads/incidents/'));
                if ($filename !== '' && basename($filename) === $filename) {
                    $localPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads'
                        . DIRECTORY_SEPARATOR . 'incidents' . DIRECTORY_SEPARATOR . $filename;
                    if (is_file($localPath) && !unlink($localPath)) {
                        throw new RuntimeException('Unable to remove a local incident attachment.');
                    }
                }
            }
        } catch (Throwable $cleanupException) {
            $cleanupWarning = true;
            error_log('[Incident] Attachment cleanup failed: ' . $cleanupException->getMessage());
        }
    }

    $response = [
        'success' => true,
        'message' => 'Incident deleted successfully',
    ];
    if ($cleanupWarning) {
        $response['warning'] = 'Incident deleted, but some attached files could not be removed.';
    }
    echo json_encode($response);
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to delete incident']);
} catch (Throwable $e) {
    error_log('[Incident] Failed to delete report or its attachments: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete incident attachments.']);
}
?>
