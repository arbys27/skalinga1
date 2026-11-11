<?php

require_once 'masterlist_common.php';
require_once __DIR__ . '/security_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    masterlistJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

require_once 'db_connect.php';

try {
    enforceMasterlistRateLimit($pdo, 'masterlist_verify');

    $voterId = normalizeMasterlistValue($_POST['voter_id'] ?? '');
    $fullName = normalizeMasterlistName($_POST['full_name'] ?? '');
    $birthday = trim($_POST['birthday'] ?? '');

    if ($voterId === '' || $fullName === '' || !DateTime::createFromFormat('!Y-m-d', $birthday)) {
        masterlistJsonResponse([
            'success' => false,
            'message' => 'Please provide a valid voter ID, full name, and birthday.'
        ], 400);
    }

    $record = masterlistFindRecord($pdo, $voterId, $fullName, $birthday);
    if (!$record) {
        masterlistJsonResponse([
            'success' => false,
            'message' => 'No matching youth record was found. Please contact the Sangguniang Kabataan Office.'
        ], 404);
    }

    if (postgresBoolean($record['is_claimed'])) {
        masterlistJsonResponse([
            'success' => false,
            'message' => 'An account already exists for this youth record.'
        ], 409);
    }

    securityStartSession();
    session_regenerate_id(true);
    $_SESSION['activation_masterlist_id'] = (int)$record['id'];
    unset(
        $_SESSION['registration_sms_challenge_id'],
        $_SESSION['verified_activation_challenge_id'],
        $_SESSION['phone_verified'],
        $_SESSION['verified_phone'],
        $_SESSION['verified_phone_at']
    );
    masterlistJsonResponse([
        'success' => true,
        'profile' => [
            'first_name' => $record['first_name'],
            'middle_name' => $record['middle_name'],
            'last_name' => $record['last_name'],
            'birthday' => $record['birthday'],
            'age' => (int)$record['age'],
            'sex' => $record['sex'],
            'address' => $record['address']
        ]
    ]);
} catch (Throwable $exception) {
    $reference = securityLogException('Masterlist Verify Error', $exception);
    masterlistJsonResponse([
        'success' => false,
        'message' => 'Unable to verify the masterlist record. Reference: ' . $reference,
        'reference' => $reference
    ], 500);
}
