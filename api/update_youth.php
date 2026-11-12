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
require_once __DIR__ . '/youth_lifecycle.php';
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
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$address = isset($_POST['address']) ? trim($_POST['address']) : '';
$barangay = isset($_POST['barangay']) ? trim($_POST['barangay']) : 'San Antonio';
$calculatedAge = youthAgeFromBirthday($birthday);

// Admin info
$admin_username = $_SESSION['admin_username'];

// Validate input
if (empty($member_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Member ID is required']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid email address is required']);
    exit;
}
if ($calculatedAge === null || $calculatedAge < 15 || $calculatedAge > 30) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'KK youth must be between 15 and 30 years old']);
    exit;
}
$age = $calculatedAge;

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
    $emailCheck = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND id <> ? LIMIT 1');
    $emailCheck->execute([$email, $user_id]);
    if ($emailCheck->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email address is already registered']);
        exit;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update profile
    $profileStmt = $pdo->prepare("UPDATE youth_profiles
                                SET firstname = ?, lastname = ?, birthday = ?, age = ?, gender = ?,
                                    phone = ?,
                                    address = ?, barangay = ?, updated_at = CURRENT_TIMESTAMP
                                WHERE user_id = ?");
    $profileStmt->execute([$firstname, $lastname, $birthday, $age, $gender, $phone, $address, $barangay, $user_id]);
    $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$email, $user_id]);
    
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
