<?php
class SubscribeNotifier
{
    private $appId;
    private $appSecret;
    private $tokenUrl = 'https://api.weixin.qq.com/cgi-bin/token';
    private $sendUrl = 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=';

    public function __construct($appId, $appSecret)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
    }

    private function getAccessToken()
    {
        $url = "{$this->tokenUrl}?grant_type=client_credential&appid={$this->appId}&secret={$this->appSecret}";
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    public function send($openid, $templateId, $page, $msgData)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'message' => 'at_crashed'];
        }

        $dataPayload = [];
        foreach ($msgData as $key => $value) {
            $dataPayload[$key] = ['value' => $value];
        }

        $payload = [
            'touser' => $openid,
            'template_id' => $templateId,
            'page' => $page,
            'data' => $dataPayload,
            'miniprogram_state' => 'formal' // also in 'developer'、'trial'
        ];

        $url = $this->sendUrl . $accessToken;
        $options = [
            'http' => [
                'header'  => "Content-type: application/json",
                'method'  => 'POST',
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]
        ];
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $res = json_decode($result, true);

        if (isset($res['errcode']) && $res['errcode'] == 0) {
            return ['success' => true, 'message' => '消息发送成功'];
        } else {
            return ['success' => false, 'message' => '发送失败', 'error' => $res];
        }
    }
}
