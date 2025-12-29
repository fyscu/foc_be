<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只支持GET请求']);
    exit;
}

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // 获取今日日期
    $today = date('Y-m-d');
    $thisMonth = date('Y-m');
    
    // 总体统计
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sent,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as success_count,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
            SUM(CASE WHEN DATE(sent_at) = ? THEN 1 ELSE 0 END) as today_total,
            SUM(CASE WHEN DATE(sent_at) = ? AND status = 'sent' THEN 1 ELSE 0 END) as today_success,
            SUM(CASE WHEN DATE(sent_at) = ? AND status = 'failed' THEN 1 ELSE 0 END) as today_failed,
            SUM(CASE WHEN DATE(sent_at) LIKE ? THEN 1 ELSE 0 END) as month_total,
            SUM(CASE WHEN DATE(sent_at) LIKE ? AND status = 'sent' THEN 1 ELSE 0 END) as month_success
        FROM fyd_sms_logs 
        WHERE sent_at IS NOT NULL
    ");
    
    $stmt->execute([$today, $today, $today, $thisMonth . '%', $thisMonth . '%']);
    $stats = $stmt->fetch();
    
    // 按模板类型统计
    $stmt = $pdo->prepare("
        SELECT 
            template_type,
            COUNT(*) as count,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as success_count
        FROM fyd_sms_logs 
        WHERE sent_at IS NOT NULL
        GROUP BY template_type
        ORDER BY count DESC
    ");
    $stmt->execute();
    $templateStats = $stmt->fetchAll();
    
    // 最近7天发送趋势
    $stmt = $pdo->prepare("
        SELECT 
            DATE(sent_at) as send_date,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as success
        FROM fyd_sms_logs 
        WHERE sent_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(sent_at)
        ORDER BY send_date DESC
    ");
    $stmt->execute();
    $trendData = $stmt->fetchAll();
    
    // 发送失败原因统计
    $stmt = $pdo->prepare("
        SELECT 
            error_message,
            COUNT(*) as count
        FROM fyd_sms_logs 
        WHERE status = 'failed' AND error_message IS NOT NULL
        GROUP BY error_message
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $errorStats = $stmt->fetchAll();
    
    // 计算成功率
    $totalSent = intval($stats['total_sent']);
    $successCount = intval($stats['success_count']);
    $successRate = $totalSent > 0 ? round(($successCount / $totalSent) * 100, 2) : 0;
    
    $todayTotal = intval($stats['today_total']);
    $todaySuccess = intval($stats['today_success']);
    $todaySuccessRate = $todayTotal > 0 ? round(($todaySuccess / $todayTotal) * 100, 2) : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'overview' => [
                'total_sent' => $totalSent,
                'success_count' => $successCount,
                'failed_count' => intval($stats['failed_count']),
                'success_rate' => $successRate,
                'today_total' => $todayTotal,
                'today_success' => $todaySuccess,
                'today_failed' => intval($stats['today_failed']),
                'today_success_rate' => $todaySuccessRate,
                'month_total' => intval($stats['month_total']),
                'month_success' => intval($stats['month_success'])
            ],
            'template_stats' => $templateStats,
            'trend_data' => $trendData,
            'error_stats' => $errorStats
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '获取短信统计失败: ' . $e->getMessage()
    ]);
}
?>