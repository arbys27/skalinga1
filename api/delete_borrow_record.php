<?php
// delete_borrow_record.php - Delete a borrow record
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'auth.php';
requireAdminApi();

require_once __DIR__ . '/../includes/request.php';
require_once __DIR__ . '/security_helpers.php';
secureSessionStart();
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

    // Validate required field
    if (empty($data['borrow_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Borrow ID is required.'
        ]);
        exit;
    }

    $borrowId = intval($data['borrow_id']);

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT item_id, status, quantity FROM borrow_records WHERE borrow_id = ? FOR UPDATE");
    $stmt->execute([$borrowId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Borrow record not found.'
        ]);
        exit;
    }

    try {
        if ($record['status'] !== 'Returned') {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'Active items must be returned through a verified return request.'
            ]);
            exit;
        }

        // Delete the borrow record
        $stmt = $pdo->prepare("DELETE FROM borrow_records WHERE borrow_id = ?");
        $stmt->execute([$borrowId]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Borrow record deleted successfully',
            'borrow_id' => $borrowId
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Borrow] Record deletion failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to delete the borrow record.'
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Borrow] Record deletion failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to delete the borrow record.'
    ]);
}
?>
