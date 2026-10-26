<?php
/**
 * Mail Configuration & Environment Loader
 * Loads SMTP configuration securely without hardcoding credentials in codebase.
 */

function loadEnv($path = __DIR__ . '/../.env') {
    if (!file_exists($path)) {
        return [];
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Strip surrounding quotes
            $value = trim($value, "\"'");
            $env[$key] = $value;
            // Also populate getenv / $_ENV if not set
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
    return $env;
}

$env = loadEnv();

return [
    'host'       => getenv('SMTP_HOST') ?: ($env['SMTP_HOST'] ?? 'smtp.gmail.com'),
    'port'       => (int)(getenv('SMTP_PORT') ?: ($env['SMTP_PORT'] ?? 587)),
    'secure'     => getenv('SMTP_SECURE') ?: ($env['SMTP_SECURE'] ?? 'tls'),
    'username'   => getenv('SMTP_USERNAME') ?: ($env['SMTP_USERNAME'] ?? ''),
    'password'   => getenv('SMTP_PASSWORD') ?: ($env['SMTP_PASSWORD'] ?? ''),
    'from_email' => getenv('SMTP_USERNAME') ?: ($env['SMTP_USERNAME'] ?? ''),
    'from_name'  => getenv('SMTP_FROM_NAME') ?: ($env['SMTP_FROM_NAME'] ?? 'CTU Registrar Office'),
];

