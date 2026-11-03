<?php

require_once 'db_connect.php';
require_once 'masterlist_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    masterlistJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$voterId = normalizeMasterlistValue($_POST['voter_id'] ?? '');
$fullName = normalizeMasterlistName($_POST['full_name'] ?? '');
$birthday = trim($_POST['birthday'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($voterId === '' || $fullName === '' || !DateTime::createFromFormat('!Y-m-d', $birthday)) {
    masterlistJsonResponse(['success' => false, 'message' => 'Please complete the masterlist verification first.'], 400);
}
if (!preg_match('/^09[0-9]{9}$/', $mobile)) {
    masterlistJsonResponse(['success' => false, 'message' => 'A valid 11-digit Philippine mobile number is required.'], 400);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
    $record = masterlistFindRecord($pdo, $voterId, $fullName, $birthday, true);

    if (!$record) {
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

    $stmt = $pdo->prepare('SELECT id FROM youth_profiles WHERE phone = ? LIMIT 1');
    $stmt->execute([$mobile]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        masterlistJsonResponse(['success' => false, 'message' => 'Mobile number already registered.'], 409);
    }

    if ($email !== '') {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            masterlistJsonResponse(['success' => false, 'message' => 'Email already registered.'], 409);
        }
    }

    do {
        $memberId = 'SK-' . date('Y') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare('SELECT id FROM users WHERE member_id = ?');
        $stmt->execute([$memberId]);
    } while ($stmt->fetch());

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (email, password_hash, member_id, masterlist_id, status, email_verified, created_at)
         VALUES (?, ?, ?, ?, \'active\', FALSE, CURRENT_TIMESTAMP)
         RETURNING id'
    );
    $stmt->execute([$email !== '' ? $email : null, $passwordHash, $memberId, $record['id']]);
    $userId = (int)$stmt->fetchColumn();

    $sex = strtolower(trim($record['sex']));
    $gender = $sex === 'male' ? 'Male' : ($sex === 'female' ? 'Female' : 'Other');
    $stmt = $pdo->prepare(
        'INSERT INTO youth_profiles
         (user_id, firstname, lastname, birthday, age, gender, phone, address, barangay, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
    );
    $stmt->execute([
        $userId,
        $record['first_name'],
        $record['last_name'],
        $record['birthday'],
        $record['age'],
        $gender,
        $mobile,
        $record['address'],
        $record['purok_or_barangay']
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
    error_log('[Masterlist Activate] ' . $exception->getMessage());
    masterlistJsonResponse(['success' => false, 'message' => 'Account activation failed. Please try again.'], 500);
}
