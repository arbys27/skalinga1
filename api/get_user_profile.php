<?php
// get_user_profile.php - Fetch current logged-in user info
header('Content-Type: application/json');

require_once 'auth.php';
authStartSession();

$requestedRole = $_GET['role'] ?? 'any';

try {
    // For youth pages requesting youth profile, skip admin session
    if ($requestedRole !== 'youth') {
        $adminAuth = requireAdminApi();
        $adminSessionId = $adminAuth['id'];
        $userId = $adminSessionId;
        $userName = $_SESSION['admin_username'] ?? $adminAuth['identifier'] ?? 'Administrator';
        $userRole = 'Super Admin';
        
        // Capitalize first letter of username
        $userName = ucfirst(strtolower($userName));
        
        // Get avatar initials from username
        $names = explode(' ', $userName);
        $initials = '';
        foreach ($names as $name) {
            $initials .= strtoupper(substr($name, 0, 1));
        }
        $initials = substr($initials, 0, 2);
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $userId,
                'name' => $userName,
                'role' => $userRole,
                'initials' => $initials,
                'member_id' => 'ADM-' . $userId,
                'issued_date' => date('F Y')
            ]
        ]);
        exit;
    }
    
    // Check if regular user (youth) is logged in (for youth pages)
    if (isset($_SESSION['user_id'])) {
        require_once 'db_connect.php';
        
        $userId = $_SESSION['user_id'];
        $firstName = $_SESSION['firstname'] ?? 'User';
        $lastName = $_SESSION['lastname'] ?? '';
        
        // Capitalize names properly
        $firstName = ucfirst(strtolower($firstName));
        $lastName = ucfirst(strtolower($lastName));
        
        $userName = $firstName . ($lastName ? ' ' . $lastName : '');
        $userRole = 'Youth Member';
        
        // Get avatar initials from name
        $names = explode(' ', trim($userName));
        $initials = '';
        foreach ($names as $name) {
            if (!empty($name)) {
                $initials .= strtoupper(substr($name, 0, 1));
            }
        }
        $initials = substr($initials, 0, 2);
        
        // Fetch member_id and created_at from users table
        try {
            $stmt = $pdo->prepare("SELECT member_id, created_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
        } catch (Exception $e) {
            $row = null;
        }
        
        $memberId = 'SK-XXXX-XXXX';
        $issuedDate = date('F Y');
        
        if ($row) {
            $memberId = $row['member_id'] ?? $memberId;
            $issuedDate = date('F Y', strtotime($row['created_at']));
        }
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $userId,
                'name' => $userName,
                'role' => $userRole,
                'initials' => $initials,
                'member_id' => $memberId,
                'issued_date' => $issuedDate
            ]
        ]);
        exit;
    }
    
    // Not logged in
    echo json_encode([
        'success' => false,
        'error' => 'Not logged in'
    ]);

} catch (Exception $e) {
    error_log('[Profile] Request failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to retrieve the profile.'
    ]);
}

?>
