<?php
require_once dirname(__DIR__) . '/db_connect.php';
require_once __DIR__ . '/common.php';

disclosure_admin_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    disclosure_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

try {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT file_path FROM public_disclosures WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        disclosure_response(['success' => false, 'message' => 'Disclosure not found'], 404);
    }
    $pdo->prepare('DELETE FROM public_disclosures WHERE id = ?')->execute([$id]);
    $fullPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $row['file_path']);
    if (is_file($fullPath)) {
        unlink($fullPath);
    }
    disclosure_response(['success' => true, 'message' => 'Disclosure deleted']);
} catch (Throwable $e) {
    disclosure_error_response($e);
}
