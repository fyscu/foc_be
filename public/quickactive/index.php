<?php
session_name('active');
session_start();
require '../../utils/sms.php';
require '../../db.php';
$config = include('../../config.php');

function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, password FROM fy_admins WHERE username = ? AND role = 'active'");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } else {
            $login_error = "用户名或密码错误";
        }
    } catch (PDOException $e) {
        $login_error = "数据库错误: " . $e->getMessage();
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

$user_info = null;
$search_error = null;
if (isLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $phone = $_POST['phone'] ?? '';
    
    try {
        $stmt = $pdo->prepare("SELECT id, phone, status, immed FROM fy_users WHERE phone = ?");
        $stmt->execute([$phone]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_info) {
            $search_error = "未找到该手机号对应的用户";
        }
    } catch (PDOException $e) {
        $search_error = "数据库错误: " . $e->getMessage();
    }
}

if (isLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $user_id = $_POST['user_id'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("SELECT status, immed, phone FROM fy_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // echo json_encode($user);

            if ($user['status'] === 'verified' && $user['immed'] == 0) {

                $stmt = $pdo->prepare("SELECT id, openid FROM fy_users WHERE phone IS NULL ORDER BY id DESC LIMIT 1");
                $stmt->execute();
                $new_user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($new_user) {
                    $stmt = $pdo->prepare("UPDATE fy_users SET openid = ?, immed = 1 WHERE id = ?");
                    $stmt->execute([$new_user['openid'], $user_id]);

                    $stmt = $pdo->prepare("DELETE FROM fy_users WHERE id = ?");
                    $stmt->execute([$new_user['id']]);

                    $success_message = "用户迁移激活成功，最新记录已删除";
                } else {
                    $search_error = "未找到空手机号的用户记录进行迁移";
                }
            } else {
                $stmt = $pdo->prepare("UPDATE fy_users SET status = 'verified' WHERE id = ? AND status = 'pending'");
                $stmt->execute([$user_id]);

                if ($stmt->rowCount() > 0) {
                    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
                    $success_message = "已成功激活";
                } else {
                    $search_error = "这个用户已经激活过了";
                }
            }
        } else {
            $search_error = "未找到该用户";
        }
    } catch (PDOException $e) {
        $search_error = "数据库错误: " . $e->getMessage();
    }
}
?>

<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <title>用户状态管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <script src="//static.wjlo.cc/js/jquery.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <style>
        .container{
            text-align: center;
            float: none;
        }
        .user-info-card {
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
            border-radius: 5px;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">用户状态管理系统</a>
        <?php if(isLoggedIn()): ?>
            <div class="d-flex">
                <a class="btn btn-outline-light me-2" href="pending_users.php">待激活用户</a>
                <span class="navbar-text text-light me-3">
                    欢迎, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                </span>
                <a class="btn btn-outline-light" href="?logout=1">退出</a>
            </div>
        <?php endif; ?>
    </div>
</nav>

<main class="container">
    <?php if(!isLoggedIn()): ?>
        <div class="bg-light p-5 rounded">
            <h2>登录</h2>
            <?php if(isset($login_error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <input type="text" class="form-control" name="username" placeholder="用户名" required>
                </div>
                <div class="mb-3">
                    <input type="password" class="form-control" name="password" placeholder="密码" required>
                </div>
                <button class="btn btn-lg btn-primary" type="submit" name="login">登录</button>
            </form>
        </div>
    <?php else: ?>
        <div class="bg-light p-5 rounded">
            <h2>用户状态管理</h2>
            <form method="post" id="searchForm">
                <div class="mb-3">
                    <input type="text" class="form-control" name="phone" placeholder="输入用户手机号" required>
                </div>
                <button class="btn btn-lg btn-primary" type="submit" name="search">查询</button>
            </form>

            <?php if(isset($search_error)): ?>
                <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($search_error); ?></div>
            <?php endif; ?>

            <?php if(isset($success_message)): ?>
                <div class="alert alert-success mt-3"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if($user_info): ?>
                <div class="user-info-card mt-4">
                    <h4>用户信息</h4>
                    <p><strong>手机号:</strong> <?php echo htmlspecialchars($user_info['phone']); ?></p>
                    <p><strong>注册状态:</strong>
                        <span class="badge bg-<?php echo $user_info['status'] === 'verified' ? 'success' : 'warning'; ?>">
                            <?php echo htmlspecialchars($user_info['status']); ?>
                        </span>
                    </p>
                    <p><strong>迁移状态:</strong>
                        <span class="badge bg-<?php echo $user_info['immed'] === '1' ? 'success' : 'danger'; ?>">
                            <?php echo $user_info['immed'] === '1' ? '已迁移' : '未迁移'; ?>
                        </span>
                    </p>

                    <?php if($user_info['status'] === 'pending' || $user_info['immed'] === '0'): ?>
                        <form method="post" id="verifyForm">
                            <input type="hidden" name="user_id" value="<?php echo $user_info['id']; ?>">
                            <button class="btn btn-lg btn-danger mt-3" type="submit" name="verify">验证用户</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<script>
$(document).ready(function() {
    const notyf = new Notyf({
        duration: 5000,
        position: {
            x: 'right',
            y: 'top',
        },
    });

    $('#verifyForm').submit(function(e) {
        if(!confirm('确定要将该用户状态改为verified吗？')) {
            e.preventDefault();
        }
    });

    <?php if(isset($search_error)): ?>
        notyf.error('<?php echo addslashes($search_error); ?>');
    <?php endif; ?>

    <?php if(isset($success_message)): ?>
        notyf.success('<?php echo addslashes($success_message); ?>');
    <?php endif; ?>
});
</script>

<script src="assets/bootstrap.bundle.min.js"></script>
</body>
</html>
