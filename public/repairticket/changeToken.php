<?php
session_start();
require_once 'api/config.php';
header('Content-Type: text/html; charset=utf-8');
$pdo = getDBConnection();

// ==========================
// 写死的密钥（自行修改）
// ==========================
$secretKey = '39y713xr31y9rc9y713y';

// 鉴权检查
if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    http_response_code(403);
    exit("403 Forbidden：密钥无效。");
}

$successMsg = '';
$errorMsg = '';

$stmtQuery = $pdo->prepare("SELECT data FROM fy_confs WHERE name='FydPassword'");
$stmtQuery->execute();
$rowRaw = $stmtQuery->fetch(PDO::FETCH_ASSOC);
$currentPassword = $rowRaw ? $rowRaw['data'] : '';

// 处理密码更新逻辑
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPass = trim($_POST['new_password'] ?? '');
    $confirmPass = trim($_POST['confirm_password'] ?? '');

    if ($newPass === '' || $confirmPass === '') {
        $errorMsg = "❌ 新密码不能为空。";
    } elseif ($newPass !== $confirmPass) {
        $errorMsg = "❌ 两次输入的密码不一致。";
    } else {
        // 更新数据库
        $stmt = $pdo->prepare("UPDATE fy_confs SET data=:data WHERE name='FydPassword'");
        $stmt->execute([':data' => $newPass]);

        // 清除所有现有 session（使旧密码立即失效）
        session_unset();
        session_destroy();

        $successMsg = "✅ 密码修改成功！所有登录已失效。";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>修改访问密码</title>
<style>
body {
    font-family: "Segoe UI", "Microsoft YaHei", sans-serif;
    background: linear-gradient(135deg, #7fa8ff, #5260de);
    color: #333;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}
.container {
    background: white;
    padding: 40px;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    text-align: center;
    width: 380px;
}
input[type="password"] {
    width: 80%;
    padding: 10px;
    margin: 15px 0;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 16px;
}
button {
    background-color: #5563DE;
    color: white;
    border: none;
    padding: 10px 25px;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    transition: 0.3s;
}
button:hover {
    background-color: #3846B8;
}
.msg {
    margin-top: 10px;
    font-size: 15px;
}
.success {
    color: green;
}
.error {
    color: red;
}
a.back {
    display: inline-block;
    margin-top: 20px;
    text-decoration: none;
    color: #5563DE;
}
a.back:hover {
    text-decoration: underline;
}
</style>
</head>
<body>
<div class="container">
    <h2>🔑 修改 FydPassword</h2>

    <?php if ($successMsg): ?>
        <p class="msg success"><?= htmlspecialchars($successMsg) ?></p>
    <?php elseif ($errorMsg): ?>
        <p class="msg error"><?= htmlspecialchars($errorMsg) ?></p>
    <?php endif; ?>

    <?php if (!$successMsg): ?>
        <p class="msg success"><?= htmlspecialchars($currentPassword) ?></p>
    <form method="post">
        <input type="password" name="new_password" placeholder="请输入新密码" required><br>
        <input type="password" name="confirm_password" placeholder="请再次输入新密码" required><br>
        <button type="submit">更新密码</button>
    </form>
    <?php endif; ?>

    <!-- <a href="/page1.php" class="back">返回登录页</a> -->
</div>
</body>
</html>
