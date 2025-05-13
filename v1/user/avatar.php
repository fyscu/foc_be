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
require '../../utils/email.php';
require '../../utils/sms.php';
include('../../utils/gets.php');
include('../../utils/token.php');
include('../../utils/headercheck.php'); //新逻辑下这里也需要Bearer验证了
include('../../utils/qiniu_avatar.php');
include('../../utils/qiniu_url.php');

$accessKey = $config['qiniu']['accessKey'];
$secretKey = $config['qiniu']['secretKey'];
$bucket = $config['qiniu']['bucket'];
$domain = $config['qiniu']['domain'];
$uploadUrl = $config['qiniu']['uploadUrl'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $image = $_FILES['file'];
    $rawresult = uploadImage($image, $accessKey, $secretKey, $bucket, $domain, $uploadUrl);
    $result = json_decode($rawresult,TRUE);
    if ($result['success']){
        echo json_encode([
            'success' => true,
            'data' => generatePrivateLink($result['data']),
            'rawdata' => $result['data'],
        ]);       
        //echo $result['data'];
    } else {
        echo json_encode([
            'success' => false,
            'data' => "七牛云上传错误"
        ]);
    }

} else {
    echo json_encode([
        'success' => false,
        'data' => "本地上传错误"
    ]);
}
?>