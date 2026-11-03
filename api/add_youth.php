<?php
/**
 * Add Youth Member
 * Registers a new youth member in the database
 * Tracks which admin created the user
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
$firstname = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
$lastname = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$birthday = isset($_POST['birthday']) ? $_POST['birthday'] : '';
$age = isset($_POST['age']) ? intval($_POST['age']) : 0;
$gender = isset($_POST['gender']) ? $_POST['gender'] : '';
$phone = isset($_POST['contact']) ? trim($_POST['contact']) : '';
$address = isset($_POST['address']) ? trim($_POST['address']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$barangay = isset($_POST['barangay']) ? trim($_POST['barangay']) : 'San Antonio';

// Admin info (who is registering)
$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'];

// Validate input
if (empty($firstname) || empty($lastname) || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

try {
    // Check if email already exists
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
    $checkStmt->execute([$email]);
    
    if ($checkStmt->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit;
    }
    
    // Generate unique member ID
    $member_id = 'SK-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Check if member ID is unique
    $checkMemberStmt = $pdo->prepare("SELECT id FROM users WHERE member_id = ?");
    
    while (true) {
        $checkMemberStmt->execute([$member_id]);
        if ($checkMemberStmt->rowCount() === 0) {
            break; // Member ID is unique
        }
        $member_id = 'SK-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Insert user
    $userStmt = $pdo->prepare("INSERT INTO users (email, password_hash, member_id, status, email_verified) 
                              VALUES (?, ?, ?, 'active', TRUE)
                              RETURNING id");
    $userStmt->execute([$email, $password_hash, $member_id]);
    $user_id = (int)$userStmt->fetchColumn();
    
    // Insert profile
    $profileStmt = $pdo->prepare("INSERT INTO youth_profiles 
                                (user_id, firstname, lastname, birthday, age, gender, phone, address, barangay) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $profileStmt->execute([$user_id, $firstname, $lastname, $birthday, $age, $gender, $phone, $address, $barangay]);
    
    // Commit transaction
    $pdo->commit();
    
    // Log registration
    error_log("[Youth Registration] Admin '$admin_username' (ID: $admin_id) registered youth member: $firstname $lastname ($member_id)");
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Youth member registered successfully',
        'data' => [
            'user_id' => $user_id,
            'member_id' => $member_id,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'registered_by' => $admin_username
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("[Add Youth Error] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to register the youth member.'
    ]);
}
?>
