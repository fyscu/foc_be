<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
$config = include('../../config.php');
include('../../db.php');
require '../../utils/email.php';
require '../../utils/sms.php';
include('../../utils/token.php');
include('../../utils/headercheck.php'); //永远记得这里通过access_token给了$userinfo的全部数据
include('../../utils/gets.php');


// 获取请求参数
$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : null;
$openid = isset($_GET['openid']) ? $_GET['openid'] : null;
$access_token = isset($_GET['access_token']) ? $_GET['access_token'] : null;
$phone = isset($_GET['phone']) ? $_GET['phone'] : null;
$email = isset($_GET['email']) ? $_GET['email'] : null;
$campus = isset($_GET['campus']) ? $_GET['campus'] : null;
$role = isset($_GET['role']) ? $_GET['role'] : null;
$available = isset($_GET['available']) ? $_GET['available'] : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// 设置默认页数限制
$limit = 10;

if ($userinfo['is_admin']) { //如果是管理员身份，就可以有更多的查询选项
    $query = "SELECT * FROM fy_users WHERE 1=1";
    $params = [];
    if ($uid) {
        $query .= " AND id = ?";
        $params[] = $uid;
    }

    if ($openid) {
        $query .= " AND openid = ?";
        $params[] = $openid;
    }

    if ($phone) {
        $query .= " AND phone = ?";
        $params[] = $phone;
    }

    if ($email) {
        $query .= " AND email = ?";
        $params[] = $email;
    }

    if ($campus) {
        $query .= " AND campus = ?";
        $params[] = $campus;
    }

    if ($role) {
        $query .= " AND role = ?";
        $params[] = $role;
    }

    if ($available) {
        $query .= " AND available = ?";
        $params[] = $available;
    }
    $start = ($page - 1) * $limit;
    $query .= " LIMIT $limit OFFSET $start";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $userData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 去掉敏感信息
    foreach ($userData as &$user) {
    unset($user['verification_code'], $user['access_token']);
    }
    //var_dump($userData);
    $requestType = "all_users";
} else {

// 查询用户数据
$userData = null;

if ($uid) {
    $userData = getUserById($uid);
    $requestType = 'by_uid';
}

if (!$userData && $openid) {
    $userData = getUserByOpenid($openid);
    $requestType = 'by_openid';
}

if (!$userData && $access_token) {
    $userData = getUserByAccessToken($access_token);
    $requestType = 'by_access_token';
}

if (!$userData && $phone) {
    $userData = getUserByPhone($phone);
    $requestType = 'by_phone';
}

if (!$userData && $email) {
    $userData = getUserByEmail($email);
    $requestType = 'by_email';
}
}
// 如果没有找到用户数据，则返回权限不足错误
if (!$userData) {
    echo json_encode([
        "success" => false,
        "requesttype" => "",
        "data" => "user_not_found"
    ]);
    http_response_code(403);
    exit;
}

// 移除无效数据
unset($userData['verification_code']);
unset($userData['access_token']);

// 返回用户数据
echo json_encode([
    "success" => true,
    "request_type" => $requestType,
    "data" => $userData
]);
?>