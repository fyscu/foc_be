<?php
// 配置
$json_file = 'fy.json'; // 替换为你的 JSON 文件路径
$db_host = 'mysql'; // 数据库地址
$db_user = 'foc'; // 数据库用户名
$db_pass = 'CizD6PChBKTYbbxS'; // 数据库密码
$db_name = 'foc'; // 数据库名称
$table_name = 'fy_users'; // 表名

// 读取 JSON 文件
$json_data = file_get_contents($json_file);
$users = json_decode($json_data, true);

if (!$users) {
    die("JSON 文件解析失败，请检查文件格式是否正确。\n");
}

// 数据库连接
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

// 检查连接
if ($mysqli->connect_error) {
    die("数据库连接失败: " . $mysqli->connect_error . "\n");
}

// 更新记录
foreach ($users as $user) {
    // 构建 SQL 更新语句
    $updates = [];
    foreach ($user as $column => $value) {
        if ($value === '0000-00-00 00:00:00') {
            $updates[] = "`$column` = NULL";
        } elseif ($value === null) {
            $updates[] = "`$column` = NULL";
        } else {
            $updates[] = "`$column` = '" . $mysqli->real_escape_string($value) . "'";
        }
    }
    $update_str = implode(", ", $updates);

    $sql = "UPDATE `$table_name` SET $update_str WHERE `id` = " . intval($user['id']);

    // 执行更新
    if ($mysqli->query($sql) === TRUE) {
        echo "记录更新成功: ID = " . $user['id'] . "\n";
        echo "<br>";
    } else {
        echo "更新失败: ID = " . $user['id'] . " - 错误: " . $mysqli->error . "\n";
        echo "<br>";
    }
}

// 关闭连接
$mysqli->close();

echo "所有记录更新完成！\n";
?>