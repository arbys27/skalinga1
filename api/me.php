<?php

require_once 'auth.php';

$auth = currentAuth();
if ($auth === null) {
    authJsonResponse(['authenticated' => false, 'message' => 'Not authenticated'], 401);
}

authJsonResponse([
    'authenticated' => true,
    'user' => $auth,
    'role' => $auth['role']
]);
