<?php
/**
 * RobChat Backend Configuration — EXAMPLE
 * -----------------------------------------
 * Copy this file to config.php and fill in your actual values.
 * NEVER commit config.php to version control.
 *
 * Environment variables (for Docker) override hardcoded values.
 */

return [
    // PostgreSQL connection
    'db_host'     => getenv('DB_HOST') ?: 'localhost',
    'db_port'     => (int)(getenv('DB_PORT') ?: 5432),
    'db_name'     => getenv('DB_NAME') ?: 'robchat',
    'db_user'     => getenv('DB_USER') ?: 'robchat',
    'db_password' => getenv('DB_PASSWORD') ?: 'your-secure-password',

    // App-level encryption key for API keys stored in DB
    // Generate with: php -r "echo bin2hex(random_bytes(32));"
    'encryption_key' => getenv('ENCRYPTION_KEY') ?: 'your-64-char-hex-encryption-key',

    // Default LLM keys (used if tenant hasn't set their own)
    'default_openai_key'    => getenv('OPENAI_API_KEY') ?: 'sk-YOUR-OPENAI-API-KEY',
    'default_anthropic_key' => getenv('ANTHROPIC_API_KEY') ?: 'sk-ant-YOUR-ANTHROPIC-API-KEY',

    // CORS — origins allowed for all tenants (in addition to per-tenant origins)
    'global_allowed_origins' => [
        'http://localhost:4321',
        'http://localhost:3000',
        'http://localhost:8080',
        'http://127.0.0.1:5500',
    ],
];
