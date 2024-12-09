<?php
//本util是针对本项目特殊化适配，不可直接取用于其他项目
//这里的$object变量，可以是纯object，也可以是完整的访问url。函数前端已有判断逻辑
require '../../utils/qiniusdk/autoload.php';

use Qiniu\Auth;

function generatePrivateLink($object) {
    global $config;
    $bucketDomain = $config['qiniu']['domain'];
    $accessKey = $config['qiniu']['accessKey'];
    $secretKey = $config['qiniu']['secretKey'];
    if (filter_var($object, FILTER_VALIDATE_URL)) {
        $parsedUrl = parse_url($object);
        $object = ltrim($parsedUrl['path'], '/');
    } else {
        $object = $object;
    }

    $auth = new Auth($accessKey, $secretKey);

    $baseUrl = "{$bucketDomain}/{$object}";
    $privateUrl = $auth->privateDownloadUrl($baseUrl, 3600);

    return $privateUrl;
}



