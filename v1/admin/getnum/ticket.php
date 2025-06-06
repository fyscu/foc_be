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
include('../../../utils/headercheck.php');

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

$repair_status = isset($_GET['repair_status']) ? $_GET['repair_status'] : null;
$technician_id = isset($_GET['tid']) ? (int)$_GET['tid'] : null;

$query = "SELECT COUNT(*) FROM fy_workorders WHERE 1=1";
$params = [];

if ($repair_status) {
    $query .= " AND repair_status = ?";
    $params[] = $repair_status;
}

if ($technician_id) {
    $query .= " AND assigned_technician_id = ?";
    $params[] = $technician_id;
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $workordersCount = $stmt->fetchColumn();

    echo json_encode([
        "success" => true,
        "total_workorders" => $workordersCount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "服务器错误: " . $e->getMessage(),
    ]);
}
?>