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

$role = isset($_GET['role']) ? $_GET['role'] : null;
$uid = isset($_GET['uid']) ? $_GET['uid'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;
$available = isset($_GET['available']) ? $_GET['available'] : null;
$campus = isset($_GET['campus']) ? $_GET['campus'] : null; 
$immed = isset($_GET['immed']) ? $_GET['immed'] : null; 

$query = "SELECT COUNT(*) FROM fy_users WHERE 1=1";
$params = [];

if ($role) {
    $query .= " AND role = ?";
    $params[] = $role;
}

if ($status) {
    $query .= " AND status = ?";
    $params[] = $status;
}

if ($uid) {
    $query .= " AND id = ?";
    $params[] = $uid;
}

if ($available !== null) {
    $query .= " AND available = ?";
    $params[] = $available;
}

if ($immed !== null) {
    $query .= " AND immed = ?";
    $params[] = $immed;
}

if ($campus) {
    $query .= " AND campus = ?";
    $params[] = $campus;
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $usersCount = $stmt->fetchColumn();

    echo json_encode([
        "success" => true,
        "total_users" => $usersCount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "服务器错误: " . $e->getMessage(),
    ]);
}
?>