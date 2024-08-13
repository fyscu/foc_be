<?php
$headers = getallheaders();
//这里加
if (!isset($headers['Authorization'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        echo json_encode(['error' => 'Unauthorized for no Authorization']);
        exit;
    }
    else {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized for no Authorization']);
    exit;
    }
}

$authHeader = $headers['Authorization'];
list($bearer, $token) = explode(' ', $authHeader);
if ($bearer != 'Bearer' || !$token) {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        echo json_encode(['error' => 'Unauthorized for no Bearer']);
        exit;
    }
    else {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized for no Bearer']);
        exit;
    }
}

$userinfo = verifyToken($token);

if($userinfo == "user_not_found"){
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        echo json_encode(['error' => 'Unauthorized for unvaild token']);
        exit;
    }
    else {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized for unvaild token']);
        exit;
    }
    
}elseif ($userinfo == "token_expired") {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        echo json_encode(['error' => $userinfo]);
        exit;
    }
    else {
        http_response_code(401);
        echo json_encode(['error' => $userinfo]);
        exit;
    }
    
} else {
    $openid = $userinfo['openid'];
}
?>