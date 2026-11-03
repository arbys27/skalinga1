<?php
// api/get_print_requests.php - Fetch all print requests for admin dashboard
header('Content-Type: application/json');
require_once 'auth.php';
requireAdminApi();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized. Admin login required.'
    ]);
    exit;
}

try {
    require_once 'db_connect.php';
    
    // Fetch all print requests sorted by created_at DESC
    $stmt = $pdo->query("
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
            pr.notes
        FROM printing_requests pr
        LEFT JOIN users u ON u.member_id = pr.member_id
        LEFT JOIN youth_profiles yp ON yp.user_id = u.id
        LEFT JOIN kk_masterlist km ON km.id = u.masterlist_id
        ORDER BY pr.created_at DESC
    ");
    
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($requests as &$request) {
        $request['file_path'] = 'api/get_upload.php?type=printing&id=' . (int)$request['request_id'];
    }
    unset($request);
    
    echo json_encode([
        'success' => true,
        'data' => $requests,
        'total' => count($requests)
    ]);
    
} catch (PDOException $e) {
    error_log('[Print] Request list query failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to retrieve print requests.'
    ]);
}
?>
