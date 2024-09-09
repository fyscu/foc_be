<?php
require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Email {
    private $emailConfig;

    public function __construct($config) {
        $this->emailConfig = $config['email'];
    }

    public function sendEmail($email, $subject, $body) {
        $mail = new PHPMailer(true);
        try {
            // 服务器设置
            $mail->isSMTP();
            $mail->Host = $this->emailConfig['smtp_host']; // SMTP 服务器地址
            $mail->SMTPAuth = true;
            $mail->Username = $this->emailConfig['username']; // SMTP 用户名
            $mail->Password = $this->emailConfig['password']; // SMTP 密码
            $mail->SMTPSecure = 'ssl';
            $mail->CharSet = "UTF-8";
            $mail->Port = $this->emailConfig['smtp_port'];

            // 发件人
            $mail->setFrom($this->emailConfig['username'], '飞扬俱乐部');
            $mail->addAddress($email);

            // 内容
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;

            // 发送邮件
            $mail->send();
            return true; // 发送成功
        } catch (Exception $e) {
            return false; // 发送失败
        }
    }
}
?>