<?php
class Sms {
    private $smsConfig;
    private $debug; // 是否开启调试日志

    public function __construct($config, $debug = false)
    {
        $this->smsConfig = $config['sms'];
        $this->debug = $debug;
    }

    /**
     * 发送短信验证码 (使用阿里云号码认证服务接口 SendSmsVerifyCode)
     * * @param string $templateKey 模板标识，从配置中取得阿里云短信模板CODE
     * @param string $phoneNumber 目标手机号码 (仅支持单号码)
     * @param array  $templateParams 模板变量数组。
     * 场景1(自定义验证码): ['code' => '123456', 'min' => '5']
     * 场景2(阿里云生成):   ['code' => '##code##', 'min' => '5']
     * @return array 返回API的JSON解析结果或调试信息
     */
    public function sendSms($templateKey, $phoneNumber, $templateParams = [])
    {
        $this->log("开始发送短信验证码 (Interface: SendSmsVerifyCode)");

        // 从配置中获取必要信息
        $accessKeyId     = $this->smsConfig['access_key_id'];
        $accessKeySecret = $this->smsConfig['access_key_secret'];
        $signName        = $this->smsConfig['sign_name'];
        // 注意：新接口要求使用“赠送签名”和“赠送模板”，请确保配置文件中的 TemplateCode 是号码认证控制台里的
        $templateCode    = $this->smsConfig['template_ids'][$templateKey];

        $this->log("配置参数获取成功");

        // [变更] 阿里云号码认证服务接口地址
        $apiUrl = 'https://dypnsapi.aliyuncs.com/';

        // 构造公共请求参数
        $params = [
            'AccessKeyId'      => $accessKeyId,
            'Action'           => 'SendSmsVerifyCode', // [变更] 接口名称
            'Format'           => 'JSON',
            'PhoneNumber'      => $phoneNumber,        // [变更] 参数名为单数
            'RegionId'         => 'cn-hangzhou',
            'SignName'         => $signName,
            'SchemeName'       => '默认方案',           // [新增] 方案名称，不填默认
            'SignatureMethod'  => 'HMAC-SHA1',
            'SignatureNonce'   => uniqid(),
            'SignatureVersion' => '1.0',
            'TemplateCode'     => $templateCode,
            'Timestamp'        => gmdate("Y-m-d\TH:i:s\Z"),
            'Version'          => '2017-05-25'
        ];

        // [新增] 只有在调试模式下，才要求API返回验证码明文，方便后端调试
        if ($this->debug) {
            $params['ReturnVerifyCode'] = 'true';
        }

        // [变更] TemplateParam 是必填项，且必须是 JSON 字符串
        if (empty($templateParams)) {
            // 如果为空，给一个空JSON对象，防止API报错（视具体模板要求而定）
            $params['TemplateParam'] = '{}';
        } else {
            $params['TemplateParam'] = json_encode($templateParams, JSON_UNESCAPED_UNICODE);
        }

        // 检查是否需要阿里云自动生成验证码 (即参数中包含 ##code##)
        // 如果包含 ##code##，通常需要指定 CodeType 等参数，这里给一个默认值
        if (strpos($params['TemplateParam'], '##code##') !== false) {
             $params['CodeType'] = 1; // 1: 纯数字, 2: 纯大写... 根据需求调整
             $params['CodeLength'] = 4; // 默认长度
        }

        $this->log("请求参数构造完成：" . json_encode($params));

        // 1. 对参数进行字典排序
        ksort($params);

        // 2. 构造规范化请求字符串
        $canonicalizedQueryString = "";
        foreach ($params as $key => $value) {
            $canonicalizedQueryString .= '&' . $this->percentEncode($key) . '=' . $this->percentEncode($value);
        }
        $canonicalizedQueryString = ltrim($canonicalizedQueryString, '&');

        // 3. 构造待签名字符串
        $stringToSign = 'GET&' . $this->percentEncode('/') . '&' . $this->percentEncode($canonicalizedQueryString);

        // 4. 计算签名
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret . "&", true));

        // 5. 构造最终请求 URL
        $finalUrl = $apiUrl . '?Signature=' . $this->percentEncode($signature) . '&' . $canonicalizedQueryString;
        $this->log("最终请求 URL：" . $finalUrl);

        // 使用 cURL 发起 GET 请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $finalUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        // SSL 验证建议开启，如果本地开发环境证书有问题，可临时设为 false
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $result = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            $this->log("cURL 错误：" . $error);
            curl_close($ch);
            return ['error' => $error];
        }
        curl_close($ch);
        $this->log("API 返回结果：" . $result);

        // 将返回的 JSON 数据转换为数组后返回
        return json_decode($result, true);
    }

    /**
     * 对字符串进行特殊的 URL 编码处理，符合阿里云签名要求
     * @param string $str
     * @return string
     */
    private function percentEncode($str)
    {
        $res = urlencode($str);
        $res = preg_replace("/\+/", "%20", $res);
        $res = preg_replace("/\*/", "%2A", $res);
        $res = preg_replace("/%7E/", "~", $res);
        return $res;
    }

    /**
     * 记录调试信息到日志文件
     * @param string $message
     */
    private function log($message)
    {
        if ($this->debug) {
            // 建议使用 error_log 或专门的 Logger 类
            // 如果是在 Linux 环境下调试，注意文件权限
            error_log("[Sms Debug] " . $message);
        }
    }
}