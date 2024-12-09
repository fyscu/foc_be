<?php
require 'PHPExcel/Classes/PHPExcel.php';

function processExcel($filePath) {
    $objPHPExcel = PHPExcel_IOFactory::load($filePath);
    $sheet = $objPHPExcel->getActiveSheet();
    $highestRow = $sheet->getHighestRow();

    $departmentsList = ['研发' => '研发部', '维修' => '维修部', '设计' => '设计部', '行政' => '行政部', '流媒' => '流媒部'];
    $processedData = [];

    // 假设第一行是表头，从第二行开始读取
    for ($row = 2; $row <= $highestRow; $row++) {
        $name = $sheet->getCell("D$row")->getValue(); // 假设姓名在第 1 列
        $departmentRaw = $sheet->getCell("E$row")->getValue(); // 假设部门在第 2 列
        $freeTimeRaw = $sheet->getCell("F$row")->getValue(); // 假设空闲时间在第 3 列

        // 处理部门列
        $departments = processDepartments($departmentRaw, $departmentsList);

        // 处理空闲时间列
        $freeTimes = processFreeTimes($freeTimeRaw);

        // 组装数据
        $processedData[] = [
            'activity_id' => 4,
            'user_id' => 100003,
            'name' => $name,
            'gender' => '男',
            'departments' => $departments,
            'free_times' => $freeTimes,
        ];
    }

    // 执行数据插入
    insertData($processedData);
}

// 处理部门数据
function processDepartments($departmentRaw, $departmentsList) {
    $foundDepartments = [];
    foreach ($departmentsList as $keyword => $standardDepartment) {
        if (strpos($departmentRaw, $keyword) !== false) {
            $foundDepartments[] = $standardDepartment;
        }
    }
    return implode(',', $foundDepartments);
}

function processFreeTimes($freeTimeRaw) {
    // 替换不规范的符号，去掉空格，规范格式
    $freeTimeRaw = str_replace(['::', '--', ' '], [':', '-', ''], $freeTimeRaw);
    $timeSegments = explode(',', $freeTimeRaw);
    $cleanedTimes = [];
    
    foreach ($timeSegments as $segment) {
        // 使用正则表达式匹配时间段，并格式化为标准 HH:MM-HH:MM 格式
        if (preg_match('/(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})/', $segment, $matches)) {
            $startHour = sprintf("%02d", $matches[1]);
            $startMinute = sprintf("%02d", $matches[2]);
            $endHour = sprintf("%02d", $matches[3]);
            $endMinute = sprintf("%02d", $matches[4]);

            $cleanedTimes[] = "$startHour:$startMinute-$endHour:$endMinute";
        }
    }

    return implode(',', $cleanedTimes);
}

// 插入数据到数据库
function insertData($processedData) {
    $dsn = "mysql:host=localhost;dbname=foc4;charset=utf8mb4";
    $pdo = new PDO($dsn, 'foc4', 'foc@fyscu2024');

    foreach ($processedData as $data) {
        $sql = "INSERT INTO fy_repair_registrations (activity_id, user_id, name, gender, departments, free_times) 
                VALUES (:activity_id, :user_id, :name, :gender, :departments, :free_times)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':activity_id' => $data['activity_id'],
            ':user_id' => $data['user_id'],
            ':name' => $data['name'],
            ':gender' => $data['gender'],
            ':departments' => $data['departments'],
            ':free_times' => $data['free_times'],
        ]);
    }
}

// 调用处理函数
processExcel('file.xlsx');
?>