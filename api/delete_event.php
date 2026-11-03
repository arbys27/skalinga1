<?php
// delete_event.php - Delete event from database
header('Content-Type: application/json');

require 'db_connect.php';
require_once 'auth.php';
requireAdminApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    // Get event ID
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception("Invalid JSON input");
    }
    
    $eventId = $input['id'] ?? '';
    
    if (!$eventId) {
        throw new Exception("Event ID is required");
    }
    
    // Check if event exists and get image path for cleanup
    $check = $pdo->prepare("SELECT image_path FROM events WHERE event_id = ?");
    $check->execute([$eventId]);
    if ($check->rowCount() === 0) {
        throw new Exception("Event not found");
    }
    
    $row = $check->fetch(PDO::FETCH_ASSOC);
    $imagePath = $row['image_path'];
    
    // Delete event from database
    $stmt = $pdo->prepare("DELETE FROM events WHERE event_id = ?");
    $stmt->execute([$eventId]);
    
    // Delete image file if exists
    if ($imagePath) {
        $filePath = '../' . $imagePath;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Event deleted successfully',
        'id' => $eventId
    ]);
    
} catch (Exception $e) {
    error_log('[Events] Deletion failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to delete the event.'
    ]);
}

?>
