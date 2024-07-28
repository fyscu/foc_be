<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include('../../db.php');
include('../../utils/hungarian.php');
include('../../utils/json2xlsx.php');
include('../../utils/gets.php');
//include('../../utils/token.php');
//include('../../utils/headercheck.php');

$json = file_get_contents('php://input');
$data = json_decode($json, true);
$user_id = $data['uid'];
$activity_id = $data['activity_id'];
$free_times_json = $data['free_times'];

// 解码 JSON 数据并转换为数组
$free_times_array = json_decode($free_times_json, true);

// 将free_times数组转换为逗号分隔的字符串
$free_times = implode(',', $free_times_array);

// 查找满足条件的记录
$stmt = $pdo->prepare("SELECT * FROM fy_repair_registrations WHERE user_id = ? AND activity_id = ?");
$stmt->execute([$user_id, $activity_id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if ($record) {
    // 提取原有信息
    $original_activity_id = $record['activity_id'];
    $original_assignposition = $record['assignposition'];
    $original_assigntime = $record['assigntime'];
    $original_assigned = $record['assigned'];
    $original_name = $record['name'];
    $original_department = $record['departments'];
    $original_gender = $record['gender'];

    // 删除找到的记录
    $stmt = $pdo->prepare("DELETE FROM fy_repair_registrations WHERE user_id = ? AND activity_id = ?");
    $stmt->execute([$user_id, $activity_id]);

    // 插入新记录
    $stmt = $pdo->prepare("INSERT INTO fy_repair_registrations (user_id, name, gender, departments, activity_id, free_times, assigned, assignposition, assigntime) VALUES (?, ?, ?, ?, ?, ?, 0, '', '')");
    $stmt->execute([$user_id, $original_name, $original_gender, $original_department, $activity_id, $free_times]);

    // 如果原来的assigned为1，进行后续操作
    if ($original_assigned == 1) {
        // 查找activity_id相同且assigned为0的记录
        $stmt = $pdo->prepare("SELECT * FROM fy_repair_registrations WHERE activity_id = ? AND assigned = 0 ORDER BY id");
        $stmt->execute([$original_activity_id]);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($candidates as $candidate) {
            // 检查free_times中是否包含原来的assigntime
            if (strpos($candidate['free_times'], $original_assigntime) !== false) {
                // 更新这个候选人的记录
                $stmt = $pdo->prepare("UPDATE fy_repair_registrations SET assigned = 1, assignposition = ?, assigntime = ? WHERE id = ?");
                $stmt->execute([$original_assignposition, $original_assigntime, $candidate['id']]);
                break;
            }
        }
        // 查找满足条件的记录
        $stmt = $pdo->prepare("SELECT * FROM fy_repair_registrations WHERE activity_id = ? AND assigned = 1");
        $stmt->execute([$activity_id]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 初始化结果数组
                $assignment_data = [];

        // 构建结果数组
        foreach ($assignments as $assignment) {
            $assignment_data[] = [
                "name" => $assignment['name'],
                "time_slot" => $assignment['assigntime'],
                "department" => $assignment['assignposition']
            ];
        }
        echo json_encode([
            "success" => true,
            "assignment" => $assignment_data,
            "ifxlsx" => true,
            "xlsxurl" => json2xlsx($assignment_data)
        ]);
    }
}