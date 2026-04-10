<?php
/**
 * rescrape.php — Re-scrape one or all communities
 *
 * Clears existing KB entries for the tenant(s), then re-runs the universal scraper.
 *
 * Usage:
 *   php rescrape.php --all              Re-scrape every community tenant
 *   php rescrape.php hw_harvest         Re-scrape a single tenant
 *   php rescrape.php --list             List all tenants and their KB entry counts
 */
set_time_limit(0);

require_once __DIR__ . '/../api/bootstrap.php';

$arg = $argv[1] ?? '';

if (empty($arg) || $arg === '--help') {
    echo "Usage:\n";
    echo "  php rescrape.php --all              Re-scrape all community tenants\n";
    echo "  php rescrape.php --list             List tenants and KB counts\n";
    echo "  php rescrape.php <tenant_id>        Re-scrape a single tenant\n";
    exit(0);
}

$db = Database::db();

// ─── List mode ───
if ($arg === '--list') {
    $stmt = $db->query("
        SELECT t.id, t.display_name, t.community_url,
               COUNT(k.id) AS kb_entries,
               MAX(k.created_at) AS last_scraped
        FROM tenants t
        LEFT JOIN kb_entries k ON k.tenant_id = t.id
        WHERE t.community_type = 'community' OR t.community_type = 'realtor'
        GROUP BY t.id, t.display_name, t.community_url
        ORDER BY t.display_name
    ");
    $rows = $stmt->fetchAll();

    printf("%-20s %-30s %-40s %8s %s\n", 'TENANT ID', 'NAME', 'URL', 'KB', 'LAST SCRAPED');
    printf("%s\n", str_repeat('-', 130));

    foreach ($rows as $r) {
        printf("%-20s %-30s %-40s %8d %s\n",
            $r['id'],
            substr($r['display_name'], 0, 28),
            substr($r['community_url'] ?? '', 0, 38),
            (int)$r['kb_entries'],
            $r['last_scraped'] ? substr($r['last_scraped'], 0, 10) : 'never'
        );
    }
    exit(0);
}

// ─── Build tenant list ───
$tenants = [];

if ($arg === '--all') {
    $stmt = $db->query("
        SELECT id, display_name, community_url
        FROM tenants
        WHERE community_type = 'community' AND is_active = TRUE AND community_url IS NOT NULL AND community_url != ''
        ORDER BY display_name
    ");
    $tenants = $stmt->fetchAll();
    echo "Re-scraping ALL " . count($tenants) . " community tenants...\n\n";
} else {
    $stmt = $db->prepare("SELECT id, display_name, community_url FROM tenants WHERE id = :id");
    $stmt->execute(['id' => $arg]);
    $t = $stmt->fetch();
    if (!$t) die("Tenant '$arg' not found.\n");
    if (empty($t['community_url'])) die("Tenant '$arg' has no community_url set.\n");
    $tenants = [$t];
}

$scraper = __DIR__ . '/scrape-wp-universal.php';
if (!file_exists($scraper)) {
    die("Scraper not found: $scraper\n");
}

$totalSuccess = 0;
$totalFailed = 0;

foreach ($tenants as $i => $tenant) {
    $tid = $tenant['id'];
    $url = $tenant['community_url'];
    $name = $tenant['display_name'];

    echo "=== [" . ($i + 1) . "/" . count($tenants) . "] $name ($tid) ===\n";
    echo "    URL: $url\n";

    // Clear existing KB entries for this tenant
    $stmt = $db->prepare("SELECT COUNT(*) FROM kb_entries WHERE tenant_id = :tid");
    $stmt->execute(['tid' => $tid]);
    $existing = (int)$stmt->fetchColumn();

    if ($existing > 0) {
        echo "    Clearing $existing existing KB entries...\n";
        $db->prepare("DELETE FROM kb_entries WHERE tenant_id = :tid")->execute(['tid' => $tid]);
        $db->prepare("DELETE FROM kb_sources WHERE tenant_id = :tid")->execute(['tid' => $tid]);
    }

    // Run the scraper
    echo "    Scraping...\n";
    $cmd = "php " . escapeshellarg($scraper) . " " . escapeshellarg($tid) . " " . escapeshellarg($url) . " 100 2>&1";
    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);

    // Show last few lines of output
    $outputStr = implode("\n", array_slice($output, -5));
    echo "    " . str_replace("\n", "\n    ", $outputStr) . "\n";

    if ($exitCode === 0) {
        echo "    ✅ Done\n\n";
        $totalSuccess++;
    } else {
        echo "    ❌ Failed (exit code $exitCode)\n\n";
        $totalFailed++;
    }
}

echo "=== COMPLETE ===\n";
echo "Success: $totalSuccess / " . count($tenants) . "\n";
if ($totalFailed > 0) echo "Failed: $totalFailed\n";
