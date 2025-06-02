<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
if (session_status() === PHP_SESSION_NONE) {
    session_name('admin');
    session_start();
}
require '../../../db.php';
$config = include('../../../config.php');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $keyword = trim($_GET['keyword'] ?? '');
    if ($keyword === '') {
        exit('<div class="text-danger">请输入手机号或昵称</div>');
    }

    $stmt = $pdo->prepare("SELECT id, nickname, phone, role FROM fy_users WHERE phone = ? OR nickname LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$keyword, "%$keyword%"]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        exit('<div class="text-danger">未找到用户</div>');
    }

    if ($user['role'] === 'technician') {
        $btnTech = "<button class='btn btn-success btn-sm' disabled>已是技术员</button>";
        $btnUser = "<button class='btn btn-primary btn-sm' onclick=\"changeRole('{$user['id']}',0)\">成为用户</button>";
    }
    if ($user['role'] === 'user') {
        $btnTech = "<button class='btn btn-success btn-sm' onclick=\"changeRole('{$user['id']}',1)\">成为技术员</button>";
        $btnUser = "<button class='btn btn-primary btn-sm' disabled>已是用户</button>";
    }
    

    echo "<div class='alert alert-light'>
      <b>姓名：</b> {$user['nickname']}<br>
      <b>手机号：</b> {$user['phone']}<br>
      <b>当前身份：</b> {$user['role']}<br>
      <div class='mt-2'>$btnTech $btnUser</div>
    </div>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['openid'] ?? 0);
    $toTech = intval($_POST['toTech'] ?? 0);

    if (!$id) exit('参数错误');

    $stmt = $pdo->prepare("SELECT id, nickname, role FROM fy_users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) exit('用户不存在');

    if ($toTech) {
        $update = $pdo->prepare("UPDATE fy_users SET role = 'technician', available = 1, wants = 'a' WHERE id = ?");
        $update->execute([$id]);
    } else {
        $update = $pdo->prepare("UPDATE fy_users SET role = 'user', available = 5 WHERE id = ?");
        $update->execute([$id]);
    }

    echo '<div class="text-success">更新成功</div>';
    $_GET['keyword'] = $user['nickname'];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    require __FILE__;
    exit;
}

exit('非法请求');
