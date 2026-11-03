<?php
// API: Get User's Own Incidents
require_once __DIR__ . '/../includes/request.php';
secureSessionStart();
header('Content-Type: application/json');

require 'db_connect.php';

// Check if user is authenticated
if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

$member_id = $_SESSION['member_id'];

try {
    $stmt = $pdo->prepare('
        SELECT id, member_id, category, description, location, urgency, status, 
               photo_path, submitted_date, admin_notes
        FROM incidents
        WHERE member_id = ?
        ORDER BY submitted_date DESC
    ');
    
    $stmt->execute([$member_id]);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($incidents as &$incident) {
        $photos = json_decode((string)($incident['photo_path'] ?? ''), true);
        if (!is_array($photos)) {
            $photos = !empty($incident['photo_path']) ? [$incident['photo_path']] : [];
        }
        $photos = array_values($photos);
        $incident['photo_path'] = json_encode(array_values(array_map(
            static fn(int $index): string => 'api/get_upload.php?type=incident&id=' . (int)$incident['id'] . '&file=' . $index,
            array_keys($photos)
        )), JSON_UNESCAPED_SLASHES);
    }
    unset($incident);
    
    echo json_encode([
        'success' => true,
        'data' => $incidents
    ]);
    
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to fetch incidents']);
}
?>
