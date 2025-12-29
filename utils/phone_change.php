<?php

require 'sms.php';

function requestPhoneChange($userinfo, $newPhone) {
    global $pdo, $config;

    // 检查新手机号是否和当前手机号相同
    if ($userinfo['phone'] === $newPhone) {
        return ['status' => 'same_phone'];
    }

    // 将新手机号存入缓冲区
    $stmt = $pdo->prepare("UPDATE fy_users SET temp_phone = ? WHERE openid = ?");
    $stmt->execute([$newPhone, $userinfo['openid']]);

    // 生成验证码（6位随机数字）
    $verificationCode = rand(100000, 999999);

    // 存入验证码到数据库
    $stmt = $pdo->prepare("UPDATE fy_users SET verification_code = ? WHERE openid = ?");
    $stmt->execute([$verificationCode, $userinfo['openid']]);

    // 发送验证码到新手机号
    // $sms = new Sms($config);
    // $result = $sms->sendSms('changephone', $newPhone, [$verificationCode]);

    $sms = new Sms($config);
    $templateKey = 'changephone'; 
    $phoneNumber = $newphone; 
    $templateParams = ['code' => $verificationCode];
    $result = $sms->sendSms($templateKey, $phoneNumber, $templateParams);

    if ($result === true) {
        return ['status' => 'verification_sent'];
    } else {
        return ['status' => 'sms_failed', 'error' => $result];
    }
}

function verifyPhoneChange($userinfo, $inputCode) {
    global $pdo;

    // 从数据库中获取验证码和缓冲区手机号
    $stmt = $pdo->prepare("SELECT temp_phone, verification_code FROM fy_users WHERE openid = ?");
    $stmt->execute([$userinfo['openid']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 验证输入的验证码是否正确
    if ($user['verification_code'] == $inputCode) {
        // 更新手机号
        $stmt = $pdo->prepare("UPDATE fy_users SET phone = ?, temp_phone = NULL, verification_code = NULL WHERE openid = ?");
        $stmt->execute([$user['temp_phone'], $userinfo['openid']]);

        return ['status' => 'phone_updated'];
    } else {
        return ['status' => 'verification_failed'];
    }
}
?>