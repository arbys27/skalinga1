<?php

require_once 'db_connect.php';
require_once 'masterlist_common.php';
require_once __DIR__ . '/youth_lifecycle.php';

requireAdminMasterlistAccess();

$status = $_GET['status'] ?? 'active';
if (!in_array($status, ['active', 'inactive', 'archived'], true)) {
    masterlistJsonResponse(['success' => false, 'message' => 'Invalid status filter.'], 400);
}
$search = trim($_GET['search'] ?? '');
$claimed = $_GET['claimed'] ?? '';
$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = '(km.voter_id ILIKE ? OR km.first_name ILIKE ? OR km.middle_name ILIKE ? OR km.last_name ILIKE ? OR km.address ILIKE ?)';
    $term = '%' . $search . '%';
    $params = [$term, $term, $term, $term, $term];
}
if ($claimed === '0' || $claimed === '1') {
    $conditions[] = 'km.is_claimed = ?';
    $params[] = $claimed === '1';
}
$conditions[] = "COALESCE(u.status, km.status) = ?";
$params[] = $status;

$sql = 'SELECT km.id, km.voter_id, km.first_name, km.last_name, km.middle_name, km.birthday, km.age, km.sex,
               km.address, km.purok_or_barangay, km.is_claimed, km.created_at,
               COALESCE(u.status, km.status) AS status,
               COALESCE(u.status_reason, km.status_reason) AS status_reason,
               COALESCE(u.status_changed_at, km.status_changed_at) AS status_changed_at,
               u.last_login
        FROM public.kk_masterlist km
        LEFT JOIN public.users u ON u.id = km.claimed_by_user_id
        WHERE ' . implode(' AND ', $conditions) . '
        ORDER BY km.last_name, km.first_name LIMIT 500';

try {
    runYouthLifecycle($pdo);
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
