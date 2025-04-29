<?php
session_name('active');
session_start();
require '../../db.php';
$config = include('../../config.php');

function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$pending_users = [];
try {
    $stmt = $pdo->prepare("SELECT id, phone FROM fy_users WHERE status = 'pending' AND immed = '1' AND openid is not null AND phone is not null ORDER BY id DESC");
    $stmt->execute();
    $pending_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fy_users WHERE status = 'pending' AND immed = '1' AND openid is not null AND phone is not null");
    $stmt->execute();
    $pendingCount = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $error = "数据库错误: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $user_id = $_POST['user_id'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("UPDATE fy_users SET status = 'verified' WHERE id = ? AND status = 'pending'");
        $stmt->execute([$user_id]);
        
        if ($stmt->rowCount() > 0) {
            $success_message = "用户状态已更新为verified";
            header("Location: pending_users.php");
            exit();
        } else {
            $error = "用户状态更新失败，可能状态已经不是pending";
        }
    } catch (PDOException $e) {
        $error = "数据库错误: " . $e->getMessage();
    }
}
?>

<!doctype html>
<html lang="zh-CN">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <title>待激活用户列表</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <script src="//static.wjlo.cc/js/jquery.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>


    <style>
      .bd-placeholder-img {
        font-size: 1.125rem;
        text-anchor: middle;
        -webkit-user-select: none;
        -moz-user-select: none;
        user-select: none;
      }

      @media (min-width: 768px) {
        .bd-placeholder-img-lg {
          font-size: 3.5rem;
        }
      }
      .container {
          max-width: 1200px;
      }
      .table-responsive {
          margin-top: 20px;
      }
    </style>
  </head>
  <body>
    
<nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">用户状态管理系统</a>
    <div class="d-flex">
      <a class="btn btn-outline-light me-2" href="index.php">返回主页</a>
      <span class="navbar-text text-light me-3">
        欢迎, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
      </span>
      <a class="btn btn-outline-light" href="?logout=1">退出</a>
    </div>
  </div>
</nav>

<main class="container">
  <div class="bg-light p-5 rounded">
    <h2>待激活用户列表</h2>
    <p class="lead">共计 <? echo $pendingCount; ?> 名手机号未激活用户</p>
    
    <?php if(isset($error)): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if(isset($success_message)): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th scope="col">#</th>
            <th scope="col">手机号</th>
            <th scope="col">状态</th>
            <th scope="col">操作</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($pending_users)): ?>
            <tr>
              <td colspan="4" class="text-center">没有待激活的用户</td>
            </tr>
          <?php else: ?>
            <?php foreach($pending_users as $index => $user): ?>
              <tr>
                <th scope="row"><?php echo $user['id']; ?></th>
                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                <td><span class="badge bg-warning">待激活</span></td>
                <td>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                    <button type="submit" name="verify" class="btn btn-sm btn-danger" 
                            onclick="return confirm('确定要激活此用户吗？')">
                      激活
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
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
    
    <?php if(isset($error)): ?>
        notyf.error('<?php echo addslashes($error); ?>');
    <?php endif; ?>
    
    <?php if(isset($success_message)): ?>
        notyf.success('<?php echo addslashes($success_message); ?>');
    <?php endif; ?>
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>