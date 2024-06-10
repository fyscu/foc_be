<?php
class Sms {
    private $smsConfig;

    public function __construct($config)
    {
        $this->smsConfig = $config['sms'];
    }

    private function generateSignature($params)
    {
        ksort($params);
        $str = "GETsms.tencentcloudapi.com/?";
        foreach ($params as $key => $value) {
            $str .= "$key=$value&";
        }
        $str = substr($str, 0, -1);
        return base64_encode(hash_hmac('sha1', $str, $this->smsConfig['secret_key'], true));
    }

    public function sendSms($templateKey, $phoneNumber, $templateParams = [])
    {
        $endpoint = "https://sms.tencentcloudapi.com/";
        $params = [
            "Action" => "SendSms",
            "Version" => "2021-01-11",
            "Region" => "ap-guangzhou",
            "SmsSdkAppId" => $this->smsConfig['sms_sdk_appid'],
            "SignName" => $this->smsConfig['sign_name'],
            "TemplateId" => $this->smsConfig['template_ids'][$templateKey],
            "PhoneNumberSet.0" => "+86$phoneNumber",
            "Timestamp" => time(),
            "Nonce" => rand(),
            "SecretId" => $this->smsConfig['secret_id']
        ];

        foreach ($templateParams as $index => $param) {
            $params["TemplateParamSet.$index"] = $param;
        }

        $params["Signature"] = $this->generateSignature($params);

        $queryString = http_build_query($params);
        $url = "$endpoint?$queryString";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        return $this->handleResponse($response);
    }

    private function handleResponse($response)
    {
        $responseArray = json_decode($response, true);

        if (isset($responseArray['Response']['SendStatusSet'][0]['Code']) &&
            $responseArray['Response']['SendStatusSet'][0]['Code'] === 'Ok') {
            return true;
        } else {
            $errorMessage = isset($responseArray['Response']['Error']['Message']) ?
                $responseArray['Response']['Error']['Message'] : "未知错误";
            return $errorMessage;
        }
    }
}
?>
