<?php

require_once dirname(__DIR__) . '/includes/request.php';

function authJsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function authStartSession(): void
{
    secureSessionStart();
}

function currentAuth(): ?array
{
    authStartSession();

    if (!isset($_SESSION['auth_user_id'], $_SESSION['auth_role'])) {
        return null;
    }

    return [
        'id' => (int)$_SESSION['auth_user_id'],
        'role' => $_SESSION['auth_role'],
        'identifier' => $_SESSION['auth_identifier'] ?? null,
        'name' => $_SESSION['auth_name'] ?? null,
        'member_id' => $_SESSION['member_id'] ?? null
    ];
}

function requireAuth(array $allowedRoles = []): array
{
    $auth = currentAuth();
    if ($auth === null) {
        authJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    if ($allowedRoles && !in_array($auth['role'], $allowedRoles, true)) {
        authJsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
    }

    return $auth;
}

function requireAdminApi(): array
{
    return requireAuth(['admin', 'sk_official']);
}
