<?php
/**
 * LOCAL DEVELOPMENT CONFIG
 * 
 * Copy this to config.php when developing locally with Docker.
 * Points to the Docker PostgreSQL container on port 5433.
 * 
 * DO NOT commit this file or config.php — both are gitignored.
 */

return [
    // Database — Docker PostgreSQL container
    'db_host'     => 'db',
    'db_port'     => '5433',        // Docker maps 5433 -> 5432 inside container
    'db_name'     => 'hwchat',
    'db_user'     => 'hwchat',
    'db_password' => 'hwchat_local',

    // PostgreSQL DSN (used by Database.php)
    'pg_host'     => 'db',
    'pg_port'     => '5433',
    'pg_dbname'   => 'hwchat',
    'pg_user'     => 'hwchat',
    'pg_password' => 'hwchat_local',

    // LLM API keys — use your own dev keys or leave empty to skip LLM features
    'openai_api_key'    => '',
    'anthropic_api_key' => '',

    // Local dev flag — can be used to skip certain production-only behavior
    'environment' => 'local',
];
