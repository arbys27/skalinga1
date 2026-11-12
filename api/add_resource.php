<?php
// add_resource.php - Add a new borrowable resource
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'auth.php';
require_once 'security_helpers.php';
requireAdminApi();
securityRequireCsrf();

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
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        $data = $_POST;
    }

    // Validate required fields
    if (empty($data['name']) || empty($data['quantity'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Item name and quantity are required.'
        ]);
        exit;
    }

    // Generate unique item_id (e.g., ITEM-20260217-001)
    $date = date('Ymd');
    $randomId = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    $itemId = 'ITEM-' . $date . '-' . $randomId;

    $name = trim($data['name']);
    $description = trim($data['description'] ?? '');
    $quantity = intval($data['quantity']);

    // Validate quantity
    if ($quantity < 1) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Quantity must be at least 1.'
        ]);
        exit;
    }

    // Insert into resources table
    $stmt = $pdo->prepare("
        INSERT INTO resources (item_id, name, description, quantity, available)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $itemId,
        $name,
        $description,
        $quantity,
        $quantity  // available = quantity initially
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Resource added successfully',
        'resource' => [
            'item_id' => $itemId,
            'name' => $name,
            'description' => $description,
            'quantity' => $quantity,
            'available' => $quantity
        ]
    ]);

} catch (PDOException $e) {
    error_log('[Resources] Creation failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to create the inventory item.'
    ]);
} catch (Exception $e) {
    error_log('[Resources] Creation failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to create the inventory item.'
    ]);
}
?>
