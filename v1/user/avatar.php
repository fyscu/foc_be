<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();

$avatarJsonResponseSent = false;

function avatarJsonResponse(array $payload) {
    global $avatarJsonResponseSent;

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        error_log('[avatar] Failed to encode response: ' . json_last_error_msg());
        $json = '{"success":false,"data":"服务器响应错误"}';
    }

    $avatarJsonResponseSent = true;
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo $json;
    exit;
}

register_shutdown_function(function () use (&$avatarJsonResponseSent) {
    if ($avatarJsonResponseSent) {
        return;
    }

    $output = ob_get_level() > 0 ? ob_get_contents() : '';
    if (is_string($output) && is_array(json_decode($output, true))) {
        return;
    }

    $error = error_get_last();
    if ($error !== null) {
        error_log(sprintf(
            '[avatar] Unhandled PHP error (%d) in %s:%d: %s',
            $error['type'],
            $error['file'],
            $error['line'],
            $error['message']
        ));
    } elseif ($output !== '') {
        error_log('[avatar] Discarded non-JSON response output');
    }

    if (ob_get_level() > 0) {
        ob_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(500);
    }
    echo '{"success":false,"data":"服务器上传错误"}';
});

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    avatarJsonResponse([
        'success' => true,
        'data' => null
    ]);
}

try {
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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
        avatarJsonResponse([
            'success' => false,
            'data' => "本地上传错误"
        ]);
    }

    $image = $_FILES['file'];
    $rawresult = uploadImage($image, $accessKey, $secretKey, $bucket, $domain, $uploadUrl);
    $result = is_string($rawresult) ? json_decode($rawresult, true) : null;

    if (!is_array($result) || !array_key_exists('success', $result)) {
        error_log('[avatar] Upload provider returned invalid JSON');
        avatarJsonResponse([
            'success' => false,
            'data' => "七牛云上传错误"
        ]);
    }

    if ($result['success']) {
        $rawdata = $result['data'] ?? '';
        if (!is_string($rawdata) || $rawdata === '') {
            error_log('[avatar] Upload provider returned an empty object URL');
            avatarJsonResponse([
                'success' => false,
                'data' => "七牛云上传错误"
            ]);
        }

        $signedUrl = generatePrivateLink($rawdata);
        if (!is_string($signedUrl) || $signedUrl === '') {
            error_log('[avatar] Failed to generate a private object URL');
            avatarJsonResponse([
                'success' => false,
                'data' => "七牛云上传错误"
            ]);
        }

        avatarJsonResponse([
            'success' => true,
            'data' => $signedUrl,
            'rawdata' => $rawdata,
        ]);
    }

    $providerMessage = isset($result['data']) && is_scalar($result['data'])
        ? (string) $result['data']
        : 'unknown provider error';
    error_log('[avatar] Upload provider error: ' . $providerMessage);
    avatarJsonResponse([
        'success' => false,
        'data' => "七牛云上传错误"
    ]);
} catch (Throwable $e) {
    error_log(sprintf(
        '[avatar] Upload failed with %s in %s:%d: %s',
        get_class($e),
        $e->getFile(),
        $e->getLine(),
        $e->getMessage()
    ));
    avatarJsonResponse([
        'success' => false,
        'data' => "七牛云上传错误"
    ]);
}
