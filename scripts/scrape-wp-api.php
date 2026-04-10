<?php
/**
 * CLI scraper — pulls content from WordPress REST API + ACF fields.
 * Usage: php scrape-wp-api.php <tenant_id> <site_url> [max_pages]
 */
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../api/bootstrap.php';
require_once __DIR__ . '/../lib/Embeddings.php';

$tenantId = $argv[1] ?? '';
$siteUrl  = rtrim($argv[2] ?? '', '/');
$maxPages = (int)($argv[3] ?? 100);

if (empty($tenantId) || empty($siteUrl)) {
    die("Usage: php scrape-wp-api.php <tenant_id> <site_url> [max_pages]\n");
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

// Fetch pages
$page = 1;
$fetched = 0;

while ($fetched < $maxPages) {
    $url = "$apiBase/pages?per_page=20&page=$page&_fields=id,title,slug,link,content,acf";
    echo "Fetching page $page of results...\n";

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

    if ($httpCode !== 200) {
        echo "API returned HTTP $httpCode — stopping.\n";
        break;
    }

    $pages = json_decode($response, true);
    if (empty($pages) || !is_array($pages)) {
        echo "No more pages.\n";
        break;
    }

    foreach ($pages as $wpPage) {
        $fetched++;
        $title = html_entity_decode($wpPage['title']['rendered'] ?? 'Untitled', ENT_QUOTES, 'UTF-8');
        $slug = $wpPage['slug'] ?? '';
        $link = $wpPage['link'] ?? '';

        echo "  [$fetched] $title ... ";

        // Extract text from ACF flexible content blocks
        $texts = [];
        $acf = $wpPage['acf'] ?? [];
        $flexBlocks = $acf['flex_content_block'] ?? $acf['flex_content'] ?? [];

        if (is_array($flexBlocks)) {
            foreach ($flexBlocks as $block) {
                // Headlines
                if (!empty($block['text_headline']['headline_text'])) {
                    $texts[] = strip_tags(html_entity_decode($block['text_headline']['headline_text'], ENT_QUOTES, 'UTF-8'));
                }
                // Subheadlines
                if (!empty($block['text_subheadline'])) {
                    $texts[] = strip_tags(html_entity_decode($block['text_subheadline'], ENT_QUOTES, 'UTF-8'));
                }
                // Body text
                if (!empty($block['text_body'])) {
                    $texts[] = strip_tags(html_entity_decode($block['text_body'], ENT_QUOTES, 'UTF-8'));
                }
                // Read more text
                if (!empty($block['text_body_readmore'])) {
                    $texts[] = strip_tags(html_entity_decode($block['text_body_readmore'], ENT_QUOTES, 'UTF-8'));
                }
                // Accordion/FAQ items
                if (!empty($block['accordion_items']) && is_array($block['accordion_items'])) {
                    foreach ($block['accordion_items'] as $item) {
                        if (!empty($item['title'])) $texts[] = strip_tags(html_entity_decode($item['title'], ENT_QUOTES, 'UTF-8'));
                        if (!empty($item['content'])) $texts[] = strip_tags(html_entity_decode($item['content'], ENT_QUOTES, 'UTF-8'));
                        if (!empty($item['text'])) $texts[] = strip_tags(html_entity_decode($item['text'], ENT_QUOTES, 'UTF-8'));
                    }
                }
                // Tabs
                if (!empty($block['tabs']) && is_array($block['tabs'])) {
                    foreach ($block['tabs'] as $tab) {
                        if (!empty($tab['title'])) $texts[] = strip_tags(html_entity_decode($tab['title'], ENT_QUOTES, 'UTF-8'));
                        if (!empty($tab['content'])) $texts[] = strip_tags(html_entity_decode($tab['content'], ENT_QUOTES, 'UTF-8'));
                    }
                }
                // Cards/features
                if (!empty($block['cards']) && is_array($block['cards'])) {
                    foreach ($block['cards'] as $card) {
                        if (!empty($card['title'])) $texts[] = strip_tags(html_entity_decode($card['title'], ENT_QUOTES, 'UTF-8'));
                        if (!empty($card['text'])) $texts[] = strip_tags(html_entity_decode($card['text'], ENT_QUOTES, 'UTF-8'));
                        if (!empty($card['content'])) $texts[] = strip_tags(html_entity_decode($card['content'], ENT_QUOTES, 'UTF-8'));
                    }
                }
                // Generic content field
                if (!empty($block['content'])) {
                    $texts[] = strip_tags(html_entity_decode($block['content'], ENT_QUOTES, 'UTF-8'));
                }
            }
        }

        // Also check standard WP content field
        if (!empty($wpPage['content']['rendered'])) {
            $wpContent = strip_tags(html_entity_decode($wpPage['content']['rendered'], ENT_QUOTES, 'UTF-8'));
            $wpContent = preg_replace('/\s+/', ' ', trim($wpContent));
            if (strlen($wpContent) > 50) {
                $texts[] = $wpContent;
            }
        }

        // Combine and clean
        $fullText = implode("\n\n", array_filter($texts));
        $fullText = preg_replace('/\s+/', ' ', trim($fullText));

        if (strlen($fullText) < 50) {
            echo "SKIPPED (no content)\n";
            continue;
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

        $totalEntries += count($chunks);
        $totalEmbedded += $embedded;
        echo "OK (" . count($chunks) . " chunks, $embedded embedded)\n";
    }

    if (count($pages) < 20) break;
    $page++;
}

// Also fetch posts (blog)
echo "\nFetching blog posts...\n";
$page = 1;
$postsFetched = 0;
$maxPosts = 30;

while ($postsFetched < $maxPosts) {
    $url = "$apiBase/posts?per_page=20&page=$page&_fields=id,title,slug,link,content,acf";

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

    if ($httpCode !== 200) break;

    $posts = json_decode($response, true);
    if (empty($posts) || !is_array($posts)) break;

    foreach ($posts as $post) {
        $postsFetched++;
        $title = html_entity_decode($post['title']['rendered'] ?? 'Untitled', ENT_QUOTES, 'UTF-8');
        $link = $post['link'] ?? '';

        echo "  [$postsFetched] $title ... ";

        $texts = [];

        // ACF content
        $acf = $post['acf'] ?? [];
        $flexBlocks = $acf['flex_content_block'] ?? $acf['flex_content'] ?? [];
        if (is_array($flexBlocks)) {
            foreach ($flexBlocks as $block) {
                if (!empty($block['text_headline']['headline_text'])) $texts[] = strip_tags(html_entity_decode($block['text_headline']['headline_text'], ENT_QUOTES, 'UTF-8'));
                if (!empty($block['text_body'])) $texts[] = strip_tags(html_entity_decode($block['text_body'], ENT_QUOTES, 'UTF-8'));
                if (!empty($block['text_body_readmore'])) $texts[] = strip_tags(html_entity_decode($block['text_body_readmore'], ENT_QUOTES, 'UTF-8'));
                if (!empty($block['content'])) $texts[] = strip_tags(html_entity_decode($block['content'], ENT_QUOTES, 'UTF-8'));
            }
        }

        // Standard WP content
        if (!empty($post['content']['rendered'])) {
            $wpContent = strip_tags(html_entity_decode($post['content']['rendered'], ENT_QUOTES, 'UTF-8'));
            $wpContent = preg_replace('/\s+/', ' ', trim($wpContent));
            if (strlen($wpContent) > 50) $texts[] = $wpContent;
        }

        $fullText = implode("\n\n", array_filter($texts));
        $fullText = preg_replace('/\s+/', ' ', trim($fullText));

        if (strlen($fullText) < 50) {
            echo "SKIPPED\n";
            continue;
        }

        // Single chunk for blog posts (keep it concise)
        $content = mb_substr($fullText, 0, 2000);

        $stmt = $db->prepare("
            INSERT INTO kb_entries (tenant_id, source_type, source_ref, title, content, is_active)
            VALUES (:tid, 'webpage', :url, :title, :content, TRUE)
            RETURNING id
        ");
        $stmt->execute([
            'tid' => $tenantId,
            'url' => $link,
            'title' => $title,
            'content' => $content,
        ]);
        $entry = $stmt->fetch();

        if ($apiKey && $entry) {
            $ok = Embeddings::embedEntry($db, [
                'id' => $entry['id'],
                'title' => $title,
                'content' => $content,
            ], $apiKey);
            if ($ok) $totalEmbedded++;
        }

        $totalEntries++;

        $stmt = $db->prepare("
            INSERT INTO kb_sources (tenant_id, source_type, name, url, status, chunks_created, last_processed)
            VALUES (:tid, 'webpage', :name, :url, 'completed', 1, NOW())
        ");
        $stmt->execute([
            'tid' => $tenantId,
            'name' => $title,
            'url' => $link,
        ]);

        echo "OK\n";
    }

    if (count($posts) < 20) break;
    $page++;
}

echo "\n=== DONE ===\n";
echo "Total KB entries: $totalEntries\n";
echo "Total embedded: $totalEmbedded\n";
