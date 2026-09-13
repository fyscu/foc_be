<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config = include('../../config.php');
include('../../db.php');
require '../../utils/email.php';
require '../../utils/sms.php';
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/gets.php');

$data = json_decode(file_get_contents('php://input'), true);
$workOrderId = is_array($data) ? (int)($data['order_id'] ?? 0) : 0;

if ($workOrderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'status' => 'invalid request']);
    exit;
}

$technicianId = 0;
$userId = 0;
$statusChanged = false;

try {
    $authinfo = getUserByAccessToken($token);
    if (!is_array($authinfo) || !isset($authinfo['id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'status' => 'invalid token']);
        exit;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT assigned_technician_id, user_id, repair_status " .
        "FROM fy_workorders WHERE id = ? FOR UPDATE"
    );
    $stmt->execute([$workOrderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'status' => 'ticket not found']);
        exit;
    }

    $technicianId = (int)($row['assigned_technician_id'] ?? 0);
    $userId = (int)$row['user_id'];
    $actorId = (int)$authinfo['id'];

    if ($actorId !== $technicianId && $actorId !== $userId) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'status' => 'Permission denied']);
        exit;
    }

    if ($row['repair_status'] !== 'Done') {
        $stmt = $pdo->prepare(
            "UPDATE fy_workorders " .
            "SET repair_status = 'Done', completion_time = NOW() WHERE id = ?"
        );
        $stmt->execute([$workOrderId]);
        $statusChanged = true;

        if ($technicianId > 0) {
            $stmt = $pdo->prepare(
                "UPDATE fy_users SET available = 1, last_time = NOW() WHERE id = ?"
            );
            $stmt->execute([$technicianId]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log(
        '[ticket.complete] order=' . $workOrderId . ' database failure: ' .
        get_class($e) . ': ' . $e->getMessage()
    );
    http_response_code(500);
    echo json_encode(['success' => false, 'status' => 'unknown_error']);
    exit;
}

echo json_encode(['success' => true, 'status' => 'ticket completed']);

if (!$statusChanged) {
    exit;
}

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
ignore_user_abort(true);

try {
    $user = getUserById($userId);
    if (!$user) {
        error_log('[ticket.complete] order=' . $workOrderId . ' notification skipped: user missing');
        exit;
    }

    if (!empty($user['phone'])) {
        $sms = new Sms($config);
        $smsResult = $sms->sendSms('completion', $user['phone'], []);
        if (!is_array($smsResult) || isset($smsResult['error'])) {
            error_log('[ticket.complete] order=' . $workOrderId . ' SMS delivery failed');
        }
    }

    if (!empty($user['email'])) {
        $notification = new Email($config);
        if (!$notification->sendEmail(
            $user['email'],
            '报修工单已完成',
            "您的报修工单 单号：$workOrderId 已由技术员维修完成，请及时取回"
        )) {
            error_log('[ticket.complete] order=' . $workOrderId . ' email delivery failed');
        }
    }
} catch (Throwable $e) {
    error_log(
        '[ticket.complete] order=' . $workOrderId . ' notification failure: ' .
        get_class($e) . ': ' . $e->getMessage()
    );
}
