<?php

header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'Open registration is no longer available. Verify your KK Masterlist record to activate an account.'
]);
