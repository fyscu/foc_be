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
     * 发送短信，并记录调试日志
     * @param string $templateKey 模板标识，从配置中取得阿里云短信模板CODE
     * @param string $phoneNumber 目标手机号码
     * @param array  $templateParams 模板变量数组，如 [pa1, pa2, ...]
     * @return array 返回API的JSON解析结果或调试信息
     */
    public function sendSms($templateKey, $phoneNumber, $templateParams = [])
    {
        $this->log("开始发送短信");

        // 从配置中获取必要信息
        // 配置文件中的字段为 secret_id 和 secret_key
        // 为避免名称不一致导致读取失败，这里使用这两个字段
        $accessKeyId     = $this->smsConfig['secret_id'];
        $accessKeySecret = $this->smsConfig['secret_key'];
        $signName        = $this->smsConfig['sign_name'];
        $templateCode    = $this->smsConfig['template_ids'][$templateKey];

        $this->log("配置参数获取成功");

        // 阿里云短信接口地址
        $apiUrl = 'https://dysmsapi.aliyuncs.com/';

        // 构造公共请求参数
        $params = [
            'AccessKeyId'      => $accessKeyId,
            'Action'           => 'SendSms',
            'Format'           => 'JSON',
            'PhoneNumbers'     => $phoneNumber,
            'RegionId'         => 'cn-hangzhou',
            'SignName'         => $signName,
            'SignatureMethod'  => 'HMAC-SHA1',
            'SignatureNonce'   => uniqid(), // 保证唯一性
            'SignatureVersion' => '1.0',
            'TemplateCode'     => $templateCode,
            'Timestamp'        => gmdate("Y-m-d\TH:i:s\Z"),
            'Version'          => '2017-05-25'
        ];

        // 如果模板参数不为空，转换为JSON字符串
        if (!empty($templateParams)) {
            $params['TemplateParam'] = json_encode($templateParams, JSON_UNESCAPED_UNICODE);
        }
        $this->log("请求参数构造完成：" . json_encode($params));

        // 1. 对参数进行字典排序
        ksort($params);
        $this->log("参数排序完成：" . json_encode($params));

        // 2. 构造规范化请求字符串，对每个键和值进行自定义 URL 编码
        $canonicalizedQueryString = "";
        foreach ($params as $key => $value) {
            $canonicalizedQueryString .= '&' . $this->percentEncode($key) . '=' . $this->percentEncode($value);
        }
        $canonicalizedQueryString = ltrim($canonicalizedQueryString, '&');
        $this->log("规范化请求字符串：" . $canonicalizedQueryString);

        // 3. 构造待签名字符串：HTTPMethod + "&" + percentEncode("/") + "&" + percentEncode(规范化请求字符串)
        $stringToSign = 'GET&' . $this->percentEncode('/') . '&' . $this->percentEncode($canonicalizedQueryString);
        $this->log("待签名字符串：" . $stringToSign);

        // 4. 使用 HMAC-SHA1 算法计算签名，并进行 base64 编码
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret . "&", true));
        $this->log("生成签名：" . $signature);

        // 5. 构造最终请求 URL，注意避免重复编码
        $finalUrl = $apiUrl . '?Signature=' . $this->percentEncode($signature) . '&' . $canonicalizedQueryString;
        $this->log("最终请求 URL：" . $finalUrl);

        // 使用 cURL 发起 GET 请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $finalUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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
            // 你可以根据需要调整日志文件路径或使用其他日志库
            error_log("[Sms Debug] " . $message);
        }
    }
}

