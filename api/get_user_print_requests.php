<?php
// api/get_user_print_requests.php - Fetch print requests of logged-in youth
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/request.php';
secureSessionStart();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized. User must be logged in.'
    ]);
    exit;
}

try {
    require_once 'db_connect.php';
    
    $user_id = $_SESSION['user_id'];
    
    // Get user's member_id
    $stmt = $pdo->prepare("SELECT member_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    $member_id = $user['member_id'];
    
    // Fetch all print requests for this user
    $stmt = $pdo->prepare("
        SELECT 
            pr.request_id,
            pr.member_id,
            COALESCE(
                NULLIF(TRIM(CONCAT_WS(' ', NULLIF(yp.firstname, ''), NULLIF(yp.lastname, ''))), ''),
                NULLIF(TRIM(CONCAT_WS(' ', NULLIF(km.first_name, ''), NULLIF(km.middle_name, ''), NULLIF(km.last_name, ''))), ''),
                NULLIF(NULLIF(TRIM(pr.member_name), ''), 'Unknown'),
                pr.member_name
            ) AS member_name,
            pr.document_title,
            pr.file_path,
            pr.file_name,
            pr.file_size,
            pr.print_type,
            pr.paper_size,
            pr.copies,
            pr.status,
            pr.created_at,
            pr.updated_at,
            pr.claimed_at,
            pr.notes,
            pr.print_verified_at,
            pr.verified_at
        FROM printing_requests pr
        LEFT JOIN users u ON u.member_id = pr.member_id
        LEFT JOIN youth_profiles yp ON yp.user_id = u.id
        LEFT JOIN kk_masterlist km ON km.id = u.masterlist_id
        WHERE pr.member_id = ?
        ORDER BY pr.created_at DESC
    ");
    
    $stmt->execute([$member_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($requests as &$request) {
        $request['file_path'] = empty($request['print_verified_at']) && empty($request['verified_at'])
            ? null
            : 'api/get_print_file.php?id=' . (int)$request['request_id'];
    }
    unset($request);
    
    echo json_encode([
        'success' => true,
        'data' => $requests,
        'total' => count($requests)
    ]);
    
} catch (Exception $e) {
    error_log('[Print] Youth request list query failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to retrieve print requests.'
    ]);
}
?>
