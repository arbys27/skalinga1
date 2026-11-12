<?php

require_once 'masterlist_common.php';
require_once __DIR__ . '/../includes/sms.php';
require_once __DIR__ . '/../includes/turnstile.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    masterlistJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$verification = verifyTurnstile(
    is_string($_POST['cf-turnstile-response'] ?? null) ? $_POST['cf-turnstile-response'] : '',
    'activate'
);
if (!$verification['success']) {
    masterlistJsonResponse(['success' => false, 'message' => $verification['message']], 400);
}

require_once 'db_connect.php';
require_once __DIR__ . '/security_helpers.php';
securityStartSession();
securityRequireCsrf();
enforceMasterlistRateLimit($pdo, 'activate', 5);

$fullName = submittedMasterlistName($_POST);
$birthday = trim($_POST['birthday'] ?? '');
$houseNumber = is_string($_POST['house_number'] ?? null) ? trim($_POST['house_number']) : '';
$streetName = is_string($_POST['street_name'] ?? null) ? trim($_POST['street_name']) : '';
$subdivisionPurok = is_string($_POST['subdivision_purok'] ?? null) ? trim($_POST['subdivision_purok']) : '';
$city = is_string($_POST['city'] ?? null) ? trim($_POST['city']) : '';
$province = is_string($_POST['province'] ?? null) ? trim($_POST['province']) : '';
$rawMobile = is_string($_POST['mobile'] ?? null) ? $_POST['mobile'] : '';
$email = trim($_POST['email'] ?? '');
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

if ($fullName === '' || !DateTime::createFromFormat('!Y-m-d', $birthday)) {
    masterlistJsonResponse(['success' => false, 'message' => 'Please complete the masterlist verification first.'], 400);
}
if ($houseNumber === '' || $streetName === '' || $subdivisionPurok === '' || $city === '' || $province === '') {
    masterlistJsonResponse(['success' => false, 'message' => 'Please complete all address fields.'], 400);
}
try {
    $mobile = normalizePhPhone($rawMobile);
} catch (InvalidArgumentException $exception) {
    masterlistJsonResponse(['success' => false, 'message' => $exception->getMessage()], 400);
}
if ((int)($_SESSION['activation_masterlist_id'] ?? 0) <= 0
    || !is_string($_SESSION['verified_activation_challenge_id'] ?? null)
    || !is_string($_SESSION['verified_phone'] ?? null)
    || !hash_equals($mobile, (string)$_SESSION['verified_phone'])
    || time() - (int)($_SESSION['verified_phone_at'] ?? 0) > 600) {
    masterlistJsonResponse(['success' => false, 'message' => 'Verify this mobile number before activating your account.'], 403);
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    masterlistJsonResponse(['success' => false, 'message' => 'Please provide a valid email address.'], 400);
}
if (strlen($password) < 8) {
    masterlistJsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters.'], 400);
}
if ($password !== $confirmPassword) {
    masterlistJsonResponse(['success' => false, 'message' => 'Passwords do not match.'], 400);
}

try {
    $pdo->beginTransaction();
    $record = masterlistFindRecord(
        $pdo,
        $fullName,
        $birthday,
        true,
        (int)$_SESSION['activation_masterlist_id']
    );

    if (!$record || (int)$record['id'] !== (int)$_SESSION['activation_masterlist_id']) {
        $pdo->rollBack();
        masterlistJsonResponse([
            'success' => false,
            'message' => 'No matching youth record was found. Please contact the Sangguniang Kabataan Office.'
        ], 404);
    }
    if (postgresBoolean($record['is_claimed'])) {
        $pdo->rollBack();
        masterlistJsonResponse([
            'success' => false,
            'message' => 'An account already exists for this youth record.'
        ], 409);
    }

    $phoneChallenge = $pdo->prepare(
        'SELECT id FROM registration_sms_challenges
         WHERE id = ?::uuid AND masterlist_id = ? AND phone = ?
           AND verified_at IS NOT NULL
           AND verified_at >= CURRENT_TIMESTAMP - INTERVAL \'10 minutes\''
    );
    $phoneChallenge->execute([
        $_SESSION['verified_activation_challenge_id'],
        (int)$record['id'],
        $mobile
    ]);
    if (!$phoneChallenge->fetchColumn()) {
        $pdo->rollBack();
        masterlistJsonResponse(['success' => false, 'message' => 'Verify this mobile number before activating your account.'], 403);
    }

    $stmt = $pdo->prepare('SELECT id FROM youth_profiles WHERE phone = ? LIMIT 1');
    $stmt->execute([$mobile]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        masterlistJsonResponse(['success' => false, 'message' => 'Mobile number already registered.'], 409);
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        masterlistJsonResponse(['success' => false, 'message' => 'Email already registered.'], 409);
    }

    do {
        $memberId = 'SK-' . date('Y') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare('SELECT id FROM users WHERE member_id = ?');
        $stmt->execute([$memberId]);
    } while ($stmt->fetch());

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (email, password_hash, member_id, masterlist_id, status, email_verified, phone_verified_at, created_at)
         VALUES (?, ?, ?, ?, \'active\', FALSE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
         RETURNING id'
    );
    $stmt->execute([$email, $passwordHash, $memberId, $record['id']]);
    $userId = (int)$stmt->fetchColumn();

    $sex = strtolower(trim($record['sex']));
    $gender = $sex === 'male' ? 'Male' : ($sex === 'female' ? 'Female' : 'Other');
    $address = implode(', ', [
        trim($houseNumber . ' ' . $streetName),
        $subdivisionPurok,
        $city,
        $province
    ]);
    $stmt = $pdo->prepare(
        'INSERT INTO youth_profiles
         (user_id, firstname, middle_name, lastname, birthday, age, gender, phone, address, barangay,
          house_number, street_name, subdivision_purok, city, province, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
    );
    $stmt->execute([
        $userId,
        $record['first_name'],
        $record['middle_name'],
        $record['last_name'],
        $record['birthday'],
        $record['age'],
        $gender,
        $mobile,
        $address,
        $record['purok_or_barangay'],
        $houseNumber,
        $streetName,
        $subdivisionPurok,
        $city,
        $province
    ]);

    $stmt = $pdo->prepare(
        'UPDATE kk_masterlist
         SET is_claimed = TRUE, claimed_by_user_id = ?
         WHERE id = ? AND is_claimed = FALSE'
    );
    $stmt->execute([$userId, $record['id']]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Masterlist record was claimed by another request.');
    }

    $pdo->commit();
    unset(
        $_SESSION['activation_masterlist_id'],
        $_SESSION['registration_sms_challenge_id'],
        $_SESSION['verified_activation_challenge_id'],
        $_SESSION['phone_verified'],
        $_SESSION['verified_phone'],
        $_SESSION['verified_phone_at']
    );
    masterlistJsonResponse([
        'success' => true,
        'message' => 'Account activated successfully.',
        'member_id' => $memberId,
        'redirect' => 'login.html'
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $reference = securityLogException('Masterlist Activate Error', $exception, [$password, $confirmPassword, $mobile, $email]);
    masterlistJsonResponse([
        'success' => false,
        'message' => 'Account activation failed. Please try again. Reference: ' . $reference,
        'reference' => $reference
    ], 500);
}
