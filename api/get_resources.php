<?php
// get_resources.php - Fetch all resources
header('Content-Type: application/json');

require_once 'db_connect.php';
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
    // Fetch all resources from database
    $stmt = $pdo->query("
        SELECT item_id, name, description, quantity, available, created_at, updated_at
        FROM resources
        ORDER BY created_at DESC
    ");

    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $resources,
        'total' => count($resources)
    ]);

} catch (PDOException $e) {
    error_log('[Resources] List query failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to retrieve resources.'
    ]);
} catch (Exception $e) {
    error_log('[Resources] List query failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to retrieve resources.'
    ]);
}
?>
