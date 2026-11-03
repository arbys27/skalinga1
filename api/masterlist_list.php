<?php

require_once 'db_connect.php';
require_once 'masterlist_common.php';

requireAdminMasterlistAccess();

$search = trim($_GET['search'] ?? '');
$claimed = $_GET['claimed'] ?? '';
$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = '(voter_id ILIKE ? OR first_name ILIKE ? OR middle_name ILIKE ? OR last_name ILIKE ? OR address ILIKE ?)';
    $term = '%' . $search . '%';
    $params = [$term, $term, $term, $term, $term];
}
if ($claimed === '0' || $claimed === '1') {
    $conditions[] = 'is_claimed = ?';
    $params[] = $claimed === '1';
}

$sql = 'SELECT id, voter_id, first_name, last_name, middle_name, birthday, age, sex,
               address, purok_or_barangay, is_claimed, created_at
        FROM kk_masterlist';
if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY last_name, first_name LIMIT 500';

try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $index => $param) {
        $stmt->bindValue($index + 1, $param, is_bool($param) ? PDO::PARAM_BOOL : PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['is_claimed'] = postgresBoolean($row['is_claimed']);
    }
    unset($row);
    masterlistJsonResponse(['success' => true, 'data' => $rows]);
} catch (Throwable $exception) {
    error_log('[Masterlist List] ' . $exception->getMessage());
    masterlistJsonResponse(['success' => false, 'message' => 'Unable to load the masterlist.'], 500);
}
