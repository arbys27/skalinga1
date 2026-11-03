<?php
/**
 * Delete Youth Member
 * Deletes a youth member from the database
 * Tracks which admin deleted the member
 */

require_once __DIR__ . '/../includes/request.php';
secureSessionStart();
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'auth.php';
requireAdminApi();

// Check if admin is authenticated
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get POST data
$member_id = isset($_POST['member_id']) ? trim($_POST['member_id']) : '';

// Admin info
$admin_username = $_SESSION['admin_username'];

// Validate input
if (empty($member_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Member ID is required']);
    exit;
}

try {
    // Get user ID from member_id
    $userStmt = $pdo->prepare("SELECT id FROM users WHERE member_id = ?");
    $userStmt->execute([$member_id]);
    
    if ($userStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Youth member not found']);
        exit;
    }
    
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    $user_id = $user['id'];
    
    // Delete user (cascade will delete profile)
    $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $deleteStmt->execute([$user_id]);
    
    // Log deletion
    error_log("[Youth Deletion] Admin '$admin_username' deleted youth member: $member_id");
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Youth member deleted successfully',
        'deleted_by' => $admin_username
    ]);
    
} catch (Exception $e) {
    error_log("[Delete Youth Error] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while deleting youth member'
    ]);
}
?>
