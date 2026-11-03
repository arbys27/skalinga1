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
    // Get photo path to delete file
    $stmt = $pdo->prepare('SELECT photo_path FROM incidents WHERE id = ?');
    $stmt->execute([$incident_id]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($incident && $incident['photo_path']) {
        $file_path = '../' . $incident['photo_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Delete incident
    $stmt = $pdo->prepare('DELETE FROM incidents WHERE id = ?');
    $stmt->execute([$incident_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Incident deleted successfully'
    ]);
    
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to delete incident']);
}
?>
