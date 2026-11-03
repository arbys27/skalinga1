<?php
require_once 'api/db_connect.php';

try {
    echo "Attempting to drop incidents_urgency_check constraint...\n";
    $pdo->exec('ALTER TABLE incidents DROP CONSTRAINT IF EXISTS incidents_urgency_check');
    echo "✓ Constraint dropped successfully!\n";
    echo "You can now submit incident reports without errors.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
