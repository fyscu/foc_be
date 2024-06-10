<?php

require 'PHPExcel/Classes/PHPExcel.php';

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function json2xlsx($data) {
//这个傻逼PHPExcel已经在php7.4以上被淘汰了，但是我实在是懒得用新的，所以一个个改了调用方式为8.0可以识别的。
//项目utils/PHPExcel是被高版本适配化过的，实测可以在php8.0环境中以json生成xlsx，但是其他功能没有实验，不确保可用
//如果你也想魔改，只需将你所需功能代码中的{0}改成[0]这种类型，并且删掉函数请求中预设的变量值就行

$filename = generateRandomString(10).".xlsx";
$time_slots = array();
$departments = array();

// 获取所有的时间段和部门
foreach ($data as $item) {
    if (!in_array($item['time_slot'], $time_slots)) {
        $time_slots[] = $item['time_slot'];
    }
    if (!in_array($item['department'], $departments)) {
        $departments[] = $item['department'];
    }
}

// 根据时间段和部门生成表格
$objPHPExcel = new PHPExcel();
$objPHPExcel->setActiveSheetIndex(0);
$sheet = $objPHPExcel->getActiveSheet();

// 设置表头
$sheet->mergeCells('A1:G1'); // 合并单元格
$sheet->setCellValue('A1', '点位分配表');
//$sheet->getStyle('A1:G1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
$sheet->setCellValue('A2', '时间段');
foreach ($departments as $key => $department) {
    $col = chr(66 + $key); // 从'B'开始
    $sheet->setCellValue($col . '2', $department);
}

// 填充表格
foreach ($time_slots as $key => $time_slot) {
    $row = $key + 3; // 从第二行开始
    $sheet->setCellValue('A' . $row, $time_slot);
    
    foreach ($departments as $dkey => $department) {
        $col = chr(66 + $dkey); // 从'B'开始
        foreach ($data as $item) {
            if ($item['time_slot'] == $time_slot && $item['department'] == $department) {
                $sheet->setCellValue($col . $row, $item['name']);
                break; // 找到对应的姓名后跳出循环
            }
        }
    }
}
// 设置自动列宽
foreach(range('A','G') as $columnID){
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// 设置整个表格居中对齐
$lastColumn = $sheet->getHighestColumn();
$lastRow = $sheet->getHighestRow();
$sheet->getStyle('A1:' . $lastColumn . $lastRow)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

// 保存文件
$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
$route = "lists/".$filename;
$objWriter->save($route);
return "https://".$_SERVER['HTTP_HOST']."/v1/event/".$route;
}