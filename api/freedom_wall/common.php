<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/security_helpers.php';

function freedomWallResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function freedomWallInput(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }

    $raw = file_get_contents('php://input');
    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $input = json_decode($raw, true);
    return is_array($input) ? $input : [];
}

function freedomWallLength(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    $count = preg_match_all('/./us', $value);
    return $count === false ? strlen($value) : $count;
}

/**
 * Safety flags are advisory only: moderators make the approval decision.
 * The short blocklist and spam heuristics are intentionally kept here so the
 * admin list can explain why a post needs attention without auto-rejecting it.
 */
function freedomWallAttentionFlags(string $title, string $body): array
{
    $text = trim($title . ' ' . $body);
    $lowerText = function_exists('mb_strtolower')
        ? mb_strtolower($text, 'UTF-8')
        : strtolower($text);
    $flags = [];
    foreach (['kill', 'bomb', 'threat', 'fuck'] as $word) {
        if (preg_match('/\b' . preg_quote($word, '/') . '\b/u', $lowerText) === 1) {
            $flags[] = 'Contains a flagged word';
            break;
        }
    }

    $letters = preg_replace('/[^a-z]/i', '', $text);
    $uppercase = preg_replace('/[^A-Z]/', '', $text);
    if (strlen($letters) >= 20 && strlen($uppercase) / strlen($letters) >= 0.75) {
        $flags[] = 'Excessive uppercase text';
    }
    if (preg_match('/(.)\1{5,}/u', $text) === 1) {
        $flags[] = 'Repeated characters';
    }

    return array_values(array_unique($flags));
}

function freedomWallRequireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        freedomWallResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
}

function freedomWallLog(PDO $pdo, array $auth, string $action, int $postId): void
{
    require_once dirname(__DIR__, 2) . '/includes/request.php';
    $stmt = $pdo->prepare(
        'INSERT INTO activity_logs (user_id, role, action, identifier, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $auth['id'],
        $auth['role'],
        $action,
        (string)$postId,
        clientIp(),
    ]);
}

function freedomWallDatabaseError(PDOException $exception): void
{
    error_log('[Freedom Wall] Database request failed: ' . $exception->getMessage());
    freedomWallResponse(['success' => false, 'message' => 'Unable to process the Freedom Wall request.'], 500);
}
