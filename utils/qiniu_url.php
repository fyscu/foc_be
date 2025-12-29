<?php
//本util是针对本项目特殊化适配，不可直接取用于其他项目
//这里的$object变量，可以是纯object，也可以是完整的访问url。函数已有判断逻辑
require '../../utils/qiniusdk/autoload.php';

use Qiniu\Auth;

// 默认域名（从配置中读取）
$rawbucketDomain = $config['qiniu']['domain'];

/**
 * 生成私有链接
 * @param string $object  对象名或完整URL
 * @param string|null $bucketDomain 可选，自定义域名（不传则用$config中的默认域名）
 * @return string
 */
function generatePrivateLink($object, $bucketDomain = null) {
    if (empty($object)) return '';

    global $config, $rawbucketDomain;

    // 如果没有传自定义域名，使用默认域名
    if (empty($bucketDomain)) {
        $bucketDomain = $rawbucketDomain;
    }

    $accessKey = $config['qiniu']['accessKey'];
    $secretKey = $config['qiniu']['secretKey'];
    $rawobject = $object;

    // 判断传入的是完整URL还是纯object
    if (filter_var($object, FILTER_VALIDATE_URL)) {
        $parsedUrl = parse_url($object);
        $object = ltrim($parsedUrl['path'], '/');
    }

    $auth = new Auth($accessKey, $secretKey);

    // 拼接基础URL（使用传入的域名或默认域名）
    $baseUrl = rtrim($bucketDomain, '/') . '/' . $object;
    $privateUrl = $auth->privateDownloadUrl($baseUrl, 3600);

    // 特殊处理豆瓣图
    if (preg_match('/doubanio/', $rawobject)) {
        $privateUrl = $rawobject;
    }

    if (empty($object)) {
        $privateUrl = '';
    }

    return $privateUrl;
}
