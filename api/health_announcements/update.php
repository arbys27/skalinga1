<?php
require_once dirname(__DIR__) . '/db_connect.php';
require_once __DIR__ . '/common.php';

$auth = health_auth_or_fail();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    health_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

try {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) {
        health_response(['success' => false, 'message' => 'Announcement ID is required'], 422);
    }

    $existing = health_fetch($pdo, $id);
    if (!$existing) {
        health_response(['success' => false, 'message' => 'Announcement not found'], 404);
    }

    $title = health_trim_null($_POST['title'] ?? null);
    $category = health_validate_category($_POST['category'] ?? null);
    $description = health_trim_null($_POST['description'] ?? null);
    $eventDate = health_validate_date($_POST['event_date'] ?? null);
    $startTime = health_validate_time($_POST['start_time'] ?? null);
    $endTime = health_validate_time($_POST['end_time'] ?? null);
    $venue = health_trim_null($_POST['venue'] ?? null);
    $slotsRaw = health_trim_null($_POST['slots'] ?? null);
    $requirements = health_trim_null($_POST['requirements'] ?? null);
    $status = health_validate_status($_POST['status'] ?? null);

    if ($title === null || $category === null || $eventDate === null) {
        health_response(['success' => false, 'message' => 'Title, category, and event date are required'], 422);
    }

    if (strtotime($eventDate) < strtotime(date('Y-m-d')) && $existing['status'] === 'Draft') {
        health_response(['success' => false, 'message' => 'Event date cannot be in the past for new posts'], 422);
    }

    if ($startTime !== null && $endTime !== null && strcmp($endTime, $startTime) <= 0) {
        health_response(['success' => false, 'message' => 'End time must be after start time'], 422);
    }

    $slots = null;
    if ($slotsRaw !== null) {
        if (!ctype_digit($slotsRaw) || (int)$slotsRaw < 1) {
            health_response(['success' => false, 'message' => 'Slots must be at least 1'], 422);
        }
        $slots = (int)$slotsRaw;
    }

    $newImagePath = health_upload_image($_FILES['image'] ?? null, $existing['image_path'] ?? null);
    $imagePath = $newImagePath ?? $existing['image_path'];
    $updatedBy = (int)$auth['id'];

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'UPDATE health_announcements
         SET title = ?, category = ?, description = ?, event_date = ?, start_time = ?, end_time = ?, venue = ?, slots = ?, requirements = ?, image_path = ?, status = COALESCE(?, status), published_at = CASE WHEN ? = \'Published\' AND published_at IS NULL THEN CURRENT_TIMESTAMP ELSE published_at END, updated_by = ?, updated_at = CURRENT_TIMESTAMP
         WHERE id = ?'
    );
    $stmt->execute([
        $title,
        $category,
        $description,
        $eventDate,
        $startTime,
        $endTime,
        $venue,
        $slots,
        $requirements,
        $imagePath,
        $status,
        $status,
        $updatedBy,
        $id,
    ]);

    health_log($pdo, $auth, 'health_announcement_update', $id);
    $pdo->commit();

    health_response([
        'success' => true,
        'message' => 'Health announcement updated',
        'id' => $id,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    health_error_response($e);
}
