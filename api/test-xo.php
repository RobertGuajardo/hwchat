<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/CecilianXO.php';

echo "<pre>";
try {
    $xo = new CecilianXO('https://hillwood.thexo.io/o/api/v2/map/consumer', 'treeline');
    $result = $xo->search(['type' => 'home', 'sort_by' => 'balanced', 'fetch_all' => true], 5);
    echo "Summary: " . $result['summary'] . "\n";
    echo "MIR: " . $result['move_in_ready_count'] . "\n";
    echo "UC: " . $result['under_construction_count'] . "\n";
    echo "Properties returned: " . count($result['properties']) . "\n";
    foreach ($result['properties'] as $p) {
        echo $p['address'] . " — " . $p['listing_type'] . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
echo "</pre>";