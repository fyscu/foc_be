<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
$config = include('../../config.php');
include('../../db.php');
require '../../utils/email.php';
require '../../utils/sms.php';
include('../../utils/gets.php');
include('../../utils/token.php');
include('../../utils/headercheck.php'); //新逻辑下这里也需要Bearer验证了
include('../../utils/qiniu_avatar.php');

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
            'data' => $result['data']
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