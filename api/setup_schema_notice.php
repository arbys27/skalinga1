<?php

header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'Database setup endpoints are disabled. Apply database/postgres/schema.sql and its migrations through the Supabase SQL editor.'
]);
exit;
