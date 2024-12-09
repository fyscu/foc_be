<?php
header("Content-Type: application/json; charset=UTF-8");
require '../../db.php';
$config = include('../config.php');

$codeStmt = $pdo->prepare("SELECT data FROM fy_confs WHERE name = ?");
$codeStmt->execute(['RepairSmsCode']);
$thecode = $codeStmt->fetch(PDO::FETCH_ASSOC);
$correctCode = json_decode($thecode['data'],TRUE)[0];
$exptime = json_decode($thecode['data'],TRUE)[1]; 
$rexp = date('Y-m-d H:i:s', $exptime);
echo json_encode([
                'success' => true,
                'code' => $correctCode,
                'expires' => $rexp
            ]);
