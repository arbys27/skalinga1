<?php
require_once dirname(__DIR__) . '/db_connect.php';
require_once __DIR__ . '/common.php';

disclosure_admin_auth();

try {
    $stmt = $pdo->query('SELECT * FROM public_disclosures ORDER BY created_at DESC');
    disclosure_response(['success' => true, 'data' => array_map('disclosure_row', $stmt->fetchAll(PDO::FETCH_ASSOC))]);
} catch (Throwable $e) {
    disclosure_error_response($e);
}
