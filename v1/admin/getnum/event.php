<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0); // 处理 OPTIONS 预检请求
}

$config = include('../../../config.php');
include('../../../db.php');
include('../../../utils/token.php');
include('../../../utils/headercheck.php'); // 验证 token 并获取 $userinfo 的全部数据

function unauthorizedResponse() {
    $response = [
        "success" => false,
        "message" => "权限不足",
    ];
    http_response_code(403);
    echo json_encode($response);
    exit;
}

if (!$userinfo['is_admin']) {
    unauthorizedResponse();
}

$type = isset($_GET['type']) ? $_GET['type'] : null;

$query = "SELECT COUNT(*) FROM fy_activities WHERE 1=1";
$params = [];

if ($type) {
    $query .= " AND type = ?";
    $params[] = $type;
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $activitiesCount = $stmt->fetchColumn();

    echo json_encode([
        "success" => true,
        "total_activities" => $activitiesCount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "服务器错误: " . $e->getMessage(),
    ]);
}
?>