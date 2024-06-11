<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
$config = include('../../config.php');
include('../../db.php');
include('../../utils/gets.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');

$stmt = $pdo->prepare("SELECT openid FROM fy_users");
$stmt->execute();
$openids = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(["success" => true, "data" => $openids]);
?>
