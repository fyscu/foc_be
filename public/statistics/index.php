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

$results = [];
$startDate = '';
$endDate = '';

if (isLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';

    $sql = "
        SELECT 
            u.id AS technician_id,
            u.nickname,
            u.phone,
            COUNT(w.id) AS order_count
        FROM fy_workorders w
        INNER JOIN fy_users u ON w.assigned_technician_id = u.id
        WHERE w.repair_status = 'Done'
        AND w.create_time BETWEEN :start AND :end
        GROUP BY w.assigned_technician_id
        ORDER BY order_count DESC, technician_id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':start' => $startDate . " 00:00:00",
        ':end' => $endDate . " 23:59:59",
    ]);

    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 计算跳跃排名
    $results = [];
    $lastCount = null;
    $rank = 0;
    $actualRank = 0;
    foreach ($raw as $row) {
        $actualRank++;
        if ($lastCount !== $row['order_count']) {
            $rank = $actualRank;
        }
        $results[] = [
            'id' => $row['technician_id'],
            'nickname' => $row['nickname'],
            'phone' => $row['phone'],
            'order_count' => $row['order_count'],
            'rank' => $rank,
        ];
        $lastCount = $row['order_count'];
        $_SESSION['export_title'] = '技术员接单统计 - '.$startDate . ' 至 ' . $endDate;
        $_SESSION['export_data'] = $results;
    }
}
?>

<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>技术员接单统计</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
</head>
<body>

<nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">技术员接单统计</a>
        
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

<main class="container">
    
    <?php if (isLoggedIn()): ?>
        <div class="bg-light p-5 rounded">
            <h2>按时间统计接单量</h2>
            <form method="post" class="row g-3 justify-content-center">
                <div class="col-auto">
                    <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required>
                </div>
                <div class="col-auto">
                    <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($endDate) ?>" required>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">生成统计</button>
                </div>
            </form>

            <?php if (!empty($results)): ?>
                <div class="mt-4">
                    <a href="to_csv.php" class="btn btn-success">导出为CSV</a>
                <table class="table table-bordered table-hover mt-4">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>技术员昵称</th>
                            <th>接单量</th>
                            <th>排名</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['nickname']) ?></td>
                                <td><?= $row['order_count'] ?></td>
                                <td><?= $row['rank'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                <div class="alert alert-warning mt-4">所选日期内无已完成工单记录。</div>
            <?php endif; ?>
        </div>
    <?php else: ?>
         <div class="bg-light p-5 rounded">
            <div class="alert alert-danger">登录失效，请刷新页面</div>           
        </div>
    <?php endif; ?>
</main>

<script src="assets/bootstrap.bundle.min.js"></script>
<footer class="mt-5 text-center text-muted small">
  <hr>
  Developed with ❤️ by <strong>初音过去</strong> in 2025<br>
</footer>
</body>
</html>
