<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

include('../../db.php');
include('../../utils/json2xlsx.php');
include('../../utils/gets.php');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$timeSlots = $data['time_slots'];
$activity_id = $data['activity_id'];

if (!$timeSlots || !$activity_id) {
    echo json_encode(["success" => false]);
    exit;
}

// 获取技术员数据
$registrations = getRepairRegistrations($activity_id);

// 初始化数据
$assignedUsers = [];
$assignmentResults = [];
$timeSlotAssignments = array_fill_keys($timeSlots, []);

// 统计每个时间段的可用技术员
$timeSlotAvailableTechnicians = [];
foreach ($timeSlots as $timeSlot) {
    $timeSlotAvailableTechnicians[$timeSlot] = [];
    foreach ($registrations as $technician) {
        if (!in_array($technician['id'], $assignedUsers) && in_array($timeSlot, explode(',', $technician['free_times']))) {
            $timeSlotAvailableTechnicians[$timeSlot][] = $technician;
        }
    }
}

// 初步分配：选择时间段内可用的技术员
foreach ($timeSlotAvailableTechnicians as $timeSlot => $technicians) {
    usort($technicians, function ($a, $b) {
        return count(explode(',', $a['free_times'])) <=> count(explode(',', $b['free_times']));
    });

    foreach ($technicians as $technician) {
        if (!in_array($technician['id'], $assignedUsers)) {
            $timeSlotAssignments[$timeSlot][] = $technician;
            $assignedUsers[] = $technician['id'];
        }
    }
}

// 平衡调整
function balanceAssignments(&$timeSlotAssignments, &$assignedUsers, &$timeSlotAvailableTechnicians) {
    $maxIterations = 5;
    for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
        // 获取每个时间段的人数
        $counts = array_map('count', $timeSlotAssignments);
        $maxCount = max($counts);
        $minCount = min($counts);
        if ($maxCount - $minCount <= 1) {
            break; // 已经平衡，退出
        }

        // 找到人数最多和最少的时间段
        $maxSlot = array_search($maxCount, $counts);
        $minSlot = array_search($minCount, $counts);

        // 从人数最多的时间段移动一个技术员到人数最少的时间段
        foreach ($timeSlotAssignments[$maxSlot] as $index => $technician) {
            if (in_array($minSlot, explode(',', $technician['free_times']))) {
                $timeSlotAssignments[$minSlot][] = $technician;
                unset($timeSlotAssignments[$maxSlot][$index]);
                break;
            }
        }
    }
}

// 调整使其平衡
balanceAssignments($timeSlotAssignments, $assignedUsers, $timeSlotAvailableTechnicians);

// 生成最终的分配结果
foreach ($timeSlotAssignments as $timeSlot => $technicians) {
    $technicianIndex = 1;
    foreach ($technicians as $technician) {
        $assignmentResults[] = [
            "name" => $technician['name'],
            "time_slot" => $timeSlot,
            "department" => "技术员" . $technicianIndex
        ];
        // 更新数据库状态
        $stmt = $pdo->prepare("UPDATE fy_repair_registrations SET assigned = 1, assignposition = ?, assigntime = ? WHERE id = ?");
        $stmt->execute(["技术员" . $technicianIndex, $timeSlot, $technician['id']]);

        $technicianIndex++;
    }
}

// 生成表格并输出
echo json_encode([
    "success" => true,
    "assignment" => $assignmentResults,
    "ifxlsx" => true,
    "xlsxurl" => json2xlsx($assignmentResults)
]);
?>