<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

$errors = [];
$requiredExtensions = ['pdo_pgsql', 'pgsql', 'curl', 'fileinfo'];
foreach ($requiredExtensions as $extension) {
    if (!extension_loaded($extension)) {
        $errors[] = "Required PHP extension is not loaded: {$extension}";
    }
}

$configPath = __DIR__ . '/../includes/config.local.php';
$config = null;
try {
    require_once __DIR__ . '/../includes/config.php';
    $config = skalingaConfig();
    if (!str_contains($config['DB_HOST'], 'pooler.supabase.com')) {
        $errors[] = 'DB_HOST must be the Supabase Session Pooler host.';
    }
    if (!preg_match('/^postgres\.[a-z0-9]+$/i', $config['DB_USER'])) {
        $errors[] = 'DB_USER must use the Session Pooler format postgres.<PROJECT_REF>.';
    }
    if (str_starts_with($config['DB_PASSWORD'], 'REPLACE_')) {
        $errors[] = 'DB_PASSWORD still contains the example placeholder.';
    }
} catch (Throwable $exception) {
    if (!is_file($configPath)
        && getenv('DB_HOST') === false
        && getenv('DB_USER') === false
        && getenv('DB_PASSWORD') === false) {
        $errors[] = 'Missing includes/config.local.php, .env, or deployment database environment variables.';
    } else {
        $errors[] = 'Configuration error: ' . $exception->getMessage();
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Environment check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }
    exit(1);
}

$expectedTables = [
    'users',
    'youth_profiles',
    'resources',
    'borrow_records',
    'events',
    'incidents',
    'printing_requests',
    'password_resets',
    'admins',
    'kk_masterlist',
    'login_attempts',
    'activity_logs',
    'health_announcements',
    'public_disclosures',
];

try {
    $dsn = sprintf(
        'pgsql:host=%s;port=5432;dbname=postgres;sslmode=require',
        $config['DB_HOST']
    );
    $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10,
    ]);
    $pdo->exec("SET TIME ZONE 'Asia/Manila'");
    fwrite(STDOUT, "PHP PostgreSQL extensions: OK\n");
    fwrite(STDOUT, "Database configuration: OK\n");
    fwrite(STDOUT, "Supabase Session Pooler connection (port 5432): OK\n");

    $tables = $pdo->query(
        "SELECT c.relname AS table_name, c.relrowsecurity AS rls_enabled
         FROM pg_class c
         INNER JOIN pg_namespace n ON n.oid = c.relnamespace
         WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p')"
    )->fetchAll();
    $tableMap = [];
    foreach ($tables as $table) {
        $tableMap[$table['table_name']] = $table;
    }

    $missingTables = array_values(array_diff($expectedTables, array_keys($tableMap)));
    if ($missingTables !== []) {
        throw new RuntimeException('Expected tables are missing: ' . implode(', ', $missingTables));
    }

    $rlsDisabled = [];
    foreach ($expectedTables as $tableName) {
        $rlsEnabled = $tableMap[$tableName]['rls_enabled'];
        if (!in_array($rlsEnabled, [true, 1, '1', 't', 'true'], true)) {
            $rlsDisabled[] = $tableName;
        }
    }
    if ($rlsDisabled !== []) {
        throw new RuntimeException('Row Level Security is not enabled for: ' . implode(', ', $rlsDisabled));
    }

    $policyRows = $pdo->query(
        "SELECT tablename, policyname
         FROM pg_policies
         WHERE schemaname = 'public'"
    )->fetchAll();
    $unexpectedPolicies = [];
    foreach ($policyRows as $policy) {
        if (in_array($policy['tablename'], $expectedTables, true)) {
            $unexpectedPolicies[] = $policy['tablename'] . '.' . $policy['policyname'];
        }
    }
    if ($unexpectedPolicies !== []) {
        throw new RuntimeException(
            'Expected tables must have no Data API policies; found: ' . implode(', ', $unexpectedPolicies)
        );
    }

    fwrite(STDOUT, 'Expected application tables: OK (' . count($expectedTables) . ")\n");
    fwrite(STDOUT, "Row Level Security enabled with no policies: OK\n");
    fwrite(STDOUT, "Environment check passed.\n");
} catch (Throwable $exception) {
    error_log('[Environment Check] ' . $exception->getMessage());
    fwrite(STDERR, "Environment check failed: " . $exception->getMessage() . "\n");
    exit(1);
}
