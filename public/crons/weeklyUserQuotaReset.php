<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

include('../../db.php');
$config = include('../../config.php');

$json = file_get_contents('php://input');
$data = json_decode($json, true);
$weeklyset = $config['info']['weeklyset'];
$manopenid = $data['openid'] ?? null;
$manquota = $data['quota'] ?? 5;

$actioncode = $_GET['token'] ?? null;
if ($actioncode !== $config['info']['actioncode']) {
    echo json_encode([
        'success' => false,
        'message' => "Bad adtion code"
    ]);
    exit;
}

if ($manopenid) {
    $stmt = $pdo->prepare("UPDATE fy_users SET available = :weeklyset WHERE openid = :openid AND role = 'user' AND available != :weeklyset");
    $stmt->execute([
        ':weeklyset' => $weeklyset,
        ':openid' => $manopenid
    ]);

    echo json_encode([
        'success' => true,
        'message' => "User (openid: $manopenid) 's weeklyquota was set to: $weeklyset"
    ]);
} else {
    $stmt = $pdo->prepare("UPDATE fy_users SET available = :weeklyset WHERE role = 'user' AND available != :weeklyset");
    $stmt->execute([
        ':weeklyset' => $weeklyset
    ]);

    echo json_encode([
        'success' => true,
        'message' => "All user's weeklyquota was set to: $weeklyset"
    ]);
}
?>