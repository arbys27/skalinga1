<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function supabaseStorageSettings(?string $bucketOverride = null): array
{
    $config = skalingaConfig();
    $url = trim((string)($config['SUPABASE_URL'] ?? ''));
    $serviceRoleKey = trim((string)($config['SUPABASE_SERVICE_ROLE_KEY'] ?? ''));
    $bucket = trim($bucketOverride ?? (string)($config['SUPABASE_STORAGE_BUCKET'] ?? 'printing'));

    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)
        || !preg_match('/^https:\/\//i', $url)) {
        throw new RuntimeException('Supabase Storage is not configured with a valid HTTPS URL.');
    }
    if ($serviceRoleKey === '') {
        throw new RuntimeException('Supabase Storage service key is not configured.');
    }
    if ($bucket === '' || preg_match('/^[A-Za-z0-9._-]+$/', $bucket) !== 1) {
        throw new RuntimeException('Supabase Storage bucket configuration is invalid.');
    }

    return [
        'url' => rtrim($url, '/'),
        'service_role_key' => $serviceRoleKey,
        'bucket' => $bucket,
    ];
}

function supabaseIncidentStorageBucket(): string
{
    $config = skalingaConfig();
    $bucket = trim((string)($config['SUPABASE_INCIDENTS_BUCKET'] ?? 'incidents'));
    if ($bucket === '' || preg_match('/^[A-Za-z0-9._-]+$/', $bucket) !== 1) {
        throw new RuntimeException('Supabase incident Storage bucket configuration is invalid.');
    }

    return $bucket;
}

function supabaseStorageObjectUrl(array $settings, string $objectPath): string
{
    $segments = array_filter(explode('/', trim($objectPath, '/')), static fn($segment) => $segment !== '');
    if ($segments === []) {
        throw new RuntimeException('Supabase Storage object path is empty.');
    }

    return $settings['url'] . '/storage/v1/object/' . rawurlencode($settings['bucket'])
        . '/' . implode('/', array_map('rawurlencode', $segments));
}

function supabaseStorageHeaders(array $settings, string $contentType = 'application/octet-stream'): array
{
    return [
        'Authorization: Bearer ' . $settings['service_role_key'],
        'apikey: ' . $settings['service_role_key'],
        'Content-Type: ' . $contentType,
        'Cache-Control: no-store',
    ];
}

function supabaseStorageUpload(
    string $objectPath,
    string $localFilePath,
    string $contentType,
    ?string $bucketOverride = null
): void {
    if (!is_file($localFilePath) || !is_readable($localFilePath)) {
        throw new RuntimeException('The uploaded document is no longer available.');
    }

    $settings = supabaseStorageSettings($bucketOverride);
    $body = file_get_contents($localFilePath);
    if ($body === false) {
        throw new RuntimeException('Unable to read the uploaded document for Storage.');
    }

    $curl = curl_init(supabaseStorageObjectUrl($settings, $objectPath));
    if ($curl === false) {
        throw new RuntimeException('Unable to initialize the Supabase Storage request.');
    }

    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => supabaseStorageHeaders($settings, $contentType),
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 120,
    ]);
    $response = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($response === false || $error !== '') {
        throw new RuntimeException('Supabase Storage upload failed: ' . ($error !== '' ? $error : 'no response'));
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('Supabase Storage rejected the document upload (HTTP ' . $status . ').');
    }
}

function supabaseStorageDelete(string $objectPath, ?string $bucketOverride = null): void
{
    $settings = supabaseStorageSettings($bucketOverride);
    $curl = curl_init(supabaseStorageObjectUrl($settings, $objectPath));
    if ($curl === false) {
        throw new RuntimeException('Unable to initialize the Supabase Storage cleanup request.');
    }

    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => supabaseStorageHeaders($settings),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($response === false || $error !== '' || $status < 200 || $status >= 300) {
        throw new RuntimeException('Supabase Storage cleanup failed.');
    }
}

function supabaseStorageCreateSignedUrl(
    string $objectPath,
    int $expiresIn = 600,
    ?string $bucketOverride = null
): string
{
    if ($expiresIn < 300 || $expiresIn > 900) {
        throw new RuntimeException('Supabase Storage signed URL lifetime must be between 5 and 15 minutes.');
    }

    $settings = supabaseStorageSettings($bucketOverride);
    $segments = array_filter(explode('/', trim($objectPath, '/')), static fn($segment) => $segment !== '');
    if ($segments === []) {
        throw new RuntimeException('Supabase Storage object path is empty.');
    }

    $endpoint = $settings['url'] . '/storage/v1/object/sign/' . rawurlencode($settings['bucket'])
        . '/' . implode('/', array_map('rawurlencode', $segments));
    $curl = curl_init($endpoint);
    if ($curl === false) {
        throw new RuntimeException('Unable to initialize the Supabase signed URL request.');
    }

    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => supabaseStorageHeaders($settings, 'application/json'),
        CURLOPT_POSTFIELDS => json_encode(['expiresIn' => $expiresIn], JSON_THROW_ON_ERROR),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($response === false || $error !== '') {
        throw new RuntimeException('Supabase Storage signed URL request failed.');
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('Supabase Storage rejected the signed URL request (HTTP ' . $status . ').');
    }

    $payload = json_decode($response, true);
    $signedPath = is_array($payload)
        ? ($payload['signedURL'] ?? ($payload['signedUrl'] ?? null))
        : null;
    if (!is_string($signedPath) || $signedPath === '') {
        throw new RuntimeException('Supabase Storage returned an invalid signed URL.');
    }

    if (str_starts_with($signedPath, 'http://') || str_starts_with($signedPath, 'https://')) {
        return $signedPath;
    }

    return $settings['url'] . '/' . ltrim($signedPath, '/');
}
