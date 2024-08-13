<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
$config = include('../../config.php');
include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/gets.php');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$ticket_id = $data['tid'];
$ticket = getTicketById($ticket_id);

$response = [];

if ($ticket) {
    $has_permission = false;

    if ($userinfo['is_admin']) {
        $has_permission = true;
    } elseif ($userinfo['id'] === $ticket['user_id']) {
        $has_permission = true;
    } elseif ($userinfo['role'] === 'technician' && $userinfo['id'] === $ticket['assigned_technician_id']) {
        $has_permission = true;
    }

    if ($has_permission) {
        $updateFields = [];
        $updateValues = [];
        $changedFields = [];

        foreach ($data as $key => $value) {
            if (!empty($value) && $key != 'id' && isset($ticket[$key])) {
                if ($ticket[$key] != $value) {
                    $updateFields[] = "$key = :$key";
                    $updateValues[":$key"] = $value;
                    $changedFields[$key] = $value;
                }
            }
        }

        if (count($updateFields) > 0) {
            $updateSql = "UPDATE tickets SET " . implode(", ", $updateFields) . " WHERE id = :id";
            $updateValues[':id'] = $ticket_id;

            $stmt = $pdo->prepare($updateSql);
            $stmt->execute($updateValues);

            $response = [
                'success' => true,
                'changedFields' => $changedFields
            ];
        } else {
            $response = [
                'success' => true,
                'changedFields' => []
            ];
        }
    } else {
        $response = [
            'success' => false,
            'message' => 'Permission denied',
            'changedFields' => []
        ];
    }
} else {
    $response = [
        'success' => false,
        'message' => 'Ticket not found',
        'changedFields' => []
    ];
}

echo json_encode($response);
?>