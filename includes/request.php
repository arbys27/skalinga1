<?php

declare(strict_types=1);

function isHttps(): bool
{
    if (isset($_SERVER['HTTPS'])
        && in_array(strtolower((string)$_SERVER['HTTPS']), ['on', '1', 'https'], true)) {
        return true;
    }

    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $isLocalProxy = $remoteAddr === '127.0.0.1' || $remoteAddr === '::1';
    $isRailwayProxy = getenv('RAILWAY_ENVIRONMENT_NAME') !== false
        || getenv('RAILWAY_PUBLIC_DOMAIN') !== false;
    if (!$isLocalProxy && !$isRailwayProxy) {
        return false;
    }

    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if ($isLocalProxy && $forwardedProto === '') {
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTOCOL'] ?? '';
    }
    if (is_string($forwardedProto) && $forwardedProto !== '') {
        $proto = strtolower(trim(explode(',', $forwardedProto)[0]));
        if ($proto === 'https') {
            return true;
        }
        if ($proto === 'http') {
            return false;
        }
    }

    $cfVisitor = $_SERVER['HTTP_CF_VISITOR'] ?? '';
    if ($isLocalProxy && is_string($cfVisitor) && $cfVisitor !== '') {
        $decoded = json_decode($cfVisitor, true);
        if (is_array($decoded) && isset($decoded['scheme'])) {
            return strtolower((string)$decoded['scheme']) === 'https';
        }
    }

    return false;
}

function clientIp(): string
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($remoteAddr) || filter_var($remoteAddr, FILTER_VALIDATE_IP) === false) {
        return 'unknown';
    }

    $isLocalProxy = $remoteAddr === '127.0.0.1' || $remoteAddr === '::1';
    if (!$isLocalProxy) {
        $isRailwayProxy = getenv('RAILWAY_ENVIRONMENT_NAME') !== false
            || getenv('RAILWAY_PUBLIC_DOMAIN') !== false;
        if ($isRailwayProxy) {
            $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if (is_string($forwardedFor)) {
                foreach (array_map('trim', explode(',', $forwardedFor)) as $candidate) {
                    if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                        return $candidate;
                    }
                }
            }
        }
        return $remoteAddr;
    }

    $forwardedIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if (is_string($forwardedIp) && filter_var(trim($forwardedIp), FILTER_VALIDATE_IP) !== false) {
        return trim($forwardedIp);
    }

    return is_string($remoteAddr) && filter_var($remoteAddr, FILTER_VALIDATE_IP) !== false ? $remoteAddr : 'unknown';
}

function secureSessionStart(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
