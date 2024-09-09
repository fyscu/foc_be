<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0); // 提前结束响应，处理 OPTIONS 预检请求
}

include('../../db.php');
include('../../utils/hungarian.php');
include('../../utils/json2xlsx.php');
include('../../utils/gets.php');

$json = file_get_contents('php://input');
$data = json_decode($json, true);
// 更新报名数据的分配状态
function updateRegistration($registrationId, $assigned, $assignPosition, $assignTime) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE fy_repair_registrations SET assigned = ?, assignposition = ?, assigntime = ? WHERE id = ?");
    $stmt->execute([$assigned, $assignPosition, $assignTime, $registrationId]);
}

// 从前端获取时间段（Array形式，我在文档里会写例子）
// $timeSlots = json_decode($data['time_slots'], true);
$timeSlots = $data['time_slots'];
$activity_id = $data['activity_id'];

if (!$timeSlots || !$activity_id) {
    echo json_encode(["success" => false]);
    exit;
}

// 获取大修活动报名数据
$registrations = getRepairRegistrations($activity_id);
// 初始化结果数组
$assignmentResults = [];
// 初始化已分配人员列表
$assignedUsers = [];
// 点位名称
$positions = ['1号位', '4号位', '5号位', '6号位', '机动位', '现场行政'];

// 每个时间段独立处理
foreach ($timeSlots as $timeSlotIndex => $timeSlot) {
    // 构造当前时间段的可用性矩阵
    $availableUsers = [];
    foreach ($registrations as $index => $registration) {
        if (!in_array($registration['id'], $assignedUsers) && !$registration['assigned']) {
            $availableSlots = explode(',', $registration['free_times']);
            if (in_array($timeSlot, $availableSlots)) {
                $availableUsers[] = $index;
            }
        }
    }

    if (empty($availableUsers)) {
        continue;
    }

    // 初始化点位分配
    $departmentSlots = array_fill_keys($positions, null);

    // 优先处理现场行政
    foreach ($availableUsers as $index) {
        $registration = $registrations[$index];
        $departments = explode(',', $registration['departments']);
        if (in_array('行政部', $departments) && $departmentSlots['现场行政'] === null) {
            $departmentSlots['现场行政'] = $index;
            $assignedUsers[] = $registration['id'];
            updateRegistration($registration['id'], 1, '现场行政', $timeSlot);
            break; // 一旦找到现场行政的人，就跳出循环
        }
    }

    // 分配其他点位
    foreach ($availableUsers as $index) {
        $registration = $registrations[$index];
        if (!in_array($registration['id'], $assignedUsers)) {
            // 优先处理第一个和最后一个时间段的男干事
            if (($timeSlotIndex === 0 || $timeSlotIndex === count($timeSlots) - 1) && $registration['gender'] !== '男') {
                continue;
            }
            foreach (['1号位', '4号位', '5号位', '6号位', '机动位'] as $department) {
                if ($departmentSlots[$department] === null) {
                    $departmentSlots[$department] = $index;
                    $assignedUsers[] = $registration['id'];
                    updateRegistration($registration['id'], 1, $department, $timeSlot);
                    break; // 一旦分配到一个点位，就跳出循环
                }
            }
        }
    }

    // 生成当前时间段的分配结果
    foreach ($departmentSlots as $department => $index) {
        if ($index !== null && isset($registrations[$index])) {
            $assignmentResults[] = [
                "name" => $registrations[$index]['name'],
                "time_slot" => $timeSlot,
                "department" => $department
            ];
        }
    }
}

echo json_encode([
    "success" => true,
    "assignment" => $assignmentResults,
    "ifxlsx" => true,
    "xlsxurl" => json2xlsx($assignmentResults)
]);    
?>