<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
$config = include('../../config.php');
include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');
// 获取POST数据
$phone = $_POST['phone'];
$verification_code = $_POST['verification_code'];

// 查询用户
$stmt = $pdo->prepare('SELECT * FROM fy_users WHERE phone = ? AND verification_code = ?');
$stmt->execute([$phone, $verification_code]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // 验证码正确，更新用户状态
    $stmt = $pdo->prepare('UPDATE fy_users SET status = ? WHERE phone = ?');
    $stmt->execute(['verified', $phone]);
    echo json_encode([
        'status' => 'verified',
        'success' => true,
        'registered' => true,
        'openid' => $user['openid'],
        'email' => $user['email'],
        'uid' => $user['id'],
        'avatar' => $user['avatar'],
        'campus' => $user['campus'],
        'phone' => $user['phone'],
        'role' => $user['role'],
        'nickname' => $user['nickname']
    ]);
} else {
    echo json_encode([
        'status' => 'verification_failed',
        'success' => false,
        'registered' => false,
        'openid' => '',
        'email' => '',
        'uid' => '',
        'avatar' => '',
        'campus' => '',
        'phone' => '',
        'role' => '',
        'nickname' => ''
    ]);
}
?>
