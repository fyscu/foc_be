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
$weeklyset = max(1, (int)($config['info']['weeklyset'] ?? 5));
include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');
require '../../utils/sms.php';
include('../../utils/gets.php');

$data = json_decode(file_get_contents('php://input'), true);
$ticketId = is_array($data) ? (int)($data['tid'] ?? 0) : 0;

if ($ticketId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid ticket id', 'changedFields' => []]);
    exit;
}

$ticket = getTicketById($ticketId);
if (!$ticket) {
    echo json_encode(['success' => false, 'message' => 'Ticket not found', 'changedFields' => []]);
    exit;
}

$actorId = (int)($userinfo['id'] ?? 0);
$userId = (int)$ticket['user_id'];
$technicianId = (int)($ticket['assigned_technician_id'] ?? 0);
$hasPermission = !empty($userinfo['is_admin'])
    || $actorId === $userId
    || (($userinfo['role'] ?? '') === 'technician' && $actorId === $technicianId);

if (!$hasPermission) {
    echo json_encode(['success' => false, 'message' => 'Permission denied', 'changedFields' => []]);
    exit;
}

$userAllowedFields = ['repair_status', 'complete_image_url'];
$adminExtraFields = [
    'user_phone', 'qq_number', 'device_type', 'model', 'computer_brand',
    'warranty_status', 'fault_type', 'campus', 'DuoCampus',
    'repair_description', 'repair_image_url',
    'assigned_technician_id', 'assigned_time', 'completion_time',
    'machine_purchase_date', 'user_nick', 'refused_times'
];
$allowedFields = !empty($userinfo['is_admin'])
    ? array_merge($userAllowedFields, $adminExtraFields)
    : $userAllowedFields;

$updateFields = [];
$updateValues = [];
$changedFields = [];

foreach ($data as $key => $value) {
    if ($value === null || $key === 'id' || !in_array($key, $allowedFields, true)) {
        continue;
    }
    if (!array_key_exists($key, $ticket) || $ticket[$key] == $value) {
        continue;
    }
    $updateFields[] = "$key = :$key";
    $updateValues[":$key"] = $value;
    $changedFields[$key] = $value;
}

$requestedStatus = isset($data['repair_status']) ? (string)$data['repair_status'] : null;

if (in_array($requestedStatus, ['UserConfirming', 'TechConfirming'], true)) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT repair_status FROM fy_workorders WHERE id = ? FOR UPDATE");
        $stmt->execute([$ticketId]);
        $locked = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$locked) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Ticket not found', 'changedFields' => []]);
            exit;
        }

        $currentStatus = $locked['repair_status'];
        $newStatus = $requestedStatus;
        if (
            ($currentStatus === 'UserConfirming' && $requestedStatus === 'TechConfirming')
            || ($currentStatus === 'TechConfirming' && $requestedStatus === 'UserConfirming')
        ) {
            $newStatus = 'Done';
        }

        $stmt = $pdo->prepare(
            "UPDATE fy_workorders SET repair_status = ?, " .
            "completion_time = CASE WHEN ? = 'Done' THEN NOW() ELSE completion_time END " .
            "WHERE id = ?"
        );
        $stmt->execute([$newStatus, $newStatus, $ticketId]);
        $pdo->commit();

        echo json_encode(['success' => true, 'changedFields' => ['repair_status' => $newStatus]]);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[ticket.set] ticket=' . $ticketId . ' confirmation failure: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error', 'changedFields' => []]);
        exit;
    }
}

$isClosing = in_array($requestedStatus, ['Canceled', 'Closed'], true)
    && array_key_exists('repair_status', $changedFields);
$notificationPhone = null;

try {
    if ($isClosing) {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT user_id, assigned_technician_id FROM fy_workorders WHERE id = ? FOR UPDATE"
        );
        $stmt->execute([$ticketId]);
        $locked = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$locked) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Ticket not found', 'changedFields' => []]);
            exit;
        }

        $userId = (int)$locked['user_id'];
        $technicianId = (int)($locked['assigned_technician_id'] ?? 0);
        if ($technicianId > 0) {
            $stmt = $pdo->prepare("UPDATE fy_users SET available = 1 WHERE id = ?");
            $stmt->execute([$technicianId]);
            $stmt = $pdo->prepare("SELECT phone FROM fy_users WHERE id = ?");
            $stmt->execute([$technicianId]);
            $technician = $stmt->fetch(PDO::FETCH_ASSOC);
            $notificationPhone = $technician['phone'] ?? null;
        }

        $stmt = $pdo->prepare(
            "UPDATE fy_users SET available = LEAST(available + 1, ?) " .
            "WHERE id = ? AND role = 'user'"
        );
        $stmt->execute([$weeklyset, $userId]);
    }

    if (count($updateFields) > 0) {
        $updateValues[':id'] = $ticketId;
        $stmt = $pdo->prepare(
            "UPDATE fy_workorders SET " . implode(', ', $updateFields) . " WHERE id = :id"
        );
        $stmt->execute($updateValues);
    }

    if ($isClosing) {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[ticket.set] ticket=' . $ticketId . ' update failure: ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error', 'changedFields' => []]);
    exit;
}

echo json_encode(['success' => true, 'changedFields' => $changedFields]);

if ($isClosing && !empty($notificationPhone)) {
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    ignore_user_abort(true);

    try {
        $sms = new Sms($config);
        $smsResult = $sms->sendSms('beclosed', $notificationPhone, []);
        if (!is_array($smsResult) || isset($smsResult['error'])) {
            error_log('[ticket.set] ticket=' . $ticketId . ' close SMS delivery failed');
        }
    } catch (Throwable $e) {
        error_log(
            '[ticket.set] ticket=' . $ticketId . ' close notification failure: ' .
            get_class($e) . ': ' . $e->getMessage()
        );
    }
}
