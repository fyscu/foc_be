<?php
require '../../utils/email.php';
require '../../utils/sms.php';

$config = include('../../config.php');

// 创建 Email 类实例
$email = new Email($config);

// 发送邮件示例
$sent = $email->sendEmail('wjlfish@qq.com', '邮件主题', '邮件内容');

// 创建 Sms 类实例
$sms = new Sms($config);

// 发送短信示例
//$result = $sms->sendSms('assign_to_user', '18009511952', ['11','11','11']);

// 输出发送结果
if ($result === true) {
    echo "短信发送成功！";
} else {
    echo "短信发送失败，原因：$result";
}
if ($sent === true) {
    echo "邮件发送成功！";
} else {
    echo "邮件发送失败，原因：$sent";
}
?>
