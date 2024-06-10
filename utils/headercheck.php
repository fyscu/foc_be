<?php
$headers = getallheaders();

if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized for no Authorization']);
    exit;
}

$authHeader = $headers['Authorization'];
list($bearer, $token) = explode(' ', $authHeader);
if ($bearer != 'Bearer' || !$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized for no Bearer']);
    exit;
}

$userinfo = verifyToken($token);

if($userinfo == "user_not_found"){
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized for unvaild token']);
    exit;
}elseif ($userinfo == "token_expired") {
    http_response_code(401);
    echo json_encode(['error' => $userinfo]);
    exit;
} else {
    $openid = $userinfo['openid'];
}
?>