<?php
session_name('admin');
session_start();

require '../../db.php';
$config = include('../../config.php');

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: /public/admin/");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM fy_admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            // 登录成功，设置 session
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            header("Location: /public/admin/");
            exit;
        } else {
            $error = "用户名或密码错误";
        }
    } else {
        $error = "请填写用户名和密码";
    }
}

$expired = isset($_GET['expired']) && $_GET['expired'] === '1';
if ($expired) {
    $error = "登录已过期，请重新登录";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>管理员登录</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex justify-content-center align-items-center vh-100 bg-light">
  <div class="card shadow p-4" style="min-width: 320px; max-width: 400px;">
    <h4 class="mb-3 text-center">后台登录</h4>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="mb-3">
        <label for="username" class="form-label">用户名</label>
        <input type="text" class="form-control" name="username" id="username" required autofocus>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">密码</label>
        <input type="password" class="form-control" name="password" id="password" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">登录</button>
    </form>
  </div>
</body>
</html>
