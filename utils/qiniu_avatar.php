<?php
require '../../utils/qiniusdk/autoload.php';

use Qiniu\Auth;
use Qiniu\Storage\UploadManager;

function uploadImage($file, $accessKey, $secretKey, $bucket, $domain, $uploadUrl) { //文件本体，ak，桶名称，七牛云存储区绑定的域名，区上传url
    if ($file['error'] === UPLOAD_ERR_OK) {
        $imagePath = $file['tmp_name'];
        $fileInfo = pathinfo($file['name']);
        $extension = isset($fileInfo['extension']) ? $fileInfo['extension'] : '';
        $uuid = generateUUID();
        $imageName = "avatar/".$uuid . '.' . $extension;

        $auth = new Auth($accessKey, $secretKey);
        $token = $auth->uploadToken($bucket);

        $uploadMgr = new UploadManager();
        list($ret, $err) = $uploadMgr->putFile($token, $imageName, $imagePath);

        if ($err !== null) {
            return json_encode([
                'success' => false,
                'data' => $err->message()
            ]);
        } else {
            $imageUrl = $domain . '/' . $ret['key'];
            return json_encode([
                'success' => true,
                'data' => $imageUrl
            ]);
        }
    } else {
        return json_encode([
            'success' => false,
            'data' => "未知错误"
        ]);
    }
}

function generateUploadToken($accessKey, $secretKey, $bucket) {
    $deadline = time() + 3600; // 1小时有效期
    $putPolicy = [
        'scope' => $bucket,
        'deadline' => $deadline
    ];
    $putPolicyJson = json_encode($putPolicy);
    $encodedPutPolicy = base64_encode($putPolicyJson);
    $sign = hash_hmac('sha1', $encodedPutPolicy, $secretKey, true);
    $encodedSign = base64_encode($sign);
    $uploadToken = $accessKey . ':' . $encodedSign . ':' . $encodedPutPolicy;
    return $uploadToken;
}

function generateUUID() {
    $data = '';
    for ($i = 0; $i < 16; $i++) {
        $data .= chr(mt_rand(0, 255));
    }

    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return sprintf('%s-%s-%s-%s-%s',
        bin2hex(substr($data, 0, 4)),
        bin2hex(substr($data, 4, 2)),
        bin2hex(substr($data, 6, 2)),
        bin2hex(substr($data, 8, 2)),
        bin2hex(substr($data, 10, 6))
    );
}
?>
