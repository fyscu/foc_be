<?php
$config = include('../../config.php');
include('../../db.php');
if (session_status() === PHP_SESSION_NONE) {
    session_name('admin');
    session_start();
}

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

?>

<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>技术员导入</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- <script src="//static.wjlo.cc/js/jquery.js"></script> -->
</head>
<body>
<nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">技术员导入</a>
        <?php if (isLoggedIn()): ?>
        <div class="d-flex">
            <span class="navbar-text text-light me-3">
                <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
            </span>
            <!-- <a class="btn btn-outline-light" href="?logout=1">退出</a> -->
        </div>
        <?php endif; ?>
    </div>
</nav>
  <div class="container">
    <?php if (isLoggedIn()): ?>
    <h2 class="mb-4">技术员导入系统</h2>

    <!-- 手动导入 -->
    <div class="card mb-5">
      <div class="card-header">手动操作</div>
      <div class="card-body">
        <div class="input-group mb-3">
          <input type="text" class="form-control" id="searchInput" placeholder="输入手机号或昵称">
          <button class="btn btn-primary" onclick="searchUser()">搜索</button>
        </div>
        <div id="manualResult"></div>
      </div>
    </div>

    <!-- 批量导入 -->
    <div class="card">
    
      <div class="card-header">批量导入</div>
      
      <div class="card-body">
        <a href="example.xlsx" download class="d-inline-block mb-3 text-primary">
        点击下载批量导入示例表格
      </a>
        <form id="uploadForm" enctype="multipart/form-data">
          <div class="mb-3">
            <input type="file" name="file" class="form-control" accept=".xlsx">
          </div>
          <button type="submit" class="btn btn-success">上传并预览</button>
        </form>
        <div id="batchPreview" class="mt-4"></div>
      </div>
    </div>
  </div>
  <?php else: ?>
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
    <?php endif; ?>

<script>
function searchUser() {
  const val = document.getElementById('searchInput').value.trim();
  if (!val) return;
  fetch('api/import_manual.php?keyword=' + encodeURIComponent(val))
    .then(res => res.text())
    .then(html => document.getElementById('manualResult').innerHTML = html);
}

function changeRole(openid, toTech) {
  fetch('api/import_manual.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'openid=' + encodeURIComponent(openid) + '&toTech=' + (toTech ? 1 : 0)
  })
  .then(res => res.text())
  .then(html => document.getElementById('manualResult').innerHTML = html);
}

// 上传并预览批量数据
const form = document.getElementById('uploadForm');
form.addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(form);
  fetch('api/import_batch.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(json => {
    console.log(json);
    if (!json.success) {
      
      document.getElementById('batchPreview').innerHTML = '<div class="text-danger">' + json.message + '</div>';
      return;
    }

    const users = json.users;
    const table = document.createElement('table');
    table.className = 'table table-bordered';

    const thead = document.createElement('thead');
    thead.innerHTML = '<tr><th>姓名</th><th>手机号</th><th>身份</th><th>操作</th></tr>';
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    users.forEach(user => {
      const tr = document.createElement('tr');
      if (user['role'] == '技术员') {
        tr.innerHTML = `
        <td>${user['姓名'] ?? ''}</td>
        <td>${user['手机号'] ?? ''}</td>
        <td class=\"${user['role_class'] ?? 'text-muted'}\">${user['role'] ?? '未知'}</td>
        <td><button class=\"btn btn-sm btn-primary\" disabled>录入</button></td>
      `;
      } else {
        tr.innerHTML = `
        <td>${user['姓名'] ?? ''}</td>
        <td>${user['手机号'] ?? ''}</td>
        <td class=\"${user['role_class'] ?? 'text-muted'}\">${user['role'] ?? '未知'}</td>
        <td><button class=\"btn btn-sm btn-success\" onclick=\"commitImport('${user['openid']}')\">录入</button></td>
      `;
      }
      tbody.appendChild(tr);
    });

    table.appendChild(tbody);

    document.getElementById('batchPreview').innerHTML = `
      <div class=\"mb-2 text-end\">
        <button class=\"btn btn-primary\" onclick=\"commitImport()\">一键录入全部</button>
      </div>
    `;
    document.getElementById('batchPreview').appendChild(table);
  });
});

// 提交单个导入或全部导入
function commitImport(openid) {
  const body = openid ? 'openid=' + encodeURIComponent(openid) : 'all=1';
  fetch('api/commit_import.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body
  })
  .then(res => res.text())
  .then(html => document.getElementById('batchPreview').innerHTML = html);
}

function commitImportByPhone(phone) {
  fetch('api/commit_import.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'phone=' + encodeURIComponent(phone)
  })
  .then(res => res.text())
  .then(msg => alert(msg));
}
</script>
<footer class="mt-5 text-center text-muted small">
  <hr>
  Developed with ❤️ by <strong>初音过去</strong> in 2025<br>
</footer>
</body>
</html>
