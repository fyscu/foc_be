<?php
session_start();
if (!isset($_SESSION['export_data'])) {
    exit('无可导出数据');
}

$data = $_SESSION['export_data'];
$title = $_SESSION['export_title'];
header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename='.$title.'.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', '技术员昵称', '手机号', '接单量', '志愿时长', '排名']);
foreach ($data as $row) {
    fputcsv($output, [$row['id'], $row['nickname'], $row['phone'], $row['order_count'], $row['order_count'] * 2, $row['rank']]);
}
fclose($output);
exit;
