<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只支持POST请求']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('无效的JSON数据');
    }
    
    $orderId = $input['order_id'] ?? null;
    $phone = $input['phone'] ?? null;
    $message = $input['message'] ?? null;
    $templateType = $input['template_type'] ?? 'custom';
    
    // 验证必填字段
    if (!$orderId || !$phone || !$message) {
        throw new Exception('缺少必填字段');
    }
    
    // 验证手机号格式
    if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
        throw new Exception('手机号格式不正确');
    }
    
    // 验证消息长度
    if (mb_strlen($message, 'UTF-8') > 500) {
        throw new Exception('短信内容过长，最多500字符');
    }
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // 检查订单是否存在
    $stmt = $pdo->prepare("SELECT id, order_number, customer_name FROM fyd_repair_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception('订单不存在');
    }
    
    // 这里应该调用实际的短信发送接口
    // 由于是演示系统，我们模拟短信发送过程
    $smsResult = sendSMSMessage($phone, $message);
    
    if ($smsResult['success']) {
        // 记录短信发送日志
        $stmt = $pdo->prepare("
            INSERT INTO fyd_sms_logs (order_id, phone, message, template_type, status, sent_at, response_data) 
            VALUES (?, ?, ?, ?, 'sent', NOW(), ?)
        ");
        $stmt->execute([
            $orderId,
            $phone,
            $message,
            $templateType,
            json_encode($smsResult)
        ]);
        
        $smsLogId = $pdo->lastInsertId();
        
        // 记录操作日志
        $stmt = $pdo->prepare("
            INSERT INTO fyd_order_logs (order_id, action, details, created_at) 
            VALUES (?, 'sms_sent', ?, NOW())
        ");
        $stmt->execute([
            $orderId,
            json_encode([
                'phone' => $phone,
                'template_type' => $templateType,
                'message_length' => mb_strlen($message, 'UTF-8'),
                'sms_log_id' => $smsLogId
            ])
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => '短信发送成功',
            'data' => [
                'sms_log_id' => $smsLogId,
                'order_number' => $order['order_number'],
                'customer_name' => $order['customer_name'],
                'phone' => $phone,
                'sent_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } else {
        // 记录发送失败日志
        $stmt = $pdo->prepare("
            INSERT INTO fyd_sms_logs (order_id, phone, message, template_type, status, sent_at, error_message) 
            VALUES (?, ?, ?, ?, 'failed', NOW(), ?)
        ");
        $stmt->execute([
            $orderId,
            $phone,
            $message,
            $templateType,
            $smsResult['error'] ?? '未知错误'
        ]);
        
        throw new Exception('短信发送失败：' . ($smsResult['error'] ?? '未知错误'));
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * 发送短信消息（模拟实现）
 * 在实际项目中，这里应该调用真实的短信服务提供商API
 */
function sendSMSMessage($phone, $message) {
    // 模拟短信发送过程
    // 在实际项目中，这里应该调用阿里云、腾讯云等短信服务
    
    // 模拟网络延迟
    usleep(500000); // 0.5秒延迟
    
    // 模拟发送成功率（95%成功率）
    $success = (rand(1, 100) <= 95);
    
    if ($success) {
        return [
            'success' => true,
            'message_id' => 'SMS_' . time() . '_' . rand(1000, 9999),
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s')
        ];
    } else {
        return [
            'success' => false,
            'error' => '网络异常，请稍后重试',
            'error_code' => 'NETWORK_ERROR'
        ];
    }
}

/**
 * 实际短信发送实现示例（阿里云短信服务）
 * 需要安装阿里云SDK：composer require alibabacloud/dysmsapi-20170525
 */
/*
function sendSMSMessageReal($phone, $message, $templateCode = null, $templateParam = null) {
    try {
        // 阿里云短信配置
        $accessKeyId = 'your_access_key_id';
        $accessKeySecret = 'your_access_key_secret';
        $signName = '飞扬俱乐部';
        
        $client = new \AlibabaCloud\SDK\Dysmsapi\V20170525\DysmsapiClient([
            'accessKeyId' => $accessKeyId,
            'accessKeySecret' => $accessKeySecret,
            'regionId' => 'cn-hangzhou'
        ]);
        
        $request = new \AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest([
            'phoneNumbers' => $phone,
            'signName' => $signName,
            'templateCode' => $templateCode ?: 'SMS_TEMPLATE_CODE',
            'templateParam' => $templateParam ? json_encode($templateParam) : json_encode(['content' => $message])
        ]);
        
        $response = $client->sendSms($request);
        
        if ($response->body->code === 'OK') {
            return [
                'success' => true,
                'message_id' => $response->body->bizId,
                'status' => 'sent'
            ];
        } else {
            return [
                'success' => false,
                'error' => $response->body->message,
                'error_code' => $response->body->code
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'error_code' => 'SDK_ERROR'
        ];
    }
}
*/
?>