<?php

declare(strict_types=1);

function skalingaConfig(): array
{
    static $config;
    if (is_array($config)) {
        return $config;
    }

    $appEnvironment = getenv('APP_ENV');
    $appEnvironment = is_string($appEnvironment) && $appEnvironment !== '' ? $appEnvironment : '';
    $dotenvConfig = [];
    $dotenvPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if ($appEnvironment !== 'production' && is_file($dotenvPath)) {
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
            'EMAILJS_RESET_TEMPLATE_ID',
            'EMAILJS_PUBLIC_KEY',
            'EMAILJS_PRIVATE_KEY',
            'EMAILJS_MONTHLY_LIMIT',
            'MAIL_DRIVER',
            'LOGIN_OTP_MODE',
            'SMS_DRIVER',
            'IPROG_API_TOKEN',
            'IPROG_SMS_PROVIDER',
            'UNISMS_API_KEY',
            'UNISMS_SENDER_ID',
        ];
        foreach ($dotenvKeys as $key) {
            if (isset($parsedConfig[$key]) && is_string($parsedConfig[$key])) {
                $dotenvConfig[$key] = $parsedConfig[$key];
            }
        }
    }

    if ($appEnvironment === '') {
        $appEnvironment = $dotenvConfig['APP_ENV'] ?? '';
    }
    if (!is_string($appEnvironment) || $appEnvironment === '') {
        $localPath = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
        if (is_file($localPath)) {
            $localEnvironment = require $localPath;
            if (!is_array($localEnvironment)) {
                throw new RuntimeException('Local configuration must return an array.');
            }
            $appEnvironment = (string)($localEnvironment['APP_ENV'] ?? 'development');
        } else {
            $appEnvironment = 'development';
        }
    }

    $localConfig = [];
    if ($appEnvironment !== 'production') {
        $localConfigPath = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
        if (is_file($localConfigPath)) {
            $localConfig = require $localConfigPath;
            if (!is_array($localConfig)) {
                throw new RuntimeException('Local configuration must return an array.');
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
        'EMAILJS_RESET_TEMPLATE_ID',
        'EMAILJS_PUBLIC_KEY',
        'EMAILJS_PRIVATE_KEY',
        'EMAILJS_MONTHLY_LIMIT',
        'MAIL_DRIVER',
        'LOGIN_OTP_MODE',
        'SMS_DRIVER',
        'IPROG_API_TOKEN',
        'IPROG_SMS_PROVIDER',
        'UNISMS_API_KEY',
        'UNISMS_SENDER_ID',
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
