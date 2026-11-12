<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/db_connect.php';
require_once __DIR__ . '/../includes/sms.php';

$pdo->beginTransaction();
try {
    $mark = $pdo->prepare(
        "UPDATE borrow_records
         SET status = 'Overdue', updated_at = CURRENT_TIMESTAMP
         WHERE status = 'Borrowed' AND due_date < CURRENT_TIMESTAMP"
    );
    $mark->execute();
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Unable to mark overdue loans: {$exception->getMessage()}\n");
    exit(1);
}

$query = $pdo->query(
    "SELECT br.borrow_id, br.item_id, br.due_date, br.overdue_notification_attempts,
            r.name AS item_name, u.id AS user_id, yp.phone, yp.firstname
     FROM borrow_records br
     JOIN users u ON u.member_id = br.member_id AND u.status = 'active'
     LEFT JOIN youth_profiles yp ON yp.user_id = u.id
     WHERE br.status = 'Overdue'
       AND br.overdue_notified_at IS NULL
       AND br.overdue_notification_attempts < 5
     ORDER BY br.due_date
     LIMIT 100"
);
$update = $pdo->prepare(
    "UPDATE borrow_records
     SET overdue_notified_at = CASE WHEN ? THEN CURRENT_TIMESTAMP ELSE NULL END,
         overdue_notification_attempts = overdue_notification_attempts + 1,
         overdue_notification_error = ?, updated_at = CURRENT_TIMESTAMP
     WHERE borrow_id = ? AND overdue_notified_at IS NULL"
);
$sent = 0;
foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $error = null;
    $ok = false;
    try {
        if (!is_string($row['phone']) || trim($row['phone']) === '') {
            throw new RuntimeException('Youth has no registered mobile number.');
        }
        sendOTP((string)$row['phone'], 'SKalinga reminder: please return "' . $row['item_name']
            . '" to the SK Office. It was due on ' . date('M j, Y', strtotime((string)$row['due_date'])) . '.');
        $ok = true;
        $sent++;
    } catch (Throwable $exception) {
        $error = substr($exception->getMessage(), 0, 500);
        error_log('[Overdue SMS] borrow_id=' . (int)$row['borrow_id'] . ' ' . $error);
    }
    $update->execute([$ok, $error, $row['borrow_id']]);
}
fwrite(STDOUT, "Marked {$mark->rowCount()} overdue loan(s); sent {$sent} reminder(s).\n");
