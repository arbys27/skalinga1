<?php

require_once __DIR__ . '/../includes/request.php';
secureSessionStart();
header('Content-Type: application/json; charset=utf-8');

require_once 'auth.php';
requireAdminApi();

http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'Permanent deletion is disabled. Use the inactive status action and provide a reason.'
]);
