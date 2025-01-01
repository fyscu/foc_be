<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config = include('../../config.php');
include('../../db.php');
require '../../utils/email.php';
require '../../utils/sms.php';
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/gets.php');

$year = isset($_GET['year']) ? (int)$_GET['year'] : null;

function convertSecondsToHours($seconds) {
    return round($seconds / 3600, 1);
}

function getTechnicianYearlyInfo($uid, $year = null) {
    global $pdo;

    // 如果未指定年份，则默认使用当前年份
    $year = $year ?? date('Y');

    // 获取该年的时间范围
    $startOfYear = $year . '-01-01 00:00:00';
    $endOfYear = $year . '-12-31 23:59:59';

    // 查询该技术员指定年度第一单的完成时间
    $sqlFirstOrder = "SELECT completion_time FROM fy_workorders 
                      WHERE assigned_technician_id = :uid AND repair_status = 'done' 
                      AND completion_time BETWEEN :startOfYear AND :endOfYear 
                      ORDER BY completion_time ASC LIMIT 1";
    $stmtFirstOrder = $pdo->prepare($sqlFirstOrder);
    $stmtFirstOrder->execute(['uid' => $uid, 'startOfYear' => $startOfYear, 'endOfYear' => $endOfYear]);
    $firstOrder = $stmtFirstOrder->fetch(PDO::FETCH_ASSOC);

    // 查询该技术员指定年度最后一单的完成时间
    $sqlLastOrder = "SELECT completion_time FROM fy_workorders 
                     WHERE assigned_technician_id = :uid AND repair_status = 'done' 
                     AND completion_time BETWEEN :startOfYear AND :endOfYear 
                     ORDER BY completion_time DESC LIMIT 1";
    $stmtLastOrder = $pdo->prepare($sqlLastOrder);
    $stmtLastOrder->execute(['uid' => $uid, 'startOfYear' => $startOfYear, 'endOfYear' => $endOfYear]);
    $lastOrder = $stmtLastOrder->fetch(PDO::FETCH_ASSOC);

    // 查询该技术员指定年度完成的总单数
    $sqlTotalOrders = "SELECT COUNT(*) AS total FROM fy_workorders 
                       WHERE assigned_technician_id = :uid AND repair_status = 'done' 
                       AND completion_time BETWEEN :startOfYear AND :endOfYear";
    $stmtTotalOrders = $pdo->prepare($sqlTotalOrders);
    $stmtTotalOrders->execute(['uid' => $uid, 'startOfYear' => $startOfYear, 'endOfYear' => $endOfYear]);
    $totalOrders = $stmtTotalOrders->fetch(PDO::FETCH_ASSOC);

    // 查询该技术员指定年度最短完成用时
    $sqlShortestTime = "SELECT TIMESTAMPDIFF(SECOND, assigned_time, completion_time) AS duration 
                        FROM fy_workorders 
                        WHERE assigned_technician_id = :uid AND repair_status = 'done' 
                        AND completion_time BETWEEN :startOfYear AND :endOfYear 
                        ORDER BY duration ASC LIMIT 1";
    $stmtShortestTime = $pdo->prepare($sqlShortestTime);
    $stmtShortestTime->execute(['uid' => $uid, 'startOfYear' => $startOfYear, 'endOfYear' => $endOfYear]);
    $shortestTime = $stmtShortestTime->fetch(PDO::FETCH_ASSOC);

    // 查询该技术员指定年度最长完成用时
    $sqlLongestTime = "SELECT TIMESTAMPDIFF(SECOND, assigned_time, completion_time) AS duration 
                       FROM fy_workorders 
                       WHERE assigned_technician_id = :uid AND repair_status = 'done' 
                       AND completion_time BETWEEN :startOfYear AND :endOfYear 
                       ORDER BY duration DESC LIMIT 1";
    $stmtLongestTime = $pdo->prepare($sqlLongestTime);
    $stmtLongestTime->execute(['uid' => $uid, 'startOfYear' => $startOfYear, 'endOfYear' => $endOfYear]);
    $longestTime = $stmtLongestTime->fetch(PDO::FETCH_ASSOC);

    // 查询该技术员指定年度所有完成工单的总用时
    $sqlTotalDuration = "SELECT SUM(TIMESTAMPDIFF(SECOND, assigned_time, completion_time)) AS total_duration 
                         FROM fy_workorders 
                         WHERE assigned_technician_id = :uid AND repair_status = 'done' 
                         AND completion_time BETWEEN :startOfYear AND :endOfYear";
    $stmtTotalDuration = $pdo->prepare($sqlTotalDuration);
    $stmtTotalDuration->execute(['uid' => $uid, 'startOfYear' => $startOfYear, 'endOfYear' => $endOfYear]);
    $totalDuration = $stmtTotalDuration->fetch(PDO::FETCH_ASSOC);

    // 转换秒数为智能单位
    $convertTime = function($seconds) {
        if ($seconds < 60) {
            return $seconds . '秒';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            return $minutes . '分' . $remainingSeconds . '秒';
        } elseif ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            $remainingMinutes = floor(($seconds % 3600) / 60);
            $remainingSeconds = $seconds % 60;
            return $hours . '小时' . $remainingMinutes . '分' . $remainingSeconds . '秒';
        } else {
            $days = floor($seconds / 86400);
            $remainingHours = floor(($seconds % 86400) / 3600);
            $remainingMinutes = floor(($seconds % 3600) / 60);
            $remainingSeconds = $seconds % 60;
            if ($remainingSeconds == 0){
                return $days . '天' . $remainingHours . '小时' . $remainingMinutes . '分';
            } else {
                return $days . '天' . $remainingHours . '小时' . $remainingMinutes . '分' . $remainingSeconds . '秒';
            }
             
        }
    };

    

    return [
        'success' => true,
        'year' => $year,
        'data' => [
            'first_time' => $firstOrder['completion_time'] ?? null,
            'last_time' => $lastOrder['completion_time'] ?? null,
            'total_orders' => $totalOrders['total'] ?? 0,
            'shortest_time' => $convertTime($shortestTime['duration'] ?? 0),
            'longest_time' => $convertTime($longestTime['duration'] ?? 0),
            'total_time' => $convertTime($totalDuration['total_duration'] ?? 0),
            'total_time_in_hour' => convertSecondsToHours($totalDuration['total_duration'] ?? 0)."小时(志愿不以此计算)"
        ]
    ];
}

echo json_encode(getTechnicianYearlyInfo($userinfo['id'], $year = null));
?>