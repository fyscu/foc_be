<?php
//其实可以把所有查询放在一个函数里，但是我觉得反正都封装了也看不见，给每种查询可能都专属创建一个函数，当然也可以直接调用万金油函数getAll()
function getUserById($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM fy_users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserByOpenid($openid) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM fy_users WHERE openid = ?");
    $stmt->execute([$openid]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserByAccessToken($token) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM fy_users WHERE access_token = ?");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserByPhone($phone) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM fy_users WHERE phone = ?");
    $stmt->execute([$phone]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserByEmail($email) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM fy_users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getTicketById($tid) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM fy_workorders WHERE id = ?");
    $stmt->execute([$tid]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getTicketByTechnician($technicianid) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM fy_workorders WHERE assigned_technician_id = ?");
    $stmt->execute([$technicianid]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getTicketByUserid($uid) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM fy_workorders WHERE user_id = ?");
    $stmt->execute([$uid]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getTicketByStatus($status) {//pending or done
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM fy_workorders WHERE repair_status = ?");
    $stmt->execute([$status]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getAll($query,$table,$type) {//使用此函数需要对本项目数据库结构极其熟悉
    global $pdo;  
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE $type = ?");
    $stmt->execute([$query]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUnansweredQuestions() {//已弃用
    global $pdo;
    $stmt = $pdo->prepare("SELECT fy_info.id, fy_users.nickname, fy_info.question, fy_info.created_at 
                               FROM fy_info 
                               JOIN fy_users ON fy_info.user_id = fy_users.id 
                               WHERE fy_info.status = 'unanswered'");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);    
}

// 获取大修活动报名数据并构造可用性矩阵
function getRepairRegistrations($activity_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT r.id, r.name, r.gender, r.departments, r.free_times FROM fy_repair_registrations r WHERE r.activity_id = ? ORDER BY id");
    $stmt->execute([$activity_id]);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 初始化assigned字段
    foreach ($registrations as &$registration) {
        if (!isset($registration['assigned'])) {
            $registration['assigned'] = 0;
        }
    }

    return $registrations;
}