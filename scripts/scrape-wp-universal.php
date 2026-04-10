<?php
/**
 * Universal WP REST API + ACF scraper.
 * Recursively walks any ACF structure and extracts text content.
 * Works regardless of how the developer named their fields.
 *
 * Usage: php scrape-wp-universal.php <tenant_id> <site_url> [max_pages]
 */
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../api/bootstrap.php';
require_once __DIR__ . '/../lib/Embeddings.php';

$tenantId = $argv[1] ?? '';
$siteUrl  = rtrim($argv[2] ?? '', '/');
$maxPages = (int)($argv[3] ?? 100);

if (empty($tenantId) || empty($siteUrl)) {
    die("Usage: php scrape-wp-universal.php <tenant_id> <site_url> [max_pages]\n");
}

$db = Database::db();
$tenant = Database::getTenant($tenantId);
if (!$tenant) die("Tenant '$tenantId' not found.\n");

$config = require __DIR__ . '/../config.php';
$apiKey = Embeddings::getApiKey($tenant, $config);

echo "Scraping $siteUrl via WP REST API for tenant '$tenantId'...\n\n";

$totalEntries = 0;
$totalEmbedded = 0;
$apiBase = $siteUrl . '/wp-json/wp/v2';

// ─── Text extraction keys ───
// Field names that typically contain readable content.
// We match partial names so 'txt_richtext', 'text_body', 'richtext_content' all match.
$textKeys = [
    'text', 'body', 'richtext', 'content', 'description', 'excerpt',
    'summary', 'paragraph', 'caption', 'blurb', 'copy', 'message',
    'bio', 'details', 'info', 'answer', 'question', 'note',
];

// Field names that contain headings/titles
$headingKeys = [
    'headline', 'heading', 'title', 'label', 'name', 'subheadline',
    'subheading', 'subtitle',
];

// Keys to skip (images, settings, IDs, CSS, etc.)
$skipKeys = [
    'image', 'photo', 'video', 'url', 'link', 'src', 'href',
    'color', 'font', 'size', 'width', 'height', 'style', 'class',
    'layout', 'option', 'enabled', 'show', 'hide', 'type', 'tag',
    'target', 'icon', 'logo', 'shadow', 'parallax', 'animation',
    'poster', 'mp4', 'embed', 'vimeo', 'youtube', 'bg_', 'fx',
    'container_id', 'container_styles', 'acf_fc_layout',
    'button_url', 'button_newtab', 'cta_url', 'cta_link',
    'id', 'slug', 'template', 'menu_order', 'status', 'ping_status',
    'comment_status', 'featured_media', 'author', 'parent', 'modified',
    'date', 'guid', 'meta', '_links', 'class_list',
];

/**
 * Recursively extract readable text from any data structure.
 */
function extractText($data, int $depth = 0): array
{
    global $textKeys, $headingKeys, $skipKeys;

    $texts = [];
    if ($depth > 10) return $texts; // prevent infinite recursion

    if (is_string($data)) {
        $cleaned = strip_tags(html_entity_decode($data, ENT_QUOTES, 'UTF-8'));
        $cleaned = preg_replace('/\s+/', ' ', trim($cleaned));
        // Only keep strings that look like real content (not CSS, JSON, URLs, etc.)
        if (strlen($cleaned) > 30 && !preg_match('/^(https?:|\/\/|{|\[|#|--|var\(|\.wp-|:root)/', $cleaned)) {
            $texts[] = $cleaned;
        }
        return $texts;
    }

    if (!is_array($data)) return $texts;

    foreach ($data as $key => $value) {
        $keyLower = strtolower((string)$key);

        // Skip known non-content keys
        $skip = false;
        foreach ($skipKeys as $sk) {
            if (str_contains($keyLower, $sk)) {
                $skip = true;
                break;
            }
        }

        // But don't skip if the key also matches a text/heading key
        foreach ($textKeys as $tk) {
            if (str_contains($keyLower, $tk)) {
                $skip = false;
                break;
            }
        }
        foreach ($headingKeys as $hk) {
            if (str_contains($keyLower, $hk)) {
                $skip = false;
                break;
            }
        }

        if ($skip && is_string($value)) continue;

        // Recurse into arrays/objects
        if (is_array($value)) {
            $texts = array_merge($texts, extractText($value, $depth + 1));
        } elseif (is_string($value)) {
            $cleaned = strip_tags(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
            $cleaned = preg_replace('/\s+/', ' ', trim($cleaned));

            // Check if this key is a heading or text field
            $isContent = false;
            foreach (array_merge($textKeys, $headingKeys) as $ck) {
                if (str_contains($keyLower, $ck)) {
                    $isContent = true;
                    break;
                }
            }

            // Accept content fields with 20+ chars, or any field with 80+ chars
            if ($isContent && strlen($cleaned) > 20) {
                $texts[] = $cleaned;
            } elseif (strlen($cleaned) > 80 && !preg_match('/^(https?:|\/\/|{|\[|#|--|var\(|\.wp-|:root)/', $cleaned)) {
                $texts[] = $cleaned;
            }
        }
    }

    return $texts;
}

/**
 * Process a WP REST API item (page or post).
 */
function processItem(PDO $db, string $tenantId, string $apiKey, array $item): array
{
    $title = html_entity_decode($item['title']['rendered'] ?? 'Untitled', ENT_QUOTES, 'UTF-8');
    $link = $item['link'] ?? '';

    // Extract text from ACF fields
    $texts = [];
    if (!empty($item['acf'])) {
        $texts = extractText($item['acf']);
    }

    // Also check standard WP content
    if (!empty($item['content']['rendered'])) {
        $wpContent = strip_tags(html_entity_decode($item['content']['rendered'], ENT_QUOTES, 'UTF-8'));
        $wpContent = preg_replace('/\s+/', ' ', trim($wpContent));
        if (strlen($wpContent) > 50) {
            $texts[] = $wpContent;
        }
    }

    // Deduplicate (some text may appear in both heading and body)
    $texts = array_unique($texts);
    $fullText = implode("\n\n", $texts);
    $fullText = preg_replace('/\s+/', ' ', trim($fullText));

    if (strlen($fullText) < 50) {
        return ['chunks' => 0, 'embedded' => 0];
    }

    // Chunk into ~1000 char pieces
    $chunks = [];
    $words = explode(' ', $fullText);
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
    $embedded = 0;
    foreach ($chunks as $i => $content) {
        $chunkTitle = $title . ($i > 0 ? " (part " . ($i + 1) . ")" : "");

        $stmt = $db->prepare("
            INSERT INTO kb_entries (tenant_id, source_type, source_ref, title, content, is_active)
            VALUES (:tid, 'webpage', :url, :title, :content, TRUE)
            RETURNING id
        ");
        $stmt->execute([
            'tid' => $tenantId,
            'url' => $link,
            'title' => $chunkTitle,
            'content' => $content,
        ]);
        $entry = $stmt->fetch();

        if ($apiKey && $entry) {
            $ok = Embeddings::embedEntry($db, [
                'id' => $entry['id'],
                'title' => $chunkTitle,
                'content' => $content,
            ], $apiKey);
            if ($ok) $embedded++;
        }
    }

    // Save source record
    $stmt = $db->prepare("
        INSERT INTO kb_sources (tenant_id, source_type, name, url, status, chunks_created, last_processed)
        VALUES (:tid, 'webpage', :name, :url, 'completed', :chunks, NOW())
    ");
    $stmt->execute([
        'tid' => $tenantId,
        'name' => $title,
        'url' => $link,
        'chunks' => count($chunks),
    ]);

    return ['chunks' => count($chunks), 'embedded' => $embedded];
}

/**
 * Fetch items from a WP REST API endpoint.
 */
function fetchFromApi(string $url): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (compatible; HWChatBot/1.0)',
            'Accept: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return null;

    $data = json_decode($response, true);
    return (is_array($data) && !empty($data)) ? $data : null;
}

// ─── Scrape Pages ───
echo "=== PAGES ===\n";
$page = 1;
$fetched = 0;

while ($fetched < $maxPages) {
    $items = fetchFromApi("$apiBase/pages?per_page=20&page=$page&_fields=id,title,slug,link,content,acf");
    if (!$items) break;

    foreach ($items as $item) {
        $fetched++;
        $title = html_entity_decode($item['title']['rendered'] ?? 'Untitled', ENT_QUOTES, 'UTF-8');
        echo "  [$fetched] $title ... ";

        $result = processItem($db, $tenantId, $apiKey, $item);

        if ($result['chunks'] === 0) {
            echo "SKIPPED\n";
        } else {
            $totalEntries += $result['chunks'];
            $totalEmbedded += $result['embedded'];
            echo "OK ({$result['chunks']} chunks, {$result['embedded']} embedded)\n";
        }
    }

    if (count($items) < 20) break;
    $page++;
}

// ─── Scrape Posts ───
echo "\n=== BLOG POSTS ===\n";
$page = 1;
$fetched = 0;
$maxPosts = 50;

while ($fetched < $maxPosts) {
    $items = fetchFromApi("$apiBase/posts?per_page=20&page=$page&_fields=id,title,slug,link,content,acf");
    if (!$items) break;

    foreach ($items as $item) {
        $fetched++;
        $title = html_entity_decode($item['title']['rendered'] ?? 'Untitled', ENT_QUOTES, 'UTF-8');
        echo "  [$fetched] $title ... ";

        $result = processItem($db, $tenantId, $apiKey, $item);

        if ($result['chunks'] === 0) {
            echo "SKIPPED\n";
        } else {
            $totalEntries += $result['chunks'];
            $totalEmbedded += $result['embedded'];
            echo "OK ({$result['chunks']} chunks, {$result['embedded']} embedded)\n";
        }
    }

    if (count($items) < 20) break;
    $page++;
}

echo "\n=== DONE ===\n";
echo "Total KB entries: $totalEntries\n";
echo "Total embedded: $totalEmbedded\n";
