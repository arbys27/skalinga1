<?php
require_once dirname(__DIR__) . '/db_connect.php';
require_once __DIR__ . '/common.php';

$auth = disclosure_admin_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    disclosure_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

try {
    $title = disclosure_text($_POST['title'] ?? null);
    $category = disclosure_category($_POST['category'] ?? null);
    $description = disclosure_text($_POST['description'] ?? null);
    $status = ($_POST['status'] ?? 'Published') === 'Draft' ? 'Draft' : 'Published';
    if ($title === null || $category === null) {
        disclosure_response(['success' => false, 'message' => 'Title and category are required'], 422);
    }
    $upload = disclosure_upload($_FILES['document'] ?? null);
    $stmt = $pdo->prepare(
        'INSERT INTO public_disclosures
            (title, category, description, original_name, file_path, mime_type, file_size, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $title, $category, $description, $upload['original_name'], $upload['file_path'],
        $upload['mime_type'], $upload['file_size'], $status, (int)$auth['id'],
    ]);
    disclosure_response(['success' => true, 'message' => 'Disclosure published successfully']);
} catch (Throwable $e) {
    disclosure_error_response($e);
}
