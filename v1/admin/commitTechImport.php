<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config = include('../../config.php');
include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/admincheck.php');
include('../../utils/adminlog.php');

requireAdminRole(['super', 'active']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true) ?: [];
$phones = $data['phones'] ?? [];

if (!is_array($phones) || count($phones) === 0) {
    echo json_encode(['success' => false, 'message' => '未提供手机号列表']);
    exit;
}
if (count($phones) > 200) {
    echo json_encode(['success' => false, 'message' => '单次最多导入 200 条']);
    exit;
}

$results = [];
foreach ($phones as $phone) {
    $phone = trim((string)$phone);
    if ($phone === '') {
        continue;
    }

    // 提交时重查（不信任预览快照），与老 commit_import.php 同口径
    $stmt = $pdo->prepare("SELECT id, nickname, role FROM fy_users WHERE phone = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$phone]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $results[] = ['phone' => $phone, 'ok' => false, 'message' => '未找到该手机号的用户'];
        continue;
    }
    if ($user['role'] === 'technician') {
        $results[] = ['phone' => $phone, 'ok' => false, 'message' => $user['nickname'] . ' 已经是技术员'];
        continue;
    }

    $update = $pdo->prepare("UPDATE fy_users SET role = 'technician', available = 1, wants = 'a' WHERE id = ?");
    $update->execute([$user['id']]);
    $results[] = ['phone' => $phone, 'ok' => true, 'message' => $user['nickname'] . ' 导入成功'];
}

$okCount = count(array_filter($results, function ($r) { return $r['ok']; }));

fyAdminLog($pdo, $adminAccount, 'tech_import', 'batch', '', [
    'total' => count($results),
    'ok' => $okCount,
    'results' => $results
]);

echo json_encode([
    'success' => true,
    'ok_count' => $okCount,
    'results' => $results
], JSON_UNESCAPED_UNICODE);
