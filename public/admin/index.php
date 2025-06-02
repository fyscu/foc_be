<?php
session_name('admin');
session_start();

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>云上飞扬管理后台</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { overflow: hidden; }
    .sidebar {
      height: 100vh;
      position: fixed;
      top: 0;
      left: 0;
      padding-top: 1rem;
      width: 240px;
      background-color: #f8f9fa;
      border-right: 1px solid #dee2e6;
    }
    .main-content {
      margin-left: 240px;
      height: 100vh;
      overflow: hidden;
    }
    iframe {
      border: none;
      width: 100%;
      height: 100%;
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <div class="sidebar d-flex flex-column p-3">
    <h4 class="mb-4">云上飞扬管理后台</h4>
    <ul class="nav nav-pills flex-column mb-auto">
      <li class="nav-item">
        <a href="#dashboard" class="nav-link active" data-src="#dashboard">仪表盘</a>
      </li>
      <li>
        <a href="#users" class="nav-link" data-src="#users">用户管理</a>
      </li>
      <li>
        <a href="#orders" class="nav-link" data-src="#orders">订单管理</a>
      </li>
      <li>
        <a class="nav-link" data-bs-toggle="collapse" href="#toolboxMenu" role="button" aria-expanded="false" aria-controls="toolboxMenu">
            工具箱
        </a>
        <div class="collapse ps-3" id="toolboxMenu">
            <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
                <a href="#quickactive" class="nav-link" data-src="#quickactive">快捷激活</a>
            </li>
            <li>
                <a href="#techtransfer" class="nav-link" data-src="#techtransfer">技术员录入</a>
            </li>
            <li>
                <a href="#statistics" class="nav-link" data-src="#statistics">接单统计数据</a>
            </li>
            </ul>
        </div>
      </li>

      <li class="mt-3">
        <a href="#about" class="nav-link text-success" data-src="#about">关于</a>
      </li>
      <li class="mt-3">
        <a href="logout" class="nav-link text-danger">退出登录</a>
      </li>
    </ul>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <iframe id="contentFrame" src="dashboard"></iframe>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const iframe = document.getElementById('contentFrame');

    const routes = {
      dashboard: 'dashboard.php',
      users: 'users.php',
      orders: 'orders.php',
      about: 'about.php',
      quickactive: '../quickactive/index.php',
      techtransfer: '../techtransfer/index.php',
      statistics: '../statistics/index.php'
    };

    function setActive(route) {
      document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === '#' + route) {
          link.classList.add('active');
        }
      });
    }

    async function loadRoute() {
      const hash = location.hash.replace(/^#/, '') || 'dashboard';
      if (!routes[hash]) return;

      try {
        const res = await fetch('api/check_session.php');
        const data = await res.json();
        if (!data.logged_in) {
          window.location.href = 'login?expired=1';
          return;
        }

        iframe.src = routes[hash];
        setActive(hash);
      } catch (err) {
        alert('网络异常，无法验证登录状态。');
      }
    }

    window.addEventListener('hashchange', loadRoute);
    window.addEventListener('DOMContentLoaded', loadRoute);
  </script>


</body>
</html>
