<?php
set_time_limit(0);
ini_set('memory_limit', '512M');
require_once __DIR__ . '/../api/bootstrap.php';
require_once __DIR__ . '/../lib/Embeddings.php';

$tenantId = $argv[1] ?? '';
if (empty($tenantId) || empty($argv[2])) {
    die("Usage: php scrape-urls.php <tenant_id> <url_file_or_urls...>\n");
}

$db = Database::db();
$tenant = Database::getTenant($tenantId);
if (!$tenant) die("Tenant '$tenantId' not found.\n");

$config = require __DIR__ . '/../config.php';
$apiKey = Embeddings::getApiKey($tenant, $config);

$urls = [];
if (file_exists($argv[2])) {
    $lines = file($argv[2], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line && !str_starts_with($line, '#')) $urls[] = $line;
    }
    echo "Loaded " . count($urls) . " URLs from {$argv[2]}\n";
} else {
    for ($i = 2; $i < $argc; $i++) $urls[] = $argv[$i];
}

if (empty($urls)) die("No URLs to scrape.\n");
echo "Scraping " . count($urls) . " URLs for tenant '$tenantId'...\n\n";

$totalChunks = 0; $totalEmbedded = 0; $success = 0; $failed = 0;

foreach ($urls as $idx => $url) {
    $url = trim($url);
    echo "[" . ($idx+1) . "/" . count($urls) . "] $url ... ";

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>20,
        CURLOPT_HTTPHEADER=>["User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"]]);
    $html = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);

    if (!$html || $httpCode >= 400) { echo "FAILED (HTTP $httpCode)\n"; $failed++; continue; }

    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
        $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        $title = preg_replace('/\s*[\|–—-]\s*.*$/', '', $title);
    }

    $clean = preg_replace('/<(script|style|nav|footer|header|noscript|iframe)[^>]*>.*?<\/\1>/si', '', $html);
    if (preg_match('/<(main|article)[^>]*>(.*?)<\/\1>/si', $clean, $m)) $clean = $m[2];

    $text = html_entity_decode(strip_tags($clean), ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    $text = trim($text);

    if (strlen($text) < 100) { echo "SKIPPED (" . strlen($text) . " chars)\n"; $failed++; continue; }

    $chunks = []; $words = explode(' ', $text); $chunk = '';
    foreach ($words as $word) {
        if (strlen($chunk) + strlen($word) > 1000) { $chunks[] = trim($chunk); $chunk = ''; }
        $chunk .= $word . ' ';
    }
    if (trim($chunk)) $chunks[] = trim($chunk);

    $embedded = 0;
    foreach ($chunks as $i => $content) {
        $chunkTitle = $title . ($i > 0 ? " (part " . ($i+1) . ")" : '');
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        $stmt = $db->prepare("INSERT INTO kb_entries (tenant_id, source_type, source_ref, title, content, is_active) VALUES (:tid, 'webpage', :url, :title, :content, TRUE) RETURNING id");
        $stmt->execute(['tid'=>$tenantId, 'url'=>$url, 'title'=>$chunkTitle, 'content'=>$content]);
        $entry = $stmt->fetch();
        if ($apiKey && $entry) {
            $r = Embeddings::embedEntry($db, ['id'=>$entry['id'], 'title'=>$chunkTitle, 'content'=>$content], $apiKey);
            if ($r) $embedded++;
        }
    }
    $totalChunks += count($chunks); $totalEmbedded += $embedded; $success++;
    echo "OK — $title (" . count($chunks) . " chunks, $embedded embedded)\n";
}
echo "\n=== DONE ===\nSuccess: $success, Failed: $failed\nChunks: $totalChunks, Embedded: $totalEmbedded\n";
