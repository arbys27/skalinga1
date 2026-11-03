<?php
// update_event.php - Update event with image upload support
header('Content-Type: application/json');

require 'db_connect.php';
require_once 'auth.php';
requireAdminApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    // Get event data
    $id = $_POST['id'] ?? '';
    $title = $_POST['title'] ?? '';
    $date = $_POST['date'] ?? '';
    $startTime = $_POST['startTime'] ?? '';
    $endTime = $_POST['endTime'] ?? '';
    $location = $_POST['location'] ?? '';
    $type = $_POST['type'] ?? '';
    $description = $_POST['description'] ?? '';
    $capacity = $_POST['capacity'] ?? '';
    $regLink = $_POST['regLink'] ?? '';
    $status = $_POST['status'] ?? '';

    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Event ID is required']);
        exit;
    }

    // Check if event exists and get current image
    $result = $pdo->prepare("SELECT image_path FROM events WHERE event_id = ?");
    $result->execute([$id]);
    if ($result->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Event not found']);
        exit;
    }
    
    $currentEvent = $result->fetch(PDO::FETCH_ASSOC);
    $imagePath = $currentEvent['image_path'];

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $uploadDir = '../assets/images/events/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Delete old image if exists
        if ($imagePath && file_exists($uploadDir . basename($imagePath))) {
            unlink($uploadDir . basename($imagePath));
        }

        $fileName = 'event_' . time() . '_' . basename($_FILES['image']['name']);
        $uploadPath = $uploadDir . $fileName;
        $relativePath = 'assets/images/events/' . $fileName;

        // Validate image type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Invalid image type']);
            exit;
        }

        // Validate file size (max 5MB)
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Image too large (max 5MB)']);
            exit;
        }

        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            $imagePath = $relativePath;
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to upload image']);
            exit;
        }
    }

    // Build update with parameters
    $updateFields = [];
    $params = [];
    
    if ($title) {
        $updateFields[] = "title = ?";
        $params[] = $title;
    }
    if ($date) {
        $updateFields[] = "date = ?";
        $params[] = $date;
    }
    if ($startTime) {
        $updateFields[] = "start_time = ?";
        $params[] = $startTime;
    }
    if ($endTime) {
        $updateFields[] = "end_time = ?";
        $params[] = $endTime;
    }
    if ($location) {
        $updateFields[] = "location = ?";
        $params[] = $location;
    }
    if ($type) {
        $updateFields[] = "event_type = ?";
        $params[] = $type;
    }
    if ($capacity) {
        $updateFields[] = "capacity = ?";
        $params[] = $capacity;
    }
    if (isset($_POST['description'])) {
        $updateFields[] = "description = ?";
        $params[] = $description;
    }
    if (isset($_POST['regLink'])) {
        $updateFields[] = "registration_link = ?";
        $params[] = $regLink;
    }
    if ($status) {
        $updateFields[] = "status = ?";
        $params[] = $status;
    }
    
    $updateFields[] = "image_path = ?";
    $params[] = $imagePath;
    
    $updateFields[] = "updated_at = CURRENT_TIMESTAMP";

    if (empty($updateFields) || count($updateFields) <= 1) {
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        exit;
    }

    // Add event ID to params for WHERE clause
    $params[] = $id;

    // Execute update
    $sql = "UPDATE events SET " . implode(", ", $updateFields) . " WHERE event_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'Event updated successfully',
        'id' => $id
    ]);

} catch (Exception $e) {
    error_log('[Events] Update failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to update the event.']);
}

?>
