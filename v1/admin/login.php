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
include('../../utils/gets.php');
include('../../utils/token.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(["success" => false, "message" => "缺少必要的参数"]);
        exit();
    }

    // 获取管理员信息
    $stmt = $pdo->prepare("SELECT * FROM fy_admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        // 获取用户的 openid
        $openid = $admin['openid'];
        $type = $admin['role'];
        // 从 fy_users 表中获取用户数据
        $stmt = $pdo->prepare("SELECT * FROM fy_users WHERE openid = ?");
        $stmt->execute([$openid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {

            $tokenData = generateToken($openid, $config['token']['salt']);
            $token = $tokenData['token'];
            unset($user['verification_code']);
            unset($user['access_token']);
            echo json_encode(["success" => true,
                              "access_token" => $token,
                              "message" => "登录成功",
                              "type" => $type,
                              "user" => $user
                             ]);
        } else {
            echo json_encode(["success" => false, "message" => "用户数据获取失败"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "用户名或密码错误"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "无效的请求方法"]);
}
?>