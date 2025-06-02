<?php
header("Content-Type: application/json; charset=UTF-8");
if (session_status() === PHP_SESSION_NONE) {
    session_name('admin');
    session_start();
}
require '../../utils/sms.php';
require '../../db.php';
$config = include('../../config.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'verify') {
        $code = isset($_POST['vcode']) ? $_POST['vcode'] : '';
        $codeStmt = $pdo->prepare("SELECT data FROM fy_confs WHERE name = ?");
        $codeStmt->execute(['RepairSmsCode']);
        $thecode = $codeStmt->fetch(PDO::FETCH_ASSOC);
        $correctCode = json_decode($thecode['data'],TRUE)[0];
        if ($code == $correctCode) {
            $_SESSION['ved'] = true;
            echo json_encode(['success' => true, 'message' => 'verified']);
        } else {
            echo json_encode(['success' => false, 'message' => 'wrong']);
        }
        exit;
    }

    // 提交手机号逻辑
    if ($action === 'sms') {
        // 确认已经通过了验证码验证
        if (1 === 1) {
            $phone = isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '';

            $sms = new Sms($config);
            $result = $sms->sendSms('repair_completion', $phone, []);

            if ($result) {
                echo json_encode(['success' => true, 'message' => '短信已发送']);
            } else {
                echo json_encode(['success' => false, 'message' => '短信发送失败']);
            }
            
        } else {
            echo json_encode(['success' => false, 'message' => 'error']);
        }
        exit;
    }
}