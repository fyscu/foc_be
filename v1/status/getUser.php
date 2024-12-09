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
require '../../utils/email.php';
require '../../utils/sms.php';
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/gets.php');
include('../../utils/qiniu_url.php');

// 获取请求参数
$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : null;
$openid = isset($_GET['openid']) ? $_GET['openid'] : null;
$access_token = isset($_GET['access_token']) ? $_GET['access_token'] : null;
$phone = isset($_GET['phone']) ? $_GET['phone'] : null;
$email = isset($_GET['email']) ? $_GET['email'] : null;
$campus = isset($_GET['campus']) ? $_GET['campus'] : null;
$immed = isset($_GET['immed']) ? $_GET['immed'] : null;
$role = isset($_GET['role']) ? $_GET['role'] : null;
$available = isset($_GET['available']) ? $_GET['available'] : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

$isUniqueQuery = $uid || $openid || $access_token || $phone || $email;

if ($userinfo['is_admin'] && $userinfo['is_lucky_admin']) {
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
    if ($access_token) {
        $query .= " AND access_token = ?";
        $params[] = $access_token;
    }
    if ($phone) {
        $query .= " AND phone = ?";
        $params[] = $phone;
    }
    if ($email) {
        $query .= " AND email = ?";
        $params[] = $email;
    }
    if ($immed !== null) {
        $query .= " AND immed = ?";
        $params[] = $immed;
    }
    if ($campus) {
        $query .= " AND campus = ?";
        $params[] = $campus;
    }
    if ($role) {
        $query .= " AND role = ?";
        $params[] = $role;
    }
    if ($available !== null) {
        $query .= " AND available = ?";
        $params[] = (int)$available;
    }

    if (!$isUniqueQuery) {
        $start = ($page - 1) * $limit;
        $query .= " LIMIT $limit OFFSET $start";
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $userData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($userData as &$user) {
        unset($user['verification_code'], $user['access_token'], $user['temp_phone']);
        if ($user['email_status'] !== "verified") {
            $user['email_status'] = "unverified";
        }
    }

    if ($isUniqueQuery && count($userData) === 1) {
        $userData = $userData[0];
    }

} else {
    $userData = null;

    if ($uid) {
        $userData = getUserById($uid);
    } elseif ($openid) {
        $userData = getUserByOpenid($openid);
    } elseif ($access_token) {
        $userData = getUserByAccessToken($access_token);
    } elseif ($phone) {
        $userData = getUserByPhone($phone);
    } elseif ($email) {
        $userData = getUserByEmail($email);
    }
}

if (!$userData) {
    echo json_encode([
        "success" => false,
        "request_type" => "",
        "data" => "user_not_found"
    ]);
    http_response_code(403);
    exit;
}

if (is_array($userData)) {
    foreach ($userData as &$user) {
        
        $user['avatar'] = generatePrivateLink($user['avatar']);

        if (is_array($user)) {  // 确认 $user 是数组
            unset($user['verification_code'], $user['access_token'], $user['temp_phone']);
        }
    }
} elseif (is_array($userData)) { // 如果单条数据也确认是数组
    unset($userData['verification_code'], $userData['access_token'], $userData['temp_phone']);
}

echo json_encode([
    "success" => true,
    "request_type" => $isUniqueQuery ? 'unique_query' : 'multi_query',
    "data" => $userData
]);
?>