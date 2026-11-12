<?php
header('Content-Type: application/json');
require_once 'auth.php';
requireAdminApi();
require_once __DIR__ . '/../includes/supabase_storage.php';

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

    $lookup = $pdo->prepare(
        'SELECT file_path FROM printing_requests WHERE request_id = ? LIMIT 1'
    );
    $lookup->execute([$request_id]);
    $request = $lookup->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM printing_requests WHERE request_id = ?");
    $stmt->execute([$request_id]);
    
    if ($stmt->rowCount() > 0) {
        $filePath = (string)($request['file_path'] ?? '');
        if ($filePath !== '' && !str_starts_with($filePath, 'uploads/printing/')) {
            try {
                supabaseStorageDelete($filePath);
            } catch (Throwable $cleanupException) {
                error_log('[Print] Storage object cleanup failed: ' . $cleanupException->getMessage());
            }
        }
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
