<?php
require_once __DIR__ . '/auth.php';

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$db = Database::db();
$tenantId = getTenantId();

// ─── POST: Delete sessions ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'delete') {
        $ids = $input['ids'] ?? [];
        if (empty($ids)) {
            echo json_encode(['error' => 'No IDs provided.']);
            exit;
        }

        $deleted = 0;
        foreach ($ids as $id) {
            // Delete messages first, then session (scoped to tenant)
            $stmt = $db->prepare('DELETE FROM messages WHERE session_id = :sid AND session_id IN (SELECT id FROM sessions WHERE tenant_id = :tid)');
            $stmt->execute(['sid' => $id, 'tid' => $tenantId]);

            $stmt = $db->prepare('DELETE FROM leads WHERE session_id = :sid AND tenant_id = :tid');
            $stmt->execute(['sid' => $id, 'tid' => $tenantId]);

            $stmt = $db->prepare('DELETE FROM sessions WHERE id = :sid AND tenant_id = :tid');
            $stmt->execute(['sid' => $id, 'tid' => $tenantId]);
            $deleted += $stmt->rowCount();
        }

        echo json_encode(['success' => true, 'deleted' => $deleted]);
        exit;
    }

    echo json_encode(['error' => 'Unknown action.']);
    exit;
}

// ─── GET: Export ───
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $format = $_GET['format'] ?? 'json';

    if ($action === 'export') {
        $range = $_GET['range'] ?? 'all';
        $where = ' AND s.tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];

        if ($range !== 'all' && $range !== 'custom') {
            $days = (int) $range;
            if ($days > 0) {
                $where .= ' AND s.started_at >= :after';
                $params['after'] = date('Y-m-d', strtotime("-{$days} days"));
            }
        }

        // Single session export
        $sessionId = $_GET['session'] ?? '';
        if ($sessionId) {
            $where = ' AND s.id = :sid AND s.tenant_id = :tenant_id';
            $params = ['sid' => $sessionId, 'tenant_id' => $tenantId];
        }

        $stmt = $db->prepare("
            SELECT s.id as session_id, s.started_at, s.page_url, s.message_count, s.lead_captured,
                   m.role, m.content, m.llm_provider, m.created_at as message_time
            FROM sessions s
            LEFT JOIN messages m ON m.session_id = s.id
            WHERE 1=1 $where
            ORDER BY s.started_at DESC, m.created_at ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="robchat-export-' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['session_id', 'started_at', 'page_url', 'role', 'content', 'llm_provider', 'message_time']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['session_id'], $r['started_at'], $r['page_url'],
                    $r['role'], $r['content'], $r['llm_provider'], $r['message_time']
                ]);
            }
            fclose($out);
        } else {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="robchat-export-' . date('Y-m-d') . '.json"');
            echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Bad request.']);
