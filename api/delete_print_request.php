<?php
header('Content-Type: application/json');
require_once 'auth.php';
requireAdminApi();

// Check if user is logged in (admin session required)
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    require_once 'db_connect.php';
    
    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    $request_id = $data['request_id'] ?? null;

    if (!$request_id) {
        echo json_encode(['success' => false, 'message' => 'Request ID is required']);
        exit;
    }

    // Delete the print request
    $stmt = $pdo->prepare("DELETE FROM printing_requests WHERE request_id = ?");
    $stmt->execute([$request_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Print request deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
    }
    
} catch (Exception $e) {
    error_log('[Print] Request deletion failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to delete the print request.']);
}
?>
