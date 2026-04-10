<?php
/**
 * CLI scraper — bypasses browser/Cloudflare timeouts.
 * Usage: php scrape-site.php <tenant_id> <url> [max_pages]
 */
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../api/bootstrap.php';
require_once __DIR__ . '/../lib/Embeddings.php';

$tenantId = $argv[1] ?? '';
$startUrl = $argv[2] ?? '';
$maxPages = (int)($argv[3] ?? 20);

if (empty($tenantId) || empty($startUrl)) {
    die("Usage: php scrape-site.php <tenant_id> <url> [max_pages]\n");
}

$db = Database::db();
$tenant = Database::getTenant($tenantId);
if (!$tenant) die("Tenant '$tenantId' not found.\n");

$config = require __DIR__ . '/../config.php';
$apiKey = Embeddings::getApiKey($tenant, $config);

echo "Scraping $startUrl for tenant '$tenantId' (max $maxPages pages)...\n\n";

$visited = [];
$queue = [$startUrl];
$pagesScraped = 0;
$totalChunks = 0;
$host = parse_url($startUrl, PHP_URL_HOST);

$ctx = stream_context_create([
    'http' => [
        'timeout' => 15,
        'header' => "User-Agent: Mozilla/5.0 (compatible; HWChatBot/1.0)\r\n",
    ],
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
]);

while (!empty($queue) && $pagesScraped < $maxPages) {
    $url = array_shift($queue);
    $normalized = rtrim(preg_replace('/[#?].*$/', '', $url), '/');

    if (isset($visited[$normalized])) continue;
    $visited[$normalized] = true;

    echo "[" . ($pagesScraped + 1) . "/$maxPages] $url ... ";

    $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => ["User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"]]); $html = curl_exec($ch); curl_close($ch);
    if (!$html) { echo "FAILED (no response)\n"; continue; }

    // Extract title
    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
        $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        $title = preg_replace('/\s*[\|–—-]\s*.*$/', '', $title);
    }

    // Strip scripts, styles, nav, footer, header
    $clean = preg_replace('/<(script|style|nav|footer|header|noscript)[^>]*>.*?<\/\1>/si', '', $html);

    // Get main content area if possible
    if (preg_match('/<(main|article)[^>]*>(.*?)<\/\1>/si', $clean, $m)) {
        $clean = $m[2];
    }

    // Strip tags, decode entities
    $text = html_entity_decode(strip_tags($clean), ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    if (strlen($text) < 50) { echo "SKIPPED (too short)\n"; continue; }

    // Chunk the content (~1000 chars each)
    $chunks = [];
    $words = explode(' ', $text);
    $chunk = '';
    foreach ($words as $word) {
        if (strlen($chunk) + strlen($word) > 1000) {
            $chunks[] = trim($chunk);
            $chunk = '';
        }
        $chunk .= $word . ' ';
    }
    if (trim($chunk)) $chunks[] = trim($chunk);

    // Save chunks
    $saved = 0;
    foreach ($chunks as $i => $content) {
        $chunkTitle = $title . ($i > 0 ? " (part " . ($i + 1) . ")" : "");

        $stmt = $db->prepare("
            INSERT INTO kb_entries (tenant_id, source_type, source_ref, title, content, is_active)
            VALUES (:tid, 'webpage', :url, :title, :content, TRUE)
            RETURNING id
        ");
        $stmt->execute([
            'tid' => $tenantId,
            'url' => $url,
            'title' => $chunkTitle,
            'content' => $content,
        ]);
        $entry = $stmt->fetch();

        // Generate embedding
        if ($apiKey && $entry) {
            $embResult = Embeddings::embedEntry($db, [
                'id' => $entry['id'],
                'title' => $chunkTitle,
                'content' => $content,
            ], $apiKey);
            if ($embResult) $saved++;
        }
    }

    // Save source record
    $stmt = $db->prepare("
        INSERT INTO kb_sources (tenant_id, source_type, name, url, status, chunks_created, last_processed)
        VALUES (:tid, 'webpage', :name, :url, 'completed', :chunks, NOW())
    ");
    $stmt->execute([
        'tid' => $tenantId,
        'name' => $title ?: $url,
        'url' => $url,
        'chunks' => count($chunks),
    ]);

    $pagesScraped++;
    $totalChunks += count($chunks);
    echo "OK — $title (" . count($chunks) . " chunks, $saved embedded)\n";

    // Find internal links
    preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $links);
    foreach ($links[1] as $link) {
        $link = trim($link);
        if (str_starts_with($link, '/')) {
            $link = parse_url($startUrl, PHP_URL_SCHEME) . '://' . $host . $link;
        }
        if (!str_starts_with($link, 'http')) continue;
        if (parse_url($link, PHP_URL_HOST) !== $host) continue;
        if (preg_match('/\.(jpg|jpeg|png|gif|svg|pdf|css|js|ico|woff|webp|mp4|mp3)$/i', $link)) continue;
        if (str_contains($link, '/wp-admin') || str_contains($link, '/wp-login') || str_contains($link, '/feed')) continue;

        $normLink = rtrim(preg_replace('/[#?].*$/', '', $link), '/');
        if (!isset($visited[$normLink])) {
            $queue[] = $link;
        }
    }
}

echo "\n=== DONE ===\n";
echo "Pages scraped: $pagesScraped\n";
echo "Total chunks: $totalChunks\n";
