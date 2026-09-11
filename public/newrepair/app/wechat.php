<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function fdx_wechat_oauth_url(string $state): string
{
    $appid = (string) fdx_config('wechat.appid', '');
    $callbackBase = (string) fdx_config('wechat.oauth_callback_base', '');
    $scope = (string) fdx_config('wechat.scope', 'snsapi_base');

    $query = http_build_query([
        'appid' => $appid,
        'redirect_uri' => $callbackBase . '?route=wechat.callback',
        'response_type' => 'code',
        'scope' => $scope,
        'state' => $state,
    ]);

    return 'https://open.weixin.qq.com/connect/oauth2/authorize?' . $query . '#wechat_redirect';
}

function fdx_wechat_fetch_openid(string $code): array
{
    $appid = (string) fdx_config('wechat.appid', '');
    $secret = (string) fdx_config('wechat.secret', '');
    if ($appid === '' || $secret === '') {
        throw new RuntimeException('微信服务号配置不完整');
    }

    $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?' . http_build_query([
        'appid' => $appid,
        'secret' => $secret,
        'code' => $code,
        'grant_type' => 'authorization_code',
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);

    $raw = file_get_contents($url, false, $context);
    if ($raw === false) {
        throw new RuntimeException('微信授权请求失败');
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('微信授权返回格式异常');
    }

    if (isset($data['errcode'])) {
        throw new RuntimeException('微信授权失败：' . ($data['errmsg'] ?? $data['errcode']));
    }

    if (empty($data['openid'])) {
        throw new RuntimeException('微信授权未返回 openid');
    }

    return $data;
}

function fdx_find_bound_technician(string $appid, string $openid): ?array
{
    $stmt = fdx_db()->prepare("
        SELECT t.id, t.display_name, t.phone, t.campus, t.status
        FROM fdx_wechat_bindings b
        INNER JOIN fdx_technicians t ON t.id = b.technician_id
        WHERE b.appid = :appid
          AND b.openid = :openid
          AND b.status = 'active'
          AND t.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([
        ':appid' => $appid,
        ':openid' => $openid,
    ]);

    $technician = $stmt->fetch();
    return $technician ?: null;
}

