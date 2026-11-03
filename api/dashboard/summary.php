<?php
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

requireAdminApi();

$metrics = [
    'youth_active' => dashboardCount(
        $pdo,
        'SELECT COUNT(*) FROM users WHERE status = ?',
        ['active'],
        'youth_active'
    ),
    'youth_pending' => dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM users WHERE status = 'pending'",
        [],
        'youth_pending'
    ),
    'masterlist_total' => dashboardCount(
        $pdo,
        'SELECT COUNT(*) FROM kk_masterlist',
        [],
        'masterlist_total'
    ),
    'masterlist_claimed' => dashboardCount(
        $pdo,
        'SELECT COUNT(*) FROM kk_masterlist WHERE is_claimed = TRUE',
        [],
        'masterlist_claimed'
    ),
    'events_upcoming' => dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM events WHERE status = 'Upcoming' AND date >= CURRENT_DATE",
        [],
        'events_upcoming'
    ),
    'events_ongoing' => dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM events
         WHERE status = 'Ongoing' AND date = CURRENT_DATE
           AND start_time <= CURRENT_TIME
           AND (end_time IS NULL OR end_time >= CURRENT_TIME)",
        [],
        'events_ongoing'
    ),
    'loans_active' => dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM borrow_records
         WHERE status = 'Borrowed' AND due_date >= CURRENT_TIMESTAMP",
        [],
        'loans_active'
    ),
    'loans_overdue' => dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM borrow_records
         WHERE status = 'Overdue'
            OR (status = 'Borrowed' AND due_date < CURRENT_TIMESTAMP)",
        [],
        'loans_overdue'
    ),
    'print_pending' => dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM printing_requests WHERE status = 'Pending'",
        [],
        'print_pending'
    ),
    'print_finished_today' => dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM printing_requests
         WHERE status IN ('Completed', 'Claimed')
           AND DATE(COALESCE(claimed_at, updated_at)) = CURRENT_DATE",
        [],
        'print_finished_today'
    ),
    'incidents_open' => dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM incidents
         WHERE LOWER(status) NOT IN ('resolved', 'closed')",
        [],
        'incidents_open'
    ),
    'incidents_week' => dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM incidents
         WHERE submitted_date >= CURRENT_TIMESTAMP - INTERVAL '7 days'",
        [],
        'incidents_week'
    ),
    'health_published' => dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM health_announcements WHERE status = 'Published'",
        [],
        'health_published'
    ),
    'health_upcoming' => dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM health_announcements
         WHERE status = 'Published' AND event_date >= CURRENT_DATE",
        [],
        'health_upcoming'
    ),
    'disclosure_published' => dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM public_disclosures WHERE status = 'Published'",
        [],
        'disclosure_published'
    ),
    'disclosure_drafts' => dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM public_disclosures WHERE status = 'Draft'",
        [],
        'disclosure_drafts'
    )
];

$freedomPending = 0;
foreach (['freedom_wall_posts', 'freedom_wall', 'freedom_wall_messages'] as $table) {
    try {
        if (dashboardTableExists($pdo, $table)) {
            $freedomPending = dashboardCount(
                $pdo,
                "SELECT COUNT(*) FROM {$table} WHERE LOWER(status) = ?",
                ['pending'],
                'freedom_wall_pending'
            );
            break;
        }
    } catch (Throwable $exception) {
        error_log('[Dashboard] Optional freedom wall lookup failed: ' . $exception->getMessage());
        break;
    }
}

