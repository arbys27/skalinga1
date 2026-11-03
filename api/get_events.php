<?php
// get_events.php - Fetch all events from database
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require 'db_connect.php';

try {
    // Get optional filter parameters
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    $columnQuery = $pdo->prepare(
        'SELECT column_name FROM information_schema.columns
         WHERE table_schema = current_schema() AND table_name = ?'
    );
    $columnQuery->execute(['events']);
    $columns = $columnQuery->fetchAll(PDO::FETCH_COLUMN);
    $hasColumn = static function (string $column) use ($columns): bool {
        return in_array($column, $columns, true);
    };
    $columnExpression = static function (string $current, ?string $legacy = null, string $fallback = 'NULL') use ($hasColumn): string {
        if ($hasColumn($current)) {
            return '"' . $current . '"';
        }
        if ($legacy !== null && $hasColumn($legacy)) {
            return '"' . $legacy . '"';
        }
        return $fallback;
    };

    foreach (['title', 'date', 'location'] as $requiredColumn) {
        if (!$hasColumn($requiredColumn)) {
            throw new RuntimeException("Events table is missing required column: {$requiredColumn}");
        }
    }
    if (!$hasColumn('event_id') && !$hasColumn('id')) {
        throw new RuntimeException('Events table is missing event_id and id columns');
    }
    if (!$hasColumn('start_time') && !$hasColumn('time')) {
        throw new RuntimeException('Events table is missing start_time and time columns');
    }

    $query = "SELECT "
        . $columnExpression('event_id', 'id') . " AS event_id, "
        . '"title", "date", '
        . $columnExpression('start_time', 'time') . " AS start_time, "
        . $columnExpression('end_time') . " AS end_time, "
        . '"location", '
        . $columnExpression('event_type', 'type') . " AS event_type, "
        . $columnExpression('description') . " AS description, "
        . $columnExpression('capacity', null, '0') . " AS capacity, "
        . $columnExpression('registered_count', null, '0') . " AS registered_count, "
        . $columnExpression('registration_link', 'regLink') . " AS registration_link, "
        . $columnExpression('image_path') . " AS image_path, "
        . $columnExpression('status', null, "'Upcoming'") . " AS status "
        . 'FROM "events" WHERE TRUE';
    $params = [];
    
    if ($status) {
        $query .= " AND status = ?";
        $params[] = $status;
    }
    
    if ($search) {
        $query .= " AND (title ILIKE ? OR location ILIKE ? OR description ILIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $query .= ' ORDER BY "date" ASC, start_time ASC';
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    $events = [];
    foreach ($results as $row) {
        // Map database columns to expected format
        $events[] = [
            'id' => $row['event_id'],
            'title' => $row['title'],
            'date' => $row['date'],
            'time' => $row['start_time'],
            'endTime' => $row['end_time'],
            'location' => $row['location'],
            'type' => $row['event_type'],
            'description' => $row['description'],
            'capacity' => $row['capacity'],
            'registeredCount' => $row['registered_count'],
            'regLink' => $row['registration_link'],
            'imagePath' => $row['image_path'],
            'status' => $row['status']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $events,
        'count' => count($events)
    ]);
    
} catch (Exception $e) {
    error_log('[Events] List query failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to retrieve events.'
    ]);
}

?>
