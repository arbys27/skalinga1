<?php
require_once 'db_connect.php';
require_once 'auth.php';

$auth = requireAuth(['youth']);

try {
    $stmt = $pdo->prepare(
        'SELECT br.borrow_id, br.item_id, br.member_id, br.borrower_name,
                br.quantity, br.borrow_date, br.due_date, br.return_date, br.status,
                r.name AS item_name, r.description
         FROM borrow_records br
         INNER JOIN users u ON u.member_id = br.member_id
         LEFT JOIN resources r ON r.item_id = br.item_id
         WHERE u.id = ?
         ORDER BY br.borrow_date DESC, br.borrow_id DESC'
    );
    $stmt->execute([$auth['id']]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    authJsonResponse([
        'success' => true,
        'data' => $records,
        'total' => count($records)
    ]);
} catch (Throwable $exception) {
    error_log('[Youth Borrow History Error] ' . $exception->getMessage());
    authJsonResponse([
        'success' => false,
        'message' => 'Unable to load borrowing history right now.'
    ], 500);
}
