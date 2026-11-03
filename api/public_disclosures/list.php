<?php
require_once dirname(__DIR__) . '/db_connect.php';
require_once __DIR__ . '/common.php';

disclosure_public_auth();

try {
    $category = disclosure_category($_GET['category'] ?? null);
    $where = ["status = 'Published'"];
    $params = [];
    if ($category !== null) {
        $where[] = 'category = ?';
        $params[] = $category;
    }
    $stmt = $pdo->prepare('SELECT * FROM public_disclosures WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC');
    $stmt->execute($params);
    disclosure_response(['success' => true, 'data' => array_map('disclosure_row', $stmt->fetchAll(PDO::FETCH_ASSOC))]);
} catch (Throwable $e) {
    disclosure_error_response($e);
}
