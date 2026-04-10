<?php
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../api/bootstrap.php';
require_once __DIR__ . '/../lib/Embeddings.php';

$tenantId = 'hw_harvest';
$url = 'https://www.harvestbyhillwood.com';

// Load the scrape functions from knowledge-base.php
$kbCode = file_get_contents(__DIR__ . '/../dashboard/knowledge-base.php');

// Extract just the functions we need
require_once __DIR__ . '/../dashboard/auth.php';

$db = Database::db();

echo "Starting scrape of $url for tenant $tenantId...\n";

// Use the crawl function from the KB page
include_once __DIR__ . '/../dashboard/knowledge-base.php';
