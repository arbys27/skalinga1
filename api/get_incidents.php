<?php
// API: Get All Incidents (Admin)

// Start session
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../includes/request.php';
    secureSessionStart();
}

header('Content-Type: application/json');

require 'db_connect.php';
require_once 'auth.php';
requireAdminApi();

// Check if admin is authenticated
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check if PDO connection exists
if (!isset($pdo)) {
    error_log('PDO connection not available in get_incidents.php');
    echo json_encode(['success' => false, 'error' => 'Database connection not available']);
    exit;
}

try {
    // Join through users table (via member_id) to youth_profiles to get incident details with member info
    $query = "
        SELECT i.id, i.member_id, i.category, i.description, i.location, i.urgency, 
               i.status, i.photo_path, i.submitted_date, i.admin_notes,
               COALESCE(p.firstname, 'Unknown') as firstname,
               COALESCE(p.lastname, 'User') as lastname,
               COALESCE(p.phone, 'N/A') as phone,
               COALESCE(p.address, 'N/A') as address,
               COALESCE(p.avatar_path, '') as avatar_path,
               u.id as user_id
        FROM incidents i
        LEFT JOIN users u ON UPPER(i.member_id) = UPPER(u.member_id)
        LEFT JOIN youth_profiles p ON u.id = p.user_id
        ORDER BY i.submitted_date DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($incidents as &$incident) {
        $photos = json_decode((string)($incident['photo_path'] ?? ''), true);
        if (!is_array($photos)) {
            $photos = !empty($incident['photo_path']) ? [$incident['photo_path']] : [];
        }
        $photos = array_values($photos);
        $incident['photo_path'] = json_encode(array_values(array_map(
            static fn(int $index): string => 'api/get_upload.php?type=incident&id=' . (int)$incident['id'] . '&file=' . $index,
            array_keys($photos)
        )), JSON_UNESCAPED_SLASHES);
    }
    unset($incident);
    
    echo json_encode([
        'success' => true,
        'data' => $incidents
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in get_incidents.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to retrieve incidents.']);
    exit;
}
?>
