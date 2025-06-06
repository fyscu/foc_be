
<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0); // 提前结束响应，处理 OPTIONS 预检请求
}

include('../../db.php');
include('../../utils/token.php');
include('../../utils/headercheck.php');
include('../../utils/gets.php');
$user = $userinfo;

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
        
    $requiredFields = [
        'text' => '问题描述',
    ];

    $missingFields = [];

    foreach ($requiredFields as $field => $chineseExplanation) {
        if (empty($data[$field])) {
        $missingFields[] = "{$chineseExplanation}-{$field}";
        }
    }

    if (!empty($missingFields)) {
        echo json_encode([
            'success' => false,
            'message' => '下列所需值缺失或为空：' . implode('、 ', $missingFields)
        ]);
       exit;
    }

    $uid = $user['id'];
    $text = $data['text'];
    if(isset($data['contact'])){
        $contact = $data['contact'];
    } else {
        $contact = "";
    }
    
    $stmt = $pdo->prepare("INSERT INTO fy_info (user_id, question, contact) VALUES (?, ?, ?)");
    $stmt->execute([$uid, $text, $contact]);

    // 获取新创建的工单ID
    $questionId = $pdo->lastInsertId();

    if($questionId){
        echo json_encode([
            'success' => true,
            'qid' => $questionId
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'qid' => ''
        ]);
    }


?>