<?php
class WeChat {
    private $appId;
    private $appSecret;

    // 构造函数，初始化微信配置
    public function __construct($config) {
        $this->appId = $config['app_id'];
        $this->appSecret = $config['app_secret'];
    }

    // 获取用户信息
    public function getUserInfo($code) {
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$this->appId}&secret={$this->appSecret}&js_code={$code}&grant_type=authorization_code";
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        // 返回用户信息
        return $data;
    }
}
?>
