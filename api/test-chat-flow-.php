<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 120);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/CecilianXO.php';

echo "<pre>";

// Step 1: Test XO search with balanced sort
echo "=== Step 1: XO Search ===\n";
$start = microtime(true);
try {
    $xo = new CecilianXO('https://hillwood.thexo.io/o/api/v2/map/consumer', 'treeline');
    $result = $xo->search(['type' => 'home', 'sort_by' => 'balanced', 'fetch_all' => true], 5);
    echo "OK - " . round(microtime(true) - $start, 2) . "s\n";
    echo "Summary: " . $result['summary'] . "\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    die();
}

// Step 2: JSON encode the result (what gets sent to LLM)
echo "\n=== Step 2: JSON Encode ===\n";
$json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo "JSON size: " . strlen($json) . " bytes\n";
if (!$json) {
    echo "JSON ENCODE FAILED: " . json_last_error_msg() . "\n";
    die();
}

// Step 3: Check tool definition
echo "\n=== Step 3: Tool Definition ===\n";
require_once __DIR__ . '/../api/chat.php';
echo "ERROR - if you see this, chat.php loaded without crashing\n";

echo "</pre>";