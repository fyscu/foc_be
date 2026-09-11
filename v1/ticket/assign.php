<?php
$config = include('../../config.php');
include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');
require_once '../../utils/ticket_assign.php';

header('Content-Type: application/json; charset=UTF-8');

// 仅管理员可手动触发批量派单
if (empty($userinfo['is_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$results = runTicketAssignment($pdo, $config, false);

echo json_encode([
    'success' => true,
    'assigned_count' => count(array_filter($results, fn($r) => empty($r['skipped']))),
    'skipped_count' => count(array_filter($results, fn($r) => !empty($r['skipped']))),
    'results' => $results,
], JSON_UNESCAPED_UNICODE);
