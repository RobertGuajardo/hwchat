<?php
$webhookSecret = trim(file_get_contents('/home/rober253/.deploy-secret'));
$logFile = '/home/rober253/deploy.log';

function logMsg($msg) {
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND | LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

if (empty($signature)) {
    logMsg('REJECTED: No signature');
    http_response_code(403);
    exit;
}

$expected = 'sha256=' . hash_hmac('sha256', $payload, $webhookSecret);

if (!hash_equals($expected, $signature)) {
    logMsg('REJECTED: Invalid signature');
    http_response_code(403);
    exit;
}

$data = json_decode($payload, true);
if (($data['ref'] ?? '') !== 'refs/heads/main') {
    http_response_code(200);
    echo json_encode(['status' => 'skipped']);
    exit;
}

$pusher = $data['pusher']['name'] ?? 'unknown';
logMsg("WEBHOOK RECEIVED from {$pusher}");

// Write trigger file — cron picks this up and runs deploy
file_put_contents('/home/rober253/.deploy-trigger', time());

logMsg('DEPLOY TRIGGER WRITTEN');
http_response_code(200);
echo json_encode(['status' => 'triggered']);
