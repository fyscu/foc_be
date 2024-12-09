<?php
$headers = getallheaders();

if (!isset($headers['Authorization'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        echo json_encode(['error' => 'Unauthorized for no Authorization']);
        exit;
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized for no Authorization']);
        exit;
    }
}

$authHeader = $headers['Authorization'];
$authParts = explode(' ', $authHeader);

// 检查 Authorization 头格式是否正确
if (count($authParts) !== 2 || $authParts[0] !== 'Bearer' || empty($authParts[1])) {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        echo json_encode(['error' => 'Unauthorized for no Bearer or invalid format']);
        exit;
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized for no Bearer or invalid format']);
        exit;
    }
}

$token = $authParts[1];
$userinfo = verifyToken($token);

if ($userinfo == "user_not_found") {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        echo json_encode(['error' => 'Unauthorized for invalid token']);
        exit;
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized for invalid token']);
        exit;
    }
} elseif ($userinfo == "token_expired") {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        echo json_encode(['error' => $userinfo]);
        exit;
    } else {
        http_response_code(401);
        echo json_encode(['error' => $userinfo]);
        exit;
    }
} else {
    $openid = $userinfo['openid'];
}
?>