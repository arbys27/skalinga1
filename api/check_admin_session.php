<?php

require_once 'auth.php';

$auth = requireAuth(['admin', 'sk_official']);
authJsonResponse([
    'authenticated' => true,
    'admin' => [
        'id' => $auth['id'],
        'username' => $auth['identifier'],
        'role' => $auth['role'] === 'admin' ? 'superadmin' : 'staff'
    ]
]);
