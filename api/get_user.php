<?php
// Get user profile - supports both logged-in user lookup and admin member lookup by ID
require_once 'db_connect.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../includes/request.php';
    secureSessionStart();
}

try {
    // Check if member_id is provided as query parameter (for admin scanning)
    $member_id_param = isset($_GET['member_id']) ? trim($_GET['member_id']) : null;
    
    if ($member_id_param) {
        // Admin is looking up a specific member by member_id (QR scan)
        // Verify admin is logged in
        requireAdminApi();
        
        // Convert to uppercase for consistent search (member IDs are typically uppercase)
        $member_id_param = strtoupper($member_id_param);
        
        // Look up user by member_id (case-insensitive)
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    u.id,
                    u.email,
                    u.member_id,
                    u.status,
                    u.email_verified,
                    u.last_login,
                    u.created_at,
                    p.firstname,
                    p.lastname,
                    p.birthday,
                    p.age,
                    p.gender,
                    p.phone,
                    p.address,
                    p.barangay,
                    p.avatar_path,
                    p.bio
                FROM users u
                LEFT JOIN youth_profiles p ON u.id = p.user_id
                WHERE UPPER(u.member_id) = UPPER(?)
            ");
            $stmt->execute([$member_id_param]);
            $user = $stmt->fetch();
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database query error']);
            exit;
        }
    } else {
        // Return logged-in user's profile
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            exit;
        }
        
        $user_id = $_SESSION['user_id'];
        
        // Get complete user profile by joining users and youth_profiles
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    u.id,
                    u.email,
                    u.member_id,
                    u.status,
                    u.email_verified,
                    u.last_login,
                    u.created_at,
                    p.firstname,
                    p.lastname,
                    p.birthday,
                    p.age,
                    p.gender,
                    p.phone,
                    p.address,
                    p.barangay,
                    p.avatar_path,
                    p.bio
                FROM users u
                LEFT JOIN youth_profiles p ON u.id = p.user_id
                WHERE u.id = ?
            ");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database query error']);
            exit;
        }
    }
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => $member_id_param ? 'Member ID not found' : 'User not found']);
        exit;
    }
    
    // Get event attendance count (if events table exists)
    $events_count = 0;
    try {
        $check_events = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name = 'event_registrations'");
        if ($check_events->fetchColumn() > 0) {
            $event_stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM event_registrations 
                WHERE user_id = ? AND status = 'Attended'
            ");
            $event_stmt->execute([$user['id']]);
            $event_data = $event_stmt->fetch();
            $events_count = $event_data['count'] ?? 0;
        }
    } catch (Exception $e) {
        // Table doesn't exist or query failed, continue with 0 count
        $events_count = 0;
    }
    
    // Build user data array
    $userData = [
        'id' => $user['id'],
        'email' => $user['email'],
        'member_id' => $user['member_id'],
        'firstname' => $user['firstname'],
        'lastname' => $user['lastname'],
        'name' => trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')),
        'phone' => $user['phone'],
        'birthday' => $user['birthday'],
        'age' => $user['age'],
        'gender' => $user['gender'],
        'address' => $user['address'],
        'barangay' => $user['barangay'],
        'avatar_path' => $user['avatar_path'],
        'bio' => $user['bio'],
        'status' => $user['status'],
        'email_verified' => postgresBoolean($user['email_verified']),
        'created_at' => $user['created_at'],
        'last_login' => $user['last_login'],
        'events_attended' => $events_count
    ];
    
    // If member_id was provided (admin lookup), nest under 'user' key
    // Otherwise, return flat structure for logged-in user pages
    if ($member_id_param) {
        echo json_encode([
            'success' => true,
            'user' => $userData
        ]);
    } else {
        echo json_encode(array_merge(['success' => true], $userData));
    }
    
} catch (Exception $e) {
    error_log('[User] Profile retrieval failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to retrieve user data.']);
}
?>