<?php
// API: Update Incident Status (Admin)

// Start session
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../includes/request.php';
    secureSessionStart();
}

header('Content-Type: application/json');

require 'db_connect.php';
require_once __DIR__ . '/../includes/request.php';
require_once 'auth.php';
$auth = requireAdminApi();

// Check if admin is authenticated
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$incident_id = $data['incident_id'] ?? null;
$status = trim($data['status'] ?? '');
$admin_notes = trim($data['admin_notes'] ?? '');

// Validate input
if (!$incident_id || empty($status)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$valid_statuses = ['pending', 'in_review', 'under_action', 'resolved', 'closed'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('
        UPDATE incidents
        SET status = ?, admin_notes = ?, updated_date = CURRENT_TIMESTAMP
        WHERE id = ?
    ');
    
    $stmt->execute([$status, $admin_notes, $incident_id]);

    $activity = $pdo->prepare(
        'INSERT INTO activity_logs (user_id, role, action, identifier, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    );
    $activity->execute([
        (int)$auth['id'],
        $auth['role'],
        'incident_status_update',
        (string)$incident_id,
        clientIp(),
    ]);
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Incident status updated successfully'
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Database error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to update incident']);
}
?>
