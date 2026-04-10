<?php
/**
 * Backfill embeddings for all kb_entries that don't have one yet.
 *
 * Usage: php backfill-embeddings.php [--tenant=TENANT_ID] [--batch=50]
 *
 * Run from the backend directory:
 *   cd /home/rober253/chat.robertguajardo.com/backend
 *   php scripts/backfill-embeddings.php
 */

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Embeddings.php';

// Load config
$config = require __DIR__ . '/../config.php';
Database::connect($config);
$db = Database::db();

// Parse CLI args
$tenantFilter = null;
$batchSize = 50;
foreach ($argv as $arg) {
    if (strpos($arg, '--tenant=') === 0) {
        $tenantFilter = substr($arg, 9);
    }
    if (strpos($arg, '--batch=') === 0) {
        $batchSize = (int)substr($arg, 8);
    }
}

// Get API key from config (global default)
$apiKey = $config['default_openai_key'] ?? '';
if (empty($apiKey)) {
    fwrite(STDERR, "ERROR: No OpenAI API key in config.\n");
    exit(1);
}

// Find entries without embeddings
$sql = 'SELECT id, tenant_id, title, content FROM kb_entries WHERE embedding IS NULL AND is_active = TRUE';
$params = [];
if ($tenantFilter) {
    $sql .= ' AND tenant_id = :tid';
    $params['tid'] = $tenantFilter;
}
$sql .= ' ORDER BY id';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

$total = count($entries);
echo "Found $total entries without embeddings.\n";

if ($total === 0) {
    echo "Nothing to do.\n";
    exit(0);
}

$success = 0;
$failed = 0;

foreach ($entries as $i => $entry) {
    $num = $i + 1;
    $label = $entry['title'] ? substr($entry['title'], 0, 50) : 'ID#' . $entry['id'];

    echo "[$num/$total] Embedding: $label ... ";

    if (Embeddings::embedEntry($db, $entry, $apiKey)) {
        echo "OK\n";
        $success++;
    } else {
        echo "FAILED\n";
        $failed++;
    }

    // Rate limit: ~3000 RPM for text-embedding-3-small, but be polite
    if ($num % $batchSize === 0) {
        echo "  (pausing 1s after batch...)\n";
        sleep(1);
    } else {
        usleep(50000); // 50ms between requests
    }
}

echo "\nDone. Success: $success, Failed: $failed\n";
