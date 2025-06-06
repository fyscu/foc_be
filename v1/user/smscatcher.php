<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0); // 提前结束响应，处理 OPTIONS 预检请求
}

$config = include('../../config.php');
include('../../db.php');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$address   = trim($data['address'] ?? '');
$body      = trim($data['body'] ?? '');
$timestamp = $data['timestamp'] ?? 0;

if ($address === '' || $body === '' || !is_numeric($timestamp)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid fields']);
    exit;
}

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

try {
    $stmt = $pdo->prepare("INSERT INTO fy_sms_catcher (address, body, timestamp, ip)
                           VALUES (:address, :body, :timestamp, :ip)");
    $stmt->execute([
        ':address'   => $address,
        ':body'      => $body,
        ':timestamp' => $timestamp,
        ':ip'        => $ip
    ]);

    echo json_encode(['success' => true, 'message' => 'SMS saved']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
}
