<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
$config = include('../../config.php');
include('../../db.php');
include('../../utils/token.php');
//include('../../utils/headercheck.php');

$json = file_get_contents('php://input');
$data = json_decode($json, true);
$phone = $data['phone'];
$verification_code = $data['code'];

// 查询用户
$stmt = $pdo->prepare('SELECT * FROM fy_users WHERE phone = ? AND verification_code = ?');
$stmt->execute([$phone, $verification_code]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // 验证码正确，更新用户状态
    $stmt = $pdo->prepare('UPDATE fy_users SET status = ? WHERE phone = ?');
    $stmt->execute(['verified', $phone]);
    echo json_encode([
        'success' => true,
        'status' => 'verified'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'status' => 'user_not_exists'
    ]);
}
?>
