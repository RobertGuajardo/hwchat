<?php
/**
 * RobChat API Bootstrap
 *
 * Every API endpoint requires this file. It:
 * - Loads config
 * - Connects to PostgreSQL
 * - Sets up CORS headers
 * - Provides JSON response helpers
 */

// Load config
$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration missing.']);
    exit;
}
$config = require $configPath;

// Load Database class
require_once __DIR__ . '/../lib/Database.php';

// Connect to PostgreSQL
try {
    Database::connect($config);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

// ---------------------------------------------------------------------------
// CORS — handle preflight immediately (before endpoint method checks)
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = $config['global_allowed_origins'] ?? [];

    if (in_array($origin, $allowed, true) || in_array('*', $allowed, true)) {
        header("Access-Control-Allow-Origin: $origin");
    }

    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

// ---------------------------------------------------------------------------
// CORS Handler (for non-preflight requests)
// ---------------------------------------------------------------------------
function handleCors(array $config, ?string $tenantId = null): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    // Build allowed origins list
    $allowed = $config['global_allowed_origins'] ?? [];

    // Add tenant-specific origins if we have a tenant ID
    if ($tenantId) {
        $tenant = Database::getTenantConfig($tenantId);
        if ($tenant && !empty($tenant['allowed_origins'])) {
            $allowed = array_merge($allowed, $tenant['allowed_origins']);
        }
    }

    // Check if the requesting origin is allowed
    if (in_array($origin, $allowed, true) || in_array('*', $allowed, true)) {
        header("Access-Control-Allow-Origin: $origin");
    }

    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
    header('Content-Type: application/json; charset=utf-8');

    // Handle preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ---------------------------------------------------------------------------
// JSON Helpers
// ---------------------------------------------------------------------------
function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $message, int $status = 400): void
{
    jsonResponse(['error' => $message], $status);
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function getIpHash(): string
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    // Take first IP if multiple (proxy chain)
    $ip = explode(',', $ip)[0];
    return hash('sha256', trim($ip));
}
