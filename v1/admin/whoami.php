<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config = include('../../config.php');
include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/admincheck.php');

echo json_encode([
    'success' => true,
    'username' => $adminAccount['username'],
    'role' => $adminAccount['role'],
    'uid' => (int)$userinfo['id'],
    'nickname' => $userinfo['nickname']
], JSON_UNESCAPED_UNICODE);
