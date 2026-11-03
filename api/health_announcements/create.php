<?php
require_once dirname(__DIR__) . '/db_connect.php';
require_once __DIR__ . '/common.php';

$auth = health_auth_or_fail();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    health_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

try {
    $title = health_trim_null($_POST['title'] ?? null);
    $category = health_validate_category($_POST['category'] ?? null);
    $description = health_trim_null($_POST['description'] ?? null);
    $eventDate = health_validate_date($_POST['event_date'] ?? null);
    $startTime = health_validate_time($_POST['start_time'] ?? null);
    $endTime = health_validate_time($_POST['end_time'] ?? null);
    $venue = health_trim_null($_POST['venue'] ?? null);
    $slotsRaw = health_trim_null($_POST['slots'] ?? null);
    $requirements = health_trim_null($_POST['requirements'] ?? null);
    $status = health_validate_status($_POST['status'] ?? 'Draft') ?? 'Draft';

    if ($title === null || $category === null) {
        health_response(['success' => false, 'message' => 'Title and category are required'], 422);
    }

    if ($eventDate === null) {
        health_response(['success' => false, 'message' => 'Event date is required'], 422);
    }

    if (strtotime($eventDate) < strtotime(date('Y-m-d'))) {
        health_response(['success' => false, 'message' => 'Event date cannot be in the past'], 422);
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

    $imagePath = health_upload_image($_FILES['image'] ?? null);
    $publishedAt = $status === 'Published' ? date('Y-m-d H:i:s') : null;
    $nowUser = (int)$auth['id'];

    $stmt = $pdo->prepare(
        'INSERT INTO health_announcements
            (title, category, description, event_date, start_time, end_time, venue, slots, requirements, image_path, status, published_at, created_by, updated_by, created_at, updated_at)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
         RETURNING id'
    );
    $pdo->beginTransaction();
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
        $publishedAt,
        $nowUser,
        $nowUser,
    ]);

    $id = (int)$stmt->fetchColumn();
    health_log($pdo, $auth, 'health_announcement_create', $id);
    $pdo->commit();

    health_response([
        'success' => true,
        'message' => 'Health announcement created',
        'id' => $id,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    health_error_response($e);
}
