<?php
/**
 * Analytics Tagger — Classify chat sessions via LLM and store results.
 *
 * Usage:
 *   php scripts/analytics-tagger.php                          # Nightly run (last 24h)
 *   php scripts/analytics-tagger.php --backfill               # All historical sessions
 *   php scripts/analytics-tagger.php --tenant=hw_harvest      # Filter to one tenant
 *   php scripts/analytics-tagger.php --batch=50               # Batch size (default 100)
 *   php scripts/analytics-tagger.php --dry-run                # Preview without calling LLM
 */

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/LLMClassifier.php';

// Load config and connect
$config = require __DIR__ . '/../config.php';
Database::connect($config);
$db = Database::db();

// Parse CLI args
$backfill     = false;
$tenantFilter = null;
$batchSize    = 100;
$dryRun       = false;

foreach ($argv as $arg) {
    if ($arg === '--backfill')              $backfill = true;
    if ($arg === '--dry-run')               $dryRun = true;
    if (strpos($arg, '--tenant=') === 0)    $tenantFilter = substr($arg, 9);
    if (strpos($arg, '--batch=') === 0)     $batchSize = max(1, (int)substr($arg, 8));
}

// Load LLM config from global_settings
$provider = Database::getGlobalSetting('analytics_llm_provider') ?: 'openai';
$apiKey   = Database::getGlobalSetting('analytics_api_key');
$model    = Database::getGlobalSetting('analytics_llm_model') ?: 'gpt-4o';

if (!$dryRun && empty($apiKey)) {
    fwrite(STDERR, "ERROR: analytics_api_key is not set in global_settings.\n");
    fwrite(STDERR, "Run: UPDATE global_settings SET value = 'sk-...' WHERE key = 'analytics_api_key';\n");
    exit(1);
}

$startTime = time();
$mode = $backfill ? 'BACKFILL' : 'NIGHTLY';
echo "Analytics Tagger — {$mode} mode\n";
echo "Provider: {$provider}, Model: {$model}, Batch: {$batchSize}\n";
if ($tenantFilter) echo "Tenant filter: {$tenantFilter}\n";
if ($dryRun)       echo "** DRY RUN — no LLM calls, no DB writes **\n";
echo "---\n";

// Fetch unanalyzed sessions
$sessions = Database::getUnanalyzedSessions($backfill, $batchSize);

// Apply tenant filter if specified
if ($tenantFilter) {
    $sessions = array_values(array_filter($sessions, fn($s) => $s['tenant_id'] === $tenantFilter));
}

$total = count($sessions);
echo "Found {$total} session(s) to process.\n";

if ($total === 0) {
    echo "Nothing to do.\n";
    Database::logAnalyticsRun(0, 0, 0, time() - $startTime);
    exit(0);
}

$classifier = $dryRun ? null : new LLMClassifier($provider, $apiKey, $model);

$processed    = 0;
$skipped      = 0;
$errors       = 0;
$errorDetails = [];

foreach ($sessions as $i => $session) {
    $num       = $i + 1;
    $sessionId = $session['id'];
    $tid       = $session['tenant_id'];

    echo "[{$num}/{$total}] {$tid}/{$sessionId} ... ";

    // Fetch messages
    $messages = Database::getMessages($sessionId, 200);

    if (count($messages) < 2) {
        echo "SKIP (< 2 messages)\n";
        $skipped++;
        continue;
    }

    if ($dryRun) {
        echo "DRY-RUN (" . count($messages) . " msgs)\n";
        $processed++;
        continue;
    }

    // Classify via LLM
    $classification = $classifier->classify($messages, $tid);

    if ($classification === null) {
        echo "FAILED\n";
        $errors++;
        $errorDetails[] = ['session_id' => $sessionId, 'tenant_id' => $tid, 'error' => 'classification returned null'];
        // Rate limit even on failure
        usleep(200000);
        continue;
    }

    // Compute session metadata
    $startedAt   = $session['started_at'];
    $lastActive  = $session['last_active'];
    $durationSec = (strtotime($lastActive) - strtotime($startedAt));
    if ($durationSec < 0) $durationSec = null;

    // Check lead_captured from session
    $stmtLead = $db->prepare('SELECT lead_captured FROM sessions WHERE id = :id');
    $stmtLead->execute(['id' => $sessionId]);
    $sessionRow = $stmtLead->fetch();
    $leadCaptured = !empty($sessionRow['lead_captured']);

    // Check if a booking exists for this session
    $stmtBooking = $db->prepare('SELECT COUNT(*) FROM bookings WHERE session_id = :id');
    $stmtBooking->execute(['id' => $sessionId]);
    $tourBooked = (int)$stmtBooking->fetchColumn() > 0;

    // Count user messages
    $userMsgCount = count(array_filter($messages, fn($m) => $m['role'] === 'user'));

    // Build analytics row
    $data = [
        'session_id'          => $sessionId,
        'tenant_id'           => $tid,
        'message_count'       => count($messages),
        'user_message_count'  => $userMsgCount,
        'intent_level'        => $classification['intent_level'],
        'lead_captured'       => $leadCaptured,
        'tour_booked'         => $tourBooked,
        'xo_tool_called'      => $classification['xo_tool_called'],
        'cross_referrals'     => $classification['cross_referrals'],
        'topics'              => $classification['topics'],
        'price_range_min'     => $classification['price_range_min'],
        'price_range_max'     => $classification['price_range_max'],
        'bedrooms_requested'  => $classification['bedrooms_requested'],
        'builders_mentioned'  => $classification['builders_mentioned'],
        'objections'          => $classification['objections'],
        'sentiment'           => $classification['sentiment'],
        'summary'             => $classification['summary'],
        'session_started_at'  => $startedAt,
        'session_duration_sec'=> $durationSec,
    ];

    $rowId = Database::insertAnalytics($data);

    if ($rowId > 0) {
        echo "OK (id={$rowId})\n";
    } else {
        echo "OK (already exists)\n";
    }
    $processed++;

    // Rate limit: 200ms between requests, 1s pause every batch
    if ($num % $batchSize === 0) {
        echo "  (pausing 1s after batch...)\n";
        sleep(1);
    } else {
        usleep(200000);
    }
}

$duration = time() - $startTime;

// Log the run (skip logging for dry runs)
if (!$dryRun) {
    Database::logAnalyticsRun($processed, $skipped, $errors, $duration, $errorDetails);
}

echo "---\n";
echo "Done. Processed: {$processed}, Skipped: {$skipped}, Errors: {$errors}, Duration: {$duration}s\n";

// If backfilling and there may be more, suggest re-running
if ($backfill && $total >= $batchSize) {
    echo "\nBatch limit reached — there may be more unanalyzed sessions.\n";
    echo "Re-run: php scripts/analytics-tagger.php --backfill\n";
}
