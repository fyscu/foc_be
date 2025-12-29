<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0); // 提前结束响应，处理 OPTIONS 预检请求
}

$config = include('../../config.php');
include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/qiniu_url.php');

$response = [];

$allowedFields = ['id', 'create_time', 'start_time', 'signup_start_time', 'signup_end_time'];

$sortField = $_GET['sort_by'] ?? 'id';
$sortOrder = $_GET['sort_in'] ?? 'asc';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 1000; 
$offset = ($page - 1) * $limit;

$isLuckyFilter = isset($_GET['isLucky']) ? (int)$_GET['isLucky'] : null;
$isIdFilter = isset($_GET['id']) ? (int)$_GET['id'] : null;
$sortField = 'id';
$sortOrder = 'desc';

if (!in_array($sortField, $allowedFields)) {
    $sortField = 'id';
}

if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
    $sortOrder = 'desc';
}

$sql = "SELECT * FROM fy_activities";
$params = [];

// 增加 isLucky 筛选条件
if ($isLuckyFilter !== null) {
    $sql .= " WHERE isLucky = :isLucky";
    $params[':isLucky'] = $isLuckyFilter;
}
if ($isIdFilter !== null) {
    $sql .= " WHERE id = :id";
    $params[':id'] = $isIdFilter;
}

// 应用排序
$sql .= " ORDER BY $sortField $sortOrder LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($activities) {
    foreach ($activities as &$activity) {
        $activity['poster'] = generatePrivateLink($activity['poster']);
        $activityId = $activity['id'];
        if ($activity['type'] == "大修"){
            $registrationSql = "
                SELECT 
                    COUNT(*) as total_count,
                    SUM(CASE WHEN user_id = :user_id THEN 1 ELSE 0 END) as user_count
                FROM fy_repair_registrations 
                WHERE activity_id = :activity_id";
            $registrationStmt = $pdo->prepare($registrationSql);
            $registrationStmt->execute(['activity_id' => $activityId, 'user_id' => $userinfo['id']]);
            $registrationResult = $registrationStmt->fetch(PDO::FETCH_ASSOC);

            $activity['registered'] = $registrationResult['user_count'] > 0;
        } else {
            $registrationSql = "
                SELECT 
                    COUNT(*) as total_count,
                    SUM(CASE WHEN user_id = :user_id THEN 1 ELSE 0 END) as user_count
                FROM fy_registrations 
                WHERE activity_id = :activity_id";
            $registrationStmt = $pdo->prepare($registrationSql);
            $registrationStmt->execute(['activity_id' => $activityId, 'user_id' => $userinfo['id']]);
            $registrationResult = $registrationStmt->fetch(PDO::FETCH_ASSOC);

            $activity['registered'] = $registrationResult['user_count'] > 0;
            $activity['max_luckynum'] = $registrationResult['total_count'];
        }
    }
    $response = [
        'success' => true,
        'activities' => $activities
    ];
} else {
    $response = [
        'success' => false,
        'activities' => 'No activities found'
    ];
}

echo json_encode($response);
?>