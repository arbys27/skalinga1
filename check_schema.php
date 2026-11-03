<?php
require_once 'api/db_connect.php';

header('Content-Type: application/json');

try {
    // Check PostgreSQL table information
    $stmt = $pdo->prepare("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public'
        ORDER BY table_name
    ");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check incidents table structure
    $incidentsStmt = $pdo->prepare("
        SELECT column_name, data_type, is_nullable
        FROM information_schema.columns
        WHERE table_name = 'incidents'
        ORDER BY ordinal_position
    ");
    $incidentsStmt->execute();
    $incidentsCols = $incidentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get count
    $countStmt = $pdo->query("SELECT COUNT(*) as cnt FROM incidents");
    $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'tables' => $tables,
        'incidents_columns' => $incidentsCols,
        'incidents_count' => $countRow['cnt']
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>
