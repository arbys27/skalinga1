<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security_helpers.php';
requireAdminApi();
securityRequireCsrf();
http_response_code(410);
echo json_encode([
    'success' => false,
    'error' => 'Direct returns are disabled. Use the verified pending-request workflow in Resources Management.'
]);
