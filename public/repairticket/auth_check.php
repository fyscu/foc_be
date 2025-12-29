<?php
session_start();
require_once 'api/config.php';
header('Content-Type: text/html; charset=utf-8');
$pdo = getDBConnection();

// 检查是否在登录页中使用
if (isset($_GET['action']) && $_GET['action'] === 'check') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['fyd_password'])) {
        echo json_encode(['valid' => false]);
        exit;
    }
    $stmt = $pdo->prepare("SELECT data FROM fy_confs WHERE name='FydPassword'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['data'] !== $_SESSION['fyd_password']) {
        session_unset();
        session_destroy();
        echo json_encode(['valid' => false]);
        exit;
    }
    echo json_encode(['valid' => true]);
    exit;
}

// 初次访问时检查是否有密码会话
if (!isset($_SESSION['fyd_password'])) {
    header('Location: https://focapp.feiyang.ac.cn/public/repairticket/login.php');
    exit;
}

// 每次加载页面时都立即同步验证数据库（防止密码被改但还在Session中）
$stmt = $pdo->prepare("SELECT data FROM fy_confs WHERE name='FydPassword'");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || $row['data'] !== $_SESSION['fyd_password']) {
    session_unset();
    session_destroy();
    header('Location: https://focapp.feiyang.ac.cn/public/repairticket/login.php');
    exit;
}
?>
<!-- 加入JS定时检查 -->
<script>
setInterval(() => {
    fetch('https://focapp.feiyang.ac.cn/public/repairticket/auth_check.php?action=check')
        .then(r => r.json())
        .then(d => {
            if (!d.valid) {
                // alert('访问密码已修改或过期，请重新登录。');
                window.location.href = 'https://focapp.feiyang.ac.cn/public/repairticket/login.php';
            }
        });
}, 6000); // 每60秒检查一次
</script>