$attention = [];
$attentionItems = [
    ['youth_pending', 'Youth waiting for approval', 'youth-registry.html', 'pink'],
    ['print_pending', 'Pending print requests', 'dashboard-printing.html', 'blue'],
    ['incidents_open', 'Open incidents', 'dashboard-incidents.html', 'red'],
    ['loans_overdue', 'Overdue loans', 'dashboard-resources.html', 'amber']
];
foreach ($attentionItems as [$key, $label, $href, $tone]) {
    if ($metrics[$key] > 0) {
        $attention[] = ['label' => $label, 'count' => $metrics[$key], 'href' => $href, 'tone' => $tone];
    }
}
if ($freedomPending > 0) {
    $attention[] = [
        'label' => 'Pending freedom wall posts',
        'count' => $freedomPending,
        'href' => 'dashboard-settings.html',
        'tone' => 'violet'
    ];
}
try {
    $draftHealth = dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM health_announcements WHERE status = 'Draft'",
        [],
        'health_drafts'
    );
    if ($draftHealth > 0) {
        $attention[] = [
            'label' => 'Draft health notices',
            'count' => $draftHealth,
            'href' => 'admin-health-announcements.html',
            'tone' => 'teal'
        ];
    }
} catch (Throwable $exception) {
    error_log('[Dashboard] health_drafts query failed: ' . $exception->getMessage());
}

$activity = [];
try {
    if (dashboardTableExists($pdo, 'activity_logs')) {
        $activity = dashboardRows(
            $pdo,
            "SELECT al.action, al.created_at,
                    CASE
                        WHEN al.role IN ('admin', 'sk_official')
                            THEN COALESCE(a.username, NULLIF(al.identifier, ''), 'Administrator')
                        WHEN al.role = 'youth'
                            THEN COALESCE(
                                NULLIF(TRIM(CONCAT_WS(' ', NULLIF(yp.firstname, ''), NULLIF(yp.lastname, ''))), ''),
                                NULLIF(al.identifier, ''),
                                'Youth member'
                            )
                        ELSE COALESCE(NULLIF(al.identifier, ''), 'System')
                    END AS actor
             FROM activity_logs al
             LEFT JOIN admins a
                    ON a.id = al.user_id AND al.role IN ('admin', 'sk_official')
             LEFT JOIN users u
                    ON u.id = al.user_id AND al.role = 'youth'
             LEFT JOIN youth_profiles yp ON yp.user_id = u.id
             ORDER BY al.created_at DESC, al.id DESC
             LIMIT 10",
            [],
            'activity'
        );
    }
} catch (Throwable $exception) {
    error_log('[Dashboard] activity query failed: ' . $exception->getMessage());
}

$registrations = [];
try {
    if (dashboardTableExists($pdo, 'users')) {
        $registrations = dashboardRows(
            $pdo,
            "SELECT
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', NULLIF(yp.firstname, ''), NULLIF(yp.lastname, ''))), ''),
                    NULLIF(TRIM(CONCAT_WS(' ', NULLIF(km.first_name, ''), NULLIF(km.middle_name, ''), NULLIF(km.last_name, ''))), ''),
                    u.member_id
                ) AS name,
                COALESCE(
                    EXTRACT(YEAR FROM age(CURRENT_DATE, yp.birthday)),
                    EXTRACT(YEAR FROM age(CURRENT_DATE, km.birthday))
                ) AS age,
                COALESCE(NULLIF(yp.barangay, ''), NULLIF(km.purok_or_barangay, ''), '') AS barangay,
                u.status,
                u.created_at
             FROM users u
             LEFT JOIN youth_profiles yp ON yp.user_id = u.id
             LEFT JOIN kk_masterlist km ON km.id = u.masterlist_id
             ORDER BY u.created_at DESC, u.id DESC
             LIMIT 5",
            [],
            'registrations'
        );
    }
} catch (Throwable $exception) {
    error_log('[Dashboard] registrations query failed: ' . $exception->getMessage());
}

foreach ($activity as &$entry) {
    $action = str_replace('_', ' ', (string)$entry['action']);
    $entry['text'] = trim((string)$entry['actor'] . ' ' . $action);
    unset($entry['actor'], $entry['action']);
}
unset($entry);

$metrics['nav_events'] = $metrics['events_upcoming'] ?: null;
$metrics['nav_printing'] = $metrics['print_pending'] ?: null;
$metrics['nav_incidents'] = $metrics['incidents_open'] ?: null;

jsonResponse([
    'success' => true,
    'metrics' => $metrics,
    'attention' => $attention,
    'activity' => $activity,
    'registrations' => $registrations
]);
