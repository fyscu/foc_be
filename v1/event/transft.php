<?php
// 数据库配置
$host = "mysql";
$dbname = "foc";
$username = "foc";
$password = "CizD6PChBKTYbbxS";

try {
    // 连接数据库
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // CSV 文件路径
    $csvFilePath = 'file.csv';

    // 打开 CSV 文件
    if (($handle = fopen($csvFilePath, "r")) !== false) {
        // 跳过标题行
        fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== false) {
            // 从 CSV 获取数据
            $name = trim($data[0]); // 姓名
            $free_times = implode(',', array_map('trim', explode(',', trim($data[1], '"')))); // 报名时间，直接拼接成字符串


            // 固定字段值
            $departments = "技术员部";
            $user_id = 100003;
            $activity_id = 3;
            $gender = '男';

            // 插入数据
            $sql = "INSERT INTO fy_repair_registrations (activity_id, user_id, name, gender, departments, free_times, assigned) 
                    VALUES (:activity_id, :user_id, :name, :gender, :departments, :free_times, 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':activity_id' => $activity_id,
                ':user_id' => $user_id,
                ':name' => $name,
                ':gender' => $gender,
                ':departments' => $departments,
                ':free_times' => $free_times,
            ]);

            echo "导入成功: $name\n";
        }

        fclose($handle);
    } else {
        echo "无法打开文件：$csvFilePath\n";
    }
} catch (PDOException $e) {
    echo "数据库错误: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}







