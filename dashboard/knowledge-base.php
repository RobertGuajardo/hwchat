<?php
set_time_limit(300);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../lib/Embeddings.php';
requireAuth();

$db = Database::db();
$tenantId = getTenantId();
$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add FAQ entry
    if ($action === 'add_faq') {
        $title   = trim($_POST['faq_question'] ?? '');
        $content = trim($_POST['faq_answer'] ?? '');
        $category = trim($_POST['faq_category'] ?? '');
        if ($title && $content) {
            $keywords = extractKeywords($title . ' ' . $content);
            $stmt = $db->prepare('
                INSERT INTO kb_entries (tenant_id, source_type, title, content, keywords, category)
                VALUES (:tid, :type, :title, :content, :keywords, :category)
                RETURNING id
            ');
            $stmt->execute([
                'tid'      => $tenantId,
                'type'     => 'faq',
                'title'    => $title,
                'content'  => $content,
                'keywords' => json_encode(array_values($keywords)),
                'category' => $category ?: null,
            ]);
            $row = $stmt->fetch();
            $entryId = (int)$row['id'];
            embedNewEntry($db, $tenantId, $entryId, $title, $content);
            $success = 'FAQ entry added.';
        } else {
            $error = 'Question and answer are required.';
        }
    }

    // Add manual entry
    if ($action === 'add_manual') {
        $title   = trim($_POST['manual_title'] ?? '');
        $content = trim($_POST['manual_content'] ?? '');
        $category = trim($_POST['manual_category'] ?? '');
        if ($content) {
            $keywords = extractKeywords(($title ? $title . ' ' : '') . $content);
            $stmt = $db->prepare('
                INSERT INTO kb_entries (tenant_id, source_type, title, content, keywords, category)
                VALUES (:tid, :type, :title, :content, :keywords, :category)
                RETURNING id
            ');
            $stmt->execute([
                'tid'      => $tenantId,
                'type'     => 'manual',
                'title'    => $title ?: null,
                'content'  => $content,
                'keywords' => json_encode(array_values($keywords)),
                'category' => $category ?: null,
            ]);
            $row = $stmt->fetch();
            $entryId = (int)$row['id'];
            embedNewEntry($db, $tenantId, $entryId, $title, $content);
            $success = 'Knowledge entry added.';
        } else {
            $error = 'Content is required.';
        }
    }

    // Scrape URL
    if ($action === 'scrape_url') {
        $url = trim($_POST['scrape_url'] ?? '');
        if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
            $result = scrapeAndSave($db, $tenantId, $url);
            if ($result['success']) {
                $success = "Scraped {$result['chunks']} section(s) from " . parse_url($url, PHP_URL_HOST);
            } else {
                $error = $result['error'];
            }
        } else {
            $error = 'Please enter a valid URL.';
        }
    }

    // Crawl entire site
    if ($action === 'crawl_site') {
        $url = trim($_POST['crawl_url'] ?? '');
        $maxPages = min((int)($_POST['max_pages'] ?? 20), 50);
        if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
            $result = crawlAndSave($db, $tenantId, $url, $maxPages);
            if ($result['success']) {
                $success = "Crawled {$result['pages_scraped']} page(s), extracted {$result['total_chunks']} section(s) from " . parse_url($url, PHP_URL_HOST);
            } else {
                $error = $result['error'];
            }
        } else {
            $error = 'Please enter a valid URL.';
        }
    }

    // Delete entry
    if ($action === 'delete') {
        $entryId = (int)($_POST['entry_id'] ?? 0);
        if ($entryId) {
            $stmt = $db->prepare('DELETE FROM kb_entries WHERE id = :id AND tenant_id = :tid');
            $stmt->execute(['id' => $entryId, 'tid' => $tenantId]);
            $success = 'Entry deleted.';
        }
    }

    // Toggle entry active/inactive
    if ($action === 'toggle') {
        $entryId = (int)($_POST['entry_id'] ?? 0);
        if ($entryId) {
            $stmt = $db->prepare('UPDATE kb_entries SET is_active = NOT is_active WHERE id = :id AND tenant_id = :tid');
            $stmt->execute(['id' => $entryId, 'tid' => $tenantId]);
        }
        header('Location: knowledge-base.php?tab=' . urlencode($_GET['tab'] ?? 'faq'));
        exit;
    }
}

// Load entries by type
$tab = $_GET['tab'] ?? 'faq';

$stmt = $db->prepare('SELECT * FROM kb_entries WHERE tenant_id = :tid ORDER BY source_type, sort_order, created_at DESC');
$stmt->execute(['tid' => $tenantId]);
$allEntries = $stmt->fetchAll();

$faqEntries = array_filter($allEntries, fn($e) => $e['source_type'] === 'faq');
$manualEntries = array_filter($allEntries, fn($e) => $e['source_type'] === 'manual');
$webEntries = array_filter($allEntries, fn($e) => $e['source_type'] === 'webpage');
$docEntries = array_filter($allEntries, fn($e) => $e['source_type'] === 'document');

// Load sources
$stmt = $db->prepare('SELECT * FROM kb_sources WHERE tenant_id = :tid ORDER BY created_at DESC');
$stmt->execute(['tid' => $tenantId]);
$sources = $stmt->fetchAll();

$totalEntries = count($allEntries);
$activeEntries = count(array_filter($allEntries, fn($e) => $e['is_active']));

renderHead('Knowledge Base');
?>
    <header class="topbar">
        <div class="topbar-left">
            <span class="topbar-stamp">RC</span>
            <h1><?php echo e(strtoupper(getTenantName())); ?></h1>
        </div>
        <div class="topbar-right">
            <span style="font-family:'Space Mono',monospace;font-size:11px;color:#555;"><?php echo e($_SESSION['tenant_email'] ?? ''); ?></span>
            <a href="logout.php" class="btn btn-ghost btn-sm">LOGOUT</a>
        </div>
    </header>
    <nav class="nav-tabs">
        <a href="index.php" class="nav-tab">OVERVIEW</a>
        <a href="leads.php" class="nav-tab">LEADS</a>
        <a href="bookings.php" class="nav-tab">BOOKINGS</a>
        <a href="knowledge-base.php" class="nav-tab active">KNOWLEDGE</a>
        <a href="settings.php" class="nav-tab">SETTINGS</a>
    </nav>

    <main class="container">
        <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid" style="margin-bottom:24px;">
            <div class="stat-card">
                <span class="stat-value"><?php echo $totalEntries; ?></span>
                <span class="stat-label">TOTAL ENTRIES</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo $activeEntries; ?></span>
                <span class="stat-label">ACTIVE</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo count($faqEntries); ?></span>
                <span class="stat-label">FAQ</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo count($webEntries); ?></span>
                <span class="stat-label">FROM WEBSITES</span>
            </div>
        </div>

        <!-- Tab navigation -->
        <div style="display:flex;gap:4px;margin-bottom:24px;">
            <a href="?tab=faq" class="pill <?php echo $tab === 'faq' ? 'active' : ''; ?>">FAQ</a>
            <a href="?tab=scrape" class="pill <?php echo $tab === 'scrape' ? 'active' : ''; ?>">WEBSITE SCRAPER</a>
            <a href="?tab=manual" class="pill <?php echo $tab === 'manual' ? 'active' : ''; ?>">MANUAL ENTRIES</a>
            <a href="?tab=all" class="pill <?php echo $tab === 'all' ? 'active' : ''; ?>">ALL ENTRIES</a>
        </div>

    <?php if ($tab === 'faq'): ?>
        <!-- Add FAQ form -->
        <div style="background:#141414;border:1px solid rgba(255,255,255,0.06);padding:24px;margin-bottom:24px;">
            <h3 style="font-size:14px;color:#fff;font-family:'Syne',sans-serif;margin-bottom:16px;">ADD FAQ ENTRY</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_faq">
                <div class="form-group">
                    <label class="form-label">Question</label>
                    <input type="text" name="faq_question" class="form-input" placeholder="What are your hours?" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Answer</label>
                    <textarea name="faq_answer" class="form-textarea" style="min-height:80px;" placeholder="We're open Monday through Friday, 9am to 5pm..." required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Category (optional)</label>
                    <input type="text" name="faq_category" class="form-input" placeholder="hours, pricing, services, etc." style="max-width:300px;">
                </div>
                <button type="submit" class="btn btn-primary" style="padding:10px 24px;">ADD FAQ</button>
            </form>
        </div>

        <!-- FAQ list -->
        <?php if (empty($faqEntries)): ?>
            <div class="empty-state">No FAQ entries yet. Add common questions and answers your chatbot should know.</div>
        <?php else: ?>
            <?php foreach ($faqEntries as $e): ?>
            <div style="background:<?php echo $e['is_active'] ? '#141414' : '#0c0c0c'; ?>;border:1px solid <?php echo $e['is_active'] ? 'rgba(255,255,255,0.06)' : 'rgba(255,255,255,0.03)'; ?>;padding:16px 20px;margin-bottom:8px;<?php echo !$e['is_active'] ? 'opacity:0.5;' : ''; ?>">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                    <div style="flex:1;">
                        <div style="font-size:14px;color:#A78BFA;margin-bottom:6px;font-weight:500;">Q: <?php echo e($e['title']); ?></div>
                        <div style="font-size:13px;color:#ccc;line-height:1.6;white-space:pre-wrap;"><?php echo e($e['content']); ?></div>
                        <?php if ($e['category']): ?>
                            <span style="display:inline-block;margin-top:8px;font-size:11px;font-family:'Space Mono',monospace;color:#666;background:rgba(255,255,255,0.04);padding:2px 8px;"><?php echo e($e['category']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;gap:4px;flex-shrink:0;">
                        <form method="POST" style="display:inline;"><input type="hidden" name="action" value="toggle"><input type="hidden" name="entry_id" value="<?php echo $e['id']; ?>"><button type="submit" class="btn btn-sm"><?php echo $e['is_active'] ? 'DISABLE' : 'ENABLE'; ?></button></form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this entry?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="entry_id" value="<?php echo $e['id']; ?>"><button type="submit" class="btn btn-sm btn-danger">DELETE</button></form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php elseif ($tab === 'scrape'): ?>
        <!-- Scrape single URL -->
        <div style="background:#141414;border:1px solid rgba(255,255,255,0.06);padding:24px;margin-bottom:16px;">
            <h3 style="font-size:14px;color:#fff;font-family:'Syne',sans-serif;margin-bottom:4px;">SCRAPE SINGLE PAGE</h3>
            <p class="form-hint" style="margin-bottom:16px;">Paste a URL and we'll extract the content for your chatbot to learn from.</p>
            <form method="POST" style="display:flex;gap:8px;align-items:flex-end;">
                <input type="hidden" name="action" value="scrape_url">
                <div style="flex:1;">
                    <label class="form-label">URL</label>
                    <input type="url" name="scrape_url" class="form-input" placeholder="https://yoursite.com/services" required>
                </div>
                <button type="submit" class="btn btn-primary" style="padding:10px 24px;height:42px;">SCRAPE</button>
            </form>
        </div>

        <!-- Crawl entire site -->
        <div style="background:#141414;border:1px solid rgba(255,255,255,0.06);padding:24px;margin-bottom:24px;">
            <h3 style="font-size:14px;color:#fff;font-family:'Syne',sans-serif;margin-bottom:4px;">CRAWL ENTIRE WEBSITE</h3>
            <p class="form-hint" style="margin-bottom:16px;">Enter the homepage URL and we'll automatically find and scrape all pages on the site.</p>
            <form method="POST">
                <input type="hidden" name="action" value="crawl_site">
                <div style="display:flex;gap:8px;align-items:flex-end;">
                    <div style="flex:1;">
                        <label class="form-label">Website URL</label>
                        <input type="url" name="crawl_url" class="form-input" placeholder="https://yoursite.com" required>
                    </div>
                    <div style="width:140px;">
                        <label class="form-label">Max Pages</label>
                        <select name="max_pages" class="form-select">
                            <option value="10">10 pages</option>
                            <option value="20" selected>20 pages</option>
                            <option value="30">30 pages</option>
                            <option value="50">50 pages</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding:10px 24px;height:42px;" onclick="this.textContent='CRAWLING...';this.disabled=true;this.form.submit();">CRAWL SITE</button>
                </div>
            </form>
        </div>

        <!-- Scraped pages list -->
        <?php if (empty($webEntries)): ?>
            <div class="empty-state">No pages scraped yet. Paste a URL above to import content from any website.</div>
        <?php else: ?>
            <?php foreach ($webEntries as $e): ?>
            <div style="background:<?php echo $e['is_active'] ? '#141414' : '#0c0c0c'; ?>;border:1px solid <?php echo $e['is_active'] ? 'rgba(255,255,255,0.06)' : 'rgba(255,255,255,0.03)'; ?>;padding:16px 20px;margin-bottom:8px;<?php echo !$e['is_active'] ? 'opacity:0.5;' : ''; ?>">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                    <div style="flex:1;">
                        <?php if ($e['title']): ?><div style="font-size:14px;color:#fff;margin-bottom:4px;font-weight:500;"><?php echo e($e['title']); ?></div><?php endif; ?>
                        <div style="font-size:12px;color:#FF4D2E;margin-bottom:6px;font-family:'Space Mono',monospace;"><?php echo e($e['source_ref'] ?? ''); ?></div>
                        <div style="font-size:13px;color:#999;line-height:1.5;max-height:80px;overflow:hidden;"><?php echo e(substr($e['content'], 0, 300)); ?><?php echo strlen($e['content']) > 300 ? '...' : ''; ?></div>
                    </div>
                    <div style="display:flex;gap:4px;flex-shrink:0;">
                        <form method="POST" style="display:inline;"><input type="hidden" name="action" value="toggle"><input type="hidden" name="entry_id" value="<?php echo $e['id']; ?>"><button type="submit" class="btn btn-sm"><?php echo $e['is_active'] ? 'DISABLE' : 'ENABLE'; ?></button></form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="entry_id" value="<?php echo $e['id']; ?>"><button type="submit" class="btn btn-sm btn-danger">DELETE</button></form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php elseif ($tab === 'manual'): ?>
        <!-- Add manual entry form -->
        <div style="background:#141414;border:1px solid rgba(255,255,255,0.06);padding:24px;margin-bottom:24px;">
            <h3 style="font-size:14px;color:#fff;font-family:'Syne',sans-serif;margin-bottom:4px;">ADD KNOWLEDGE ENTRY</h3>
            <p class="form-hint" style="margin-bottom:16px;">Add any info you want your chatbot to know — services, pricing, policies, team bios, anything.</p>
            <form method="POST">
                <input type="hidden" name="action" value="add_manual">
                <div class="form-group">
                    <label class="form-label">Title (optional)</label>
                    <input type="text" name="manual_title" class="form-input" placeholder="Our pricing, Meet the team, etc.">
                </div>
                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea name="manual_content" class="form-textarea" style="min-height:120px;" placeholder="Write anything your chatbot should know..." required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Category (optional)</label>
                    <input type="text" name="manual_category" class="form-input" placeholder="pricing, team, policies, etc." style="max-width:300px;">
                </div>
                <button type="submit" class="btn btn-primary" style="padding:10px 24px;">ADD ENTRY</button>
            </form>
        </div>

        <?php $manualAll = array_merge(array_values($manualEntries), array_values($docEntries)); ?>
        <?php if (empty($manualAll)): ?>
            <div class="empty-state">No manual entries yet.</div>
        <?php else: ?>
            <?php foreach ($manualAll as $e): ?>
            <div style="background:#141414;border:1px solid rgba(255,255,255,0.06);padding:16px 20px;margin-bottom:8px;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                    <div style="flex:1;">
                        <?php if ($e['title']): ?><div style="font-size:14px;color:#fff;margin-bottom:4px;font-weight:500;"><?php echo e($e['title']); ?></div><?php endif; ?>
                        <div style="font-size:13px;color:#ccc;line-height:1.6;white-space:pre-wrap;max-height:120px;overflow:hidden;"><?php echo e(substr($e['content'], 0, 500)); ?></div>
                        <?php if ($e['category']): ?>
                            <span style="display:inline-block;margin-top:8px;font-size:11px;font-family:'Space Mono',monospace;color:#666;background:rgba(255,255,255,0.04);padding:2px 8px;"><?php echo e($e['category']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;gap:4px;flex-shrink:0;">
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="entry_id" value="<?php echo $e['id']; ?>"><button type="submit" class="btn btn-sm btn-danger">DELETE</button></form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php else: ?>
        <!-- All entries -->
        <?php if (empty($allEntries)): ?>
            <div class="empty-state">No knowledge base entries. Add FAQ entries, scrape websites, or add manual content to train your chatbot.</div>
        <?php else: ?>
            <?php foreach ($allEntries as $e): ?>
            <div style="background:<?php echo $e['is_active'] ? '#141414' : '#0c0c0c'; ?>;border:1px solid rgba(255,255,255,0.06);padding:12px 16px;margin-bottom:4px;<?php echo !$e['is_active'] ? 'opacity:0.5;' : ''; ?>">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                            <span class="badge" style="background:rgba(139,92,246,0.15);color:#a78bfa;border:1px solid rgba(139,92,246,0.3);text-transform:uppercase;"><?php echo e($e['source_type']); ?></span>
                            <?php if ($e['title']): ?><span style="font-size:13px;color:#fff;"><?php echo e(substr($e['title'], 0, 60)); ?></span><?php endif; ?>
                        </div>
                        <div style="font-size:12px;color:#888;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo e(substr($e['content'], 0, 120)); ?></div>
                    </div>
                    <div style="display:flex;gap:4px;flex-shrink:0;">
                        <form method="POST" style="display:inline;"><input type="hidden" name="action" value="toggle"><input type="hidden" name="entry_id" value="<?php echo $e['id']; ?>"><button type="submit" class="btn btn-sm" style="font-size:10px;"><?php echo $e['is_active'] ? 'OFF' : 'ON'; ?></button></form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="entry_id" value="<?php echo $e['id']; ?>"><button type="submit" class="btn btn-sm btn-danger" style="font-size:10px;">DEL</button></form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
    </main>
<?php renderFooter(); ?>

<?php
// ===========================================================================
// HELPER FUNCTIONS
// ===========================================================================

/**
 * Generate and store an embedding for a newly created KB entry.
 * Fails silently — embedding can be backfilled later if needed.
 */
function embedNewEntry(PDO $db, string $tenantId, int $entryId, ?string $title, string $content): void
{
    try {
        $config = require __DIR__ . '/../config.php';
        $tenant = Database::getTenant($tenantId);
        $apiKey = Embeddings::getApiKey($tenant, $config);
        if (empty($apiKey)) return;

        Embeddings::embedEntry($db, [
            'id'      => $entryId,
            'title'   => $title,
            'content' => $content,
        ], $apiKey);
    } catch (Exception $e) {
        error_log("Embedding failed for entry $entryId: " . $e->getMessage());
    }
}

/**
 * Extract keywords from text for search matching.
 */
function extractKeywords(string $text): array
{
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

    // Remove common stop words
    $stopWords = ['the','a','an','is','are','was','were','be','been','being','have','has','had',
        'do','does','did','will','would','shall','should','may','might','must','can','could',
        'i','you','he','she','it','we','they','me','him','her','us','them','my','your','his',
        'its','our','their','this','that','these','those','am','if','or','and','but','not',
        'no','so','as','at','by','for','from','in','into','of','on','to','with','about','what',
        'which','who','whom','when','where','why','how','all','each','every','both','few',
        'more','most','other','some','such','than','too','very','just','also'];

    $words = array_diff($words, $stopWords);
    $words = array_filter($words, fn($w) => strlen($w) >= 3);
    $words = array_unique($words);

    return array_slice(array_values($words), 0, 30);
}

/**
 * Scrape a URL and save content as KB entries.
 */
function scrapeAndSave(PDO $db, string $tenantId, string $url): array
{
    // Fetch the page
    $ctx = stream_context_create([
        'http' => [
            'timeout'       => 15,
            'user_agent'    => 'RobChat-Scraper/1.0',
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    $html = @file_get_contents($url, false, $ctx);
    if ($html === false) {
        return ['success' => false, 'error' => 'Failed to fetch URL. Check that it\'s accessible.'];
    }

    // Extract title
    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $title = trim(html_entity_decode(strip_tags($m[1])));
    }

    // Remove script, style, nav, footer, header tags
    $html = preg_replace('/<(script|style|nav|footer|header|noscript|iframe)[^>]*>.*?<\/\1>/is', '', $html);

    // Extract text from body
    $bodyText = '';
    if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $m)) {
        $bodyText = $m[1];
    } else {
        $bodyText = $html;
    }

    // Strip remaining HTML tags but preserve some structure
    $bodyText = preg_replace('/<(h[1-6])[^>]*>/i', "\n\n### ", $bodyText);
    $bodyText = preg_replace('/<\/(h[1-6])>/i', "\n", $bodyText);
    $bodyText = preg_replace('/<(p|div|br|li)[^>]*>/i', "\n", $bodyText);
    $bodyText = strip_tags($bodyText);
    $bodyText = html_entity_decode($bodyText, ENT_QUOTES, 'UTF-8');

    // Clean up whitespace
    $bodyText = preg_replace('/[ \t]+/', ' ', $bodyText);
    $bodyText = preg_replace('/\n{3,}/', "\n\n", $bodyText);
    $bodyText = trim($bodyText);

    if (strlen($bodyText) < 50) {
        return ['success' => false, 'error' => 'Page has too little text content to extract.'];
    }

    // Save source record
    $stmt = $db->prepare('
        INSERT INTO kb_sources (tenant_id, source_type, name, url, status, last_processed)
        VALUES (:tid, :type, :name, :url, :status, NOW())
    ');
    $stmt->execute([
        'tid'    => $tenantId,
        'type'   => 'webpage',
        'name'   => $title ?: parse_url($url, PHP_URL_HOST),
        'url'    => $url,
        'status' => 'completed',
    ]);
    $sourceId = (int)$db->lastInsertId();

    // Split into chunks (~500 chars each, split on double newlines)
    $sections = preg_split('/\n{2,}/', $bodyText, -1, PREG_SPLIT_NO_EMPTY);
    $chunks = [];
    $current = '';

    foreach ($sections as $section) {
        $section = trim($section);
        if (strlen($section) < 20) continue;

        if (strlen($current) + strlen($section) > 500 && strlen($current) > 50) {
            $chunks[] = $current;
            $current = $section;
        } else {
            $current .= ($current ? "\n\n" : '') . $section;
        }
    }
    if (strlen($current) > 50) {
        $chunks[] = $current;
    }

    // Save chunks as KB entries
    $insertStmt = $db->prepare('
        INSERT INTO kb_entries (tenant_id, source_type, source_ref, title, content, keywords, category)
        VALUES (:tid, :type, :ref, :title, :content, :keywords, :category)
        RETURNING id
    ');

    $chunkCount = 0;
    foreach ($chunks as $i => $chunk) {
        $chunkTitle = $title ? $title . ($i > 0 ? " (part " . ($i + 1) . ")" : '') : null;
        $keywords = extractKeywords($chunk);

        $insertStmt->execute([
            'tid'      => $tenantId,
            'type'     => 'webpage',
            'ref'      => $url,
            'title'    => $chunkTitle,
            'content'  => $chunk,
            'keywords' => json_encode(array_values($keywords)),
            'category' => null,
        ]);
        $row = $insertStmt->fetch();
        $entryId = (int)$row['id'];
        embedNewEntry($db, $tenantId, $entryId, $chunkTitle, $chunk);
        $chunkCount++;
    }

    // Update source with chunk count
    $stmt = $db->prepare('UPDATE kb_sources SET chunks_created = :count WHERE id = :id');
    $stmt->execute(['count' => $chunkCount, 'id' => $sourceId]);

    return ['success' => true, 'chunks' => $chunkCount];
}

/**
 * Crawl a website — follow internal links and scrape each page.
 */
function crawlAndSave(PDO $db, string $tenantId, string $startUrl, int $maxPages = 20): array
{
    $parsed = parse_url($startUrl);
    $baseHost = $parsed['host'] ?? '';
    $baseScheme = $parsed['scheme'] ?? 'https';
    $baseUrl = $baseScheme . '://' . $baseHost;

    if (!$baseHost) {
        return ['success' => false, 'error' => 'Invalid URL.'];
    }

    $visited = [];
    $queue = [$startUrl];
    $totalChunks = 0;
    $pagesScraped = 0;

    while (!empty($queue) && $pagesScraped < $maxPages) {
        $url = array_shift($queue);

        // Normalize URL
        $url = rtrim($url, '/');
        $url = preg_replace('/#.*$/', '', $url);

        if (in_array($url, $visited)) continue;
        if (in_array($url . '/', $visited)) continue;
        $visited[] = $url;

        // Skip non-HTML resources
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','svg','pdf','css','js','ico','mp4','mp3','zip','woff','woff2','ttf'])) {
            continue;
        }

        // Fetch page
        $ctx = stream_context_create([
            'http' => ['timeout' => 10, 'user_agent' => 'RobChat-Crawler/1.0', 'ignore_errors' => true],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $html = @file_get_contents($url, false, $ctx);
        if ($html === false) continue;

        // Check content type is HTML
        $isHtml = false;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (stripos($h, 'content-type:') !== false && stripos($h, 'text/html') !== false) {
                    $isHtml = true;
                    break;
                }
            }
        }
        if (!$isHtml && !preg_match('/<html/i', $html)) continue;

        // Extract internal links before stripping HTML
        if (preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $link) {
                $link = trim($link);

                // Skip anchors, mailto, tel, sms, javascript, data URIs
                if (preg_match('/^(#|mailto:|tel:|sms:|javascript:|data:)/i', $link)) continue;

                // Resolve relative URLs
                if (strpos($link, '//') === 0) {
                    $link = $baseScheme . ':' . $link;
                } elseif (strpos($link, '/') === 0) {
                    $link = $baseUrl . $link;
                } elseif (strpos($link, 'http') !== 0) {
                    $link = rtrim($url, '/') . '/' . $link;
                }

                // Only follow same-host links
                $linkHost = parse_url($link, PHP_URL_HOST);
                if ($linkHost === $baseHost && !in_array(rtrim($link, '/'), $visited)) {
                    $queue[] = $link;
                }
            }
        }

        // Scrape this page
        $result = scrapeAndSave($db, $tenantId, $url);
        if ($result['success']) {
            $totalChunks += $result['chunks'];
            $pagesScraped++;
        }

        // Small delay to be polite
        usleep(200000); // 200ms
    }

    if ($pagesScraped === 0) {
        return ['success' => false, 'error' => 'Could not scrape any pages from this site.'];
    }

    return ['success' => true, 'pages_scraped' => $pagesScraped, 'total_chunks' => $totalChunks];
}
