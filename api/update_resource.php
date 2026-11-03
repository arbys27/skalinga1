<?php
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'auth.php';
requireAdminApi();

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Admin login required.']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $itemId = $data['item_id'] ?? null;
    $name = $data['name'] ?? null;
    $description = $data['description'] ?? '';
    $restockQuantity = filter_var($data['restock_quantity'] ?? null, FILTER_VALIDATE_INT);

    if (!is_string($itemId) || !is_string($name) || !is_string($description)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Item ID, name, and description must be text values.']);
        exit;
    }

    $itemId = trim($itemId);
    $name = trim($name);
    $description = trim($description);
    $nameLength = preg_match_all('/./us', $name);

    if ($itemId === '' || $name === '' || $nameLength === false || $nameLength > 255 || $restockQuantity === false || $restockQuantity === null || $restockQuantity < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'A valid item, name, and non-negative restock quantity are required.']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT item_id FROM resources WHERE item_id = ? FOR UPDATE');
        $stmt->execute([$itemId]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Inventory item not found.']);
            exit;
        }

        $stmt = $pdo->prepare('
            UPDATE resources
            SET name = ?, description = ?, quantity = quantity + ?, available = available + ?
            WHERE item_id = ?
        ');
        $stmt->execute([$name, $description, $restockQuantity, $restockQuantity, $itemId]);
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Inventory item updated successfully.',
            'item_id' => $itemId,
            'restocked' => $restockQuantity
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
} catch (PDOException $e) {
    error_log('[Resources] Update failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to update the inventory item.']);
} catch (Exception $e) {
    error_log('[Resources] Update failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to update the inventory item.']);
}
?>
