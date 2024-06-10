<?php
// 加载配置文件
$config = include('config.php');

try {
    // 创建PDO实例并设置错误模式为异常
    $pdo = new PDO(
        'mysql:host=' . $config['db']['host'] . ';dbname=' . $config['db']['dbname'],
        $config['db']['username'],
        $config['db']['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // 数据库连接失败时输出错误信息
    die('数据库连接失败: ' . $e->getMessage());
}
?>
