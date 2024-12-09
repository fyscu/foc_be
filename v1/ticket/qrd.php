<?php
header("Content-Type: application/json; charset=UTF-8");
include('../../utils/qrcode.php');
$workorderId = "20240000216";
$workorderHash = "0258fda3c8197bb0f9fc3902de51d88d74ed066d56ffeeb213e793818f64951e";
$content = $qrcodeData = "[give];$workorderId;$workorderHash";;
$qrImageBase64 = generateQrCodeBase64($content);

// 打印二维码Base64数据
echo $qrImageBase64;