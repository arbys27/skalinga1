<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

require_once __DIR__ . '/../api/db_connect.php';

fwrite(STDOUT, "Admin email: ");
$email = trim((string)fgets(STDIN));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "A valid email address is required.\n");
    exit(1);
}

fwrite(STDOUT, "Strong password (12+ characters, upper/lowercase, number and symbol): ");
$password = trim((string)fgets(STDIN));
if (strlen($password) < 12
    || preg_match('/[a-z]/', $password) !== 1
    || preg_match('/[A-Z]/', $password) !== 1
    || preg_match('/[0-9]/', $password) !== 1
    || preg_match('/[^a-zA-Z0-9]/', $password) !== 1) {
    fwrite(STDERR, "Password does not meet the required strength.\n");
    exit(1);
}

$localPart = strstr($email, '@', true);
$username = substr((string)preg_replace('/[^a-zA-Z0-9_.-]/', '_', $localPart), 0, 50);
if ($username === '') {
    fwrite(STDERR, "Could not derive an admin username from the email address.\n");
    exit(1);
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'INSERT INTO admins (username, email, password_hash, role, status)
         VALUES (?, ?, ?, ?, ?)
         RETURNING id'
    );
    $stmt->execute([
        $username,
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        'superadmin',
        'active',
    ]);
    $adminId = (int)$stmt->fetchColumn();
    $pdo->commit();
    fwrite(STDOUT, "Created superadmin account {$username} (ID {$adminId}).\n");
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Create Admin] ' . $exception->getMessage());
    fwrite(STDERR, "Could not create the admin account. Check that the email and derived username are not already registered.\n");
    exit(1);
}
