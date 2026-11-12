<?php
/**
 * Get Youth Members
 * Retrieves all or a specific youth member from database
 */

require_once __DIR__ . '/../includes/request.php';
secureSessionStart();
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'auth.php';
require_once __DIR__ . '/youth_lifecycle.php';
requireAdminApi();

// Check if admin is authenticated
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$youth_id = isset($_GET['id']) ? $_GET['id'] : null;
$status = $_GET['status'] ?? 'active';
if (!$youth_id && !in_array($status, ['active', 'inactive', 'archived'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid youth status filter']);
    exit;
}

try {
    runYouthLifecycle($pdo);
    if ($youth_id) {
        // Get specific youth member
        $query = "SELECT u.id, u.email, u.member_id, u.status, u.status_reason, u.status_changed_at,
                         u.last_login, u.created_at,
                         p.firstname, p.middle_name, p.lastname, p.birthday, p.age, p.gender,
                         p.phone, p.address, p.barangay, p.house_number, p.street_name,
                         p.subdivision_purok, p.city, p.province, p.avatar_path
                  FROM users u
                  LEFT JOIN youth_profiles p ON u.id = p.user_id
                  WHERE u.member_id = ?
                  LIMIT 1";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$youth_id]);
        $youth = $stmt->fetch();
        
        if (!$youth) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Youth member not found']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $youth
        ]);
        
    } else {
        // Get all youth members
        $query = "SELECT u.id, u.email, u.member_id, u.status, u.status_reason, u.status_changed_at,
                         u.last_login, u.created_at,
                         p.firstname, p.middle_name, p.lastname, p.birthday, p.age, p.gender,
                         p.phone, p.address, p.barangay, p.house_number, p.street_name,
                         p.subdivision_purok, p.city, p.province
                  FROM users u
                  LEFT JOIN youth_profiles p ON u.id = p.user_id
                  WHERE u.status = ?
                  ORDER BY u.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$status]);
        $youth = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $youth,
            'count' => count($youth)
        ]);
    }
    
} catch (Exception $e) {
    error_log("[Get Youth Error] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching youth data'
    ]);
}

?>
