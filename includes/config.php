<?php

declare(strict_types=1);

function skalingaConfig(): array
{
    static $config;
    if (is_array($config)) {
        return $config;
    }

    $localConfigPath = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
    $localConfig = [];
    if (is_file($localConfigPath)) {
        $localConfig = require $localConfigPath;
        if (!is_array($localConfig)) {
            throw new RuntimeException('Local configuration must return an array.');
        }
    }

    $dotenvConfig = [];
    $dotenvPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (is_file($dotenvPath)) {
        $parsedConfig = parse_ini_file($dotenvPath, false, INI_SCANNER_RAW);
        if (!is_array($parsedConfig)) {
            throw new RuntimeException('Unable to parse the .env configuration file.');
        }
        $dotenvKeys = [
            'DB_HOST',
            'DB_USER',
            'DB_PASSWORD',
            'SITE_KEY',
            'APP_ENV',
            'EMAILJS_ORIGIN',
            'EMAILJS_SERVICE_ID',
            'EMAILJS_TEMPLATE_ID',
            'EMAILJS_PUBLIC_KEY',
            'SKALINGA_SMS_API_URL',
            'SKALINGA_SMS_API_KEY',
        ];
        foreach ($dotenvKeys as $key) {
            if (isset($parsedConfig[$key]) && is_string($parsedConfig[$key])) {
                $dotenvConfig[$key] = $parsedConfig[$key];
            }
        }
    }

    $environmentConfig = [];
    foreach ([
        'DB_HOST',
        'DB_USER',
        'DB_PASSWORD',
        'SITE_KEY',
        'APP_ENV',
        'EMAILJS_ORIGIN',
        'EMAILJS_SERVICE_ID',
        'EMAILJS_TEMPLATE_ID',
        'EMAILJS_PUBLIC_KEY',
        'SKALINGA_SMS_API_URL',
        'SKALINGA_SMS_API_KEY',
    ] as $key) {
        $environmentValue = getenv($key);
        if ($environmentValue !== false && $environmentValue !== '') {
            $environmentConfig[$key] = $environmentValue;
        }
    }
    $config = array_replace($localConfig, $dotenvConfig, $environmentConfig);

    foreach (['DB_HOST', 'DB_USER', 'DB_PASSWORD', 'SITE_KEY', 'APP_ENV'] as $key) {
        if (!isset($config[$key]) || !is_string($config[$key]) || trim($config[$key]) === '') {
            throw new RuntimeException('Missing required configuration value: ' . $key);
        }
    }

    if (preg_match('/[;\\s]/', $config['DB_HOST']) === 1) {
        throw new RuntimeException('DB_HOST contains invalid characters.');
    }
    if (preg_match('/[;\\s]/', $config['DB_USER']) === 1) {
        throw new RuntimeException('DB_USER contains invalid characters.');
    }
    if (!preg_match('/^[a-f0-9]{64}$/i', $config['SITE_KEY'])) {
        throw new RuntimeException('SITE_KEY must be exactly 64 hexadecimal characters.');
    }
    if (!in_array($config['APP_ENV'], ['development', 'testing', 'production'], true)) {
        throw new RuntimeException('APP_ENV must be development, testing, or production.');
    }

    return $config;
}

return skalingaConfig();
