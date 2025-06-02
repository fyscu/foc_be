<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('admin');
    session_start();
}
if (empty($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['admin_role'] ?? '';

require '../../db.php';
$config = include('../../config.php');

// 基础统计信息
$counts = [
    'technicians' => 0,
    'users' => 0,
    'workorders' => 0
];
$campus_tech = ['江安' => 0, '望江' => 0, '华西' => 0];
$campus_user = ['江安' => 0, '望江' => 0, '华西' => 0];
$campus_workorder = ['江安' => 0, '望江' => 0, '华西' => 0, '线下' => 0];

// 技术员总数 + 校区分布
$stmt = $pdo->query("SELECT campus, COUNT(*) as count FROM fy_users WHERE role = 'technician' AND immed = '1' AND wants != 'a' GROUP BY campus");
foreach ($stmt->fetchAll() as $row) {
    $counts['technicians'] += $row['count'];
    $campus_tech[$row['campus']] = $row['count'];
}

// 用户总数 + 校区分布
$stmt = $pdo->query("SELECT campus, COUNT(*) as count FROM fy_users WHERE role = 'user' GROUP BY campus");
foreach ($stmt->fetchAll() as $row) {
    $counts['users'] += $row['count'];
    $campus_user[$row['campus']] = $row['count'];
}

// 已激活用户总数 + 校区分布
$stmt = $pdo->query("SELECT campus, COUNT(*) as count FROM fy_users WHERE role = 'user' AND immed = '1' GROUP BY campus");
foreach ($stmt->fetchAll() as $row) {
    $counts['actived_users'] += $row['count'];
    $campus_activeduser[$row['campus']] = $row['count'];
}

// 工单总数 + 校区分布
$stmt = $pdo->query("SELECT campus, COUNT(*) as count FROM fy_workorders GROUP BY campus");
foreach ($stmt->fetchAll() as $row) {
    $counts['workorders'] += $row['count'];
    $campus_workorder[$row['campus']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>仪表盘 - 云上飞扬管理后台</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="p-4">

  <h3 class="mb-4">仪表盘</h3>

  <div class="row g-4">
    <!-- 概况信息 -->
    <div class="col-md-4">
      <div class="card text-center shadow">
        <div class="card-body">
          <h5 class="card-title">在职技术员总数</h5>
          <p class="display-6"><?= $counts['technicians'] ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-center shadow">
        <div class="card-body">
          <h5 class="card-title">已激活用户总数</h5>
          <p class="display-6"><?= $counts['actived_users'] ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-center shadow">
        <div class="card-body">
          <h5 class="card-title">工单总数</h5>
          <p class="display-6"><?= $counts['workorders'] ?></p>
        </div>
      </div>
    </div>
  </div>

  <!-- 三个主要分布的图表 -->
  <div class="row mt-5 g-4">
  <div class="col-md-4">
    <h5 class="text-center">技术员校区分布</h5>
    <div class="chart-container">
      <canvas id="techChart"></canvas>
    </div>
  </div>
  <div class="col-md-4">
    <h5 class="text-center">用户校区分布</h5>
    <div class="chart-container">
      <canvas id="userChart"></canvas>
    </div>
  </div>
  <div class="col-md-4">
    <h5 class="text-center">工单校区分布</h5>
    <div class="chart-container">
      <canvas id="workChart"></canvas>
    </div>
  </div>
</div>

  <!-- 跳转入口 -->
  <div class="mt-5">
    <h4>功能入口</h4>
    <div class="row row-cols-1 row-cols-md-3 g-3">
      <?php if ($role === 'super'): ?>
        <div class="col"><a href="#" onclick="parent.location.hash = '#users'; return false;" class="btn btn-outline-primary w-100">用户管理</a></div>
        <div class="col"><a href="#" onclick="parent.location.hash = '#orders'; return false;" class="btn btn-outline-primary w-100">工单管理</a></div>
      <?php endif; ?>
      <?php if (in_array($role, ['super', 'active'])): ?>
        <div class="col"><a href="#" onclick="parent.location.hash = '#quickactive'; return false;" class="btn btn-outline-success w-100">快速激活</a></div>
        <div class="col"><a href="#" onclick="parent.location.hash = '#techtransfer'; return false;" class="btn btn-outline-success w-100">技术员录入</a></div>
        <div class="col"><a href="#" onclick="parent.location.hash = '#statistics'; return false;" class="btn btn-outline-success w-100">接单统计</a></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 图表脚本 -->
  <script>
    const techChart = new Chart(document.getElementById('techChart'), {
      type: 'pie',
      data: {
        labels: ['江安', '望江', '华西'],
        datasets: [{
          data: [<?= $campus_tech['江安'] ?>, <?= $campus_tech['望江'] ?>, <?= $campus_tech['华西'] ?>],
          backgroundColor: ['#36a2eb', '#4bc0c0', '#ffcd56']
        }]
      }
    });

    const userChart = new Chart(document.getElementById('userChart'), {
      type: 'pie',
      data: {
        labels: ['江安', '望江', '华西'],
        datasets: [{
          data: [<?= $campus_user['江安'] ?>, <?= $campus_user['望江'] ?>, <?= $campus_user['华西'] ?>],
          backgroundColor: ['#ff6384', '#9966ff', '#ff9f40']
        }]
      }
    });

    const workChart = new Chart(document.getElementById('workChart'), {
      type: 'pie',
      data: {
        labels: ['江安', '望江', '华西', '线下'],
        datasets: [{
          data: [<?= $campus_workorder['江安'] ?? 0 ?>, <?= $campus_workorder['望江'] ?? 0 ?>, <?= $campus_workorder['华西'] ?? 0 ?>, <?= $campus_workorder['线下'] ?? 0 ?>],
          backgroundColor: ['#007bff', '#28a745', '#fd7e14', '#6c757d']
        }]
      }
    });
  </script>

</body>
</html>
