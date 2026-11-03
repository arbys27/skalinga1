<?php
// Update user profile
require_once 'db_connect.php';
require_once __DIR__ . '/security_helpers.php';

header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../includes/request.php';
    secureSessionStart();
}

// Validate session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}
securityRequireCsrf();

try {
    // Get updatable fields
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    
    // Optional: Password update
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    $errors = [];
    
    // Validation
    if (!empty($firstname) && strlen($firstname) < 2) {
        $errors[] = "First name must be at least 2 characters";
    }
    if (!empty($lastname) && strlen($lastname) < 2) {
        $errors[] = "Last name must be at least 2 characters";
    }
    if (!empty($phone) && !preg_match('/^[0-9]{11}$/', $phone)) {
        $errors[] = "Valid 11-digit phone number required";
    }
    if (!empty($new_password)) {
        if (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters";
        }
        if (empty($current_password)) {
            $errors[] = "Current password required to change password";
        }
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
        exit;
    }
    
    // Begin transaction
    $pdo->beginTransaction();

    // Update profile if any fields provided
    $update_fields = [];
    $params = [];
    
    if (!empty($firstname)) {
        $update_fields[] = "firstname = ?";
        $params[] = $firstname;
    }
    if (!empty($lastname)) {
        $update_fields[] = "lastname = ?";
        $params[] = $lastname;
    }
    if (!empty($phone)) {
        $update_fields[] = "phone = ?";
        $params[] = $phone;
    }
    if (!empty($address)) {
        $update_fields[] = "address = ?";
        $params[] = $address;
    }
    if (!empty($bio)) {
        $update_fields[] = "bio = ?";
        $params[] = $bio;
    }
    
    if (!empty($update_fields)) {
        $update_fields[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE youth_profiles SET " . implode(", ", $update_fields) . " WHERE user_id = ?";
        $params[] = $user_id;
        
        $profile_stmt = $pdo->prepare($sql);
        $profile_stmt->execute($params);
    }
    
    // Update password if provided
    if (!empty($new_password)) {
        // Verify current password
        $pass_stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $pass_stmt->execute([$user_id]);
        $pass_data = $pass_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pass_data || !password_verify($current_password, $pass_data['password_hash'])) {
            throw new Exception("Current password is incorrect");
        }
        
        // Update password
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_pass = $pdo->prepare("
            UPDATE users 
            SET password_hash = ?, password_updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $update_pass->execute([$new_hash, $user_id]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Profile] Update failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update the profile.']);
}
?>
