<?php
require '../../db.php';
$config = include('../config.php');

$exptime = time();
$exptime += 60;
$code = rand(1000, 9999);
$toset = json_encode([$code,$exptime]);
$stmt = $pdo->prepare("UPDATE fy_confs SET data = ? WHERE name = ?");
$stmt->execute([$toset,'RepairSmsCode']);
echo $code;
echo "<br>";




