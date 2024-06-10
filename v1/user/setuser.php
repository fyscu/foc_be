<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
$config = include('../../config.php');
include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/gets.php');

$openid = $_POST['openid'];

$user = getUserByOpenid($openid);

if ($user) {
    $updateFields = [];
    $updateValues = [];
    $changedFields = [];
    foreach ($data as $key => $value) {
        if (!empty($value) && $key != 'id') {
            $updateFields[] = "$key = :$key";
            $updateValues[":$key"] = $value;
            $changedFields[$key] = $value;
        }
    }
    if (count($updateFields) > 0) {
        $updateSql = "UPDATE fy_users SET " . implode(", ", $updateFields) . " WHERE openid = :id";
        $updateValues[':id'] = $openid;

        $stmt = $pdo->prepare($updateSql);
        $stmt->execute($updateValues);

        if (isset($changedFields['openid'])) {
            unset($changedFields['openid']);
        }

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
        'changedFields' => []
    ];
}
echo json_encode($response);
?>