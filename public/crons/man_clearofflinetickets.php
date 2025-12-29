<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

require_once '../../db.php';
$config = include('../../config.php');

$deleteSql = "DELETE FROM fy_workorders WHERE repair_status = 'Pending' AND campus = '线下'";
$deleteStmt = $pdo->prepare($deleteSql);
$deleteStmt->execute();

echo json_encode([
    'success'       => true,
    'deleted_count' => $deleteStmt->rowCount()
]);