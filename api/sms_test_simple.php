<?php
header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'The unauthenticated SMS test endpoint is disabled. Use the protected OTP verification flow.'
]);
