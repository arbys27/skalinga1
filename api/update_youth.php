<?php
/**
 * Update Youth Member
 * Updates an existing youth member's profile
 * Tracks which admin last updated the member
 */

require_once __DIR__ . '/../includes/request.php';
secureSessionStart();
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'auth.php';
require_once __DIR__ . '/security_helpers.php';
requireAdminApi();

// Check if admin is authenticated
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
securityRequireCsrf();

// Get POST data
$member_id = isset($_POST['member_id']) ? trim($_POST['member_id']) : '';
$firstname = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
$lastname = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
$birthday = isset($_POST['birthday']) ? $_POST['birthday'] : '';
$age = isset($_POST['age']) ? intval($_POST['age']) : 0;
$gender = isset($_POST['gender']) ? $_POST['gender'] : '';
$phone = isset($_POST['contact']) ? trim($_POST['contact']) : '';
$address = isset($_POST['address']) ? trim($_POST['address']) : '';
$barangay = isset($_POST['barangay']) ? trim($_POST['barangay']) : 'San Antonio';

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
    $userStmt = $pdo->prepare("SELECT u.id, u.email FROM users u WHERE u.member_id = ?");
    $userStmt->execute([$member_id]);
    
    if ($userStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Youth member not found']);
        exit;
    }
    
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    $user_id = $user['id'];
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update profile
    $profileStmt = $pdo->prepare("UPDATE youth_profiles
                                SET firstname = ?, lastname = ?, birthday = ?, age = ?, gender = ?,
                                    phone = ?,
                                    address = ?, barangay = ?, updated_at = CURRENT_TIMESTAMP
                                WHERE user_id = ?");
    $profileStmt->execute([$firstname, $lastname, $birthday, $age, $gender, $phone, $address, $barangay, $user_id]);
    
    // Commit transaction
    $pdo->commit();
    
    // Log update
    error_log("[Youth Update] Admin '$admin_username' updated youth member: $firstname $lastname ($member_id)");
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Youth member updated successfully',
        'data' => [
            'member_id' => $member_id,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'updated_by' => $admin_username
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    error_log("[Update Youth Error] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating youth member'
    ]);
}
?>
