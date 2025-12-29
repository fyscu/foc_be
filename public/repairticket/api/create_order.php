<?php
require_once 'config.php';

try {
    $data = getPostData();
    
    // 验证必需字段
    $required_fields = [
        'position', 'customer_name', 'customer_phone', 
        'device_type', 'problem_description'
    ];
    validateRequired($data, $required_fields);
    
    $pdo = getDBConnection();
    
    // 获取当前活动ID
    $activity_stmt = $pdo->query("SELECT id FROM fyd_activities WHERE is_current = 1 LIMIT 1");
    $current_activity = $activity_stmt->fetch();
    
    if (!$current_activity) {
        sendResponse(false, null, '请先设置当前活动');
    }
    
    $activity_id = $current_activity['id'];
    
    // 生成订单编号
    $position = (int)$data['position'];
    
    // 获取当前活动的最大编号
    $max_stmt = $pdo->prepare("
        SELECT MAX(CAST(order_number AS UNSIGNED)) as max_number 
        FROM fyd_orders 
        WHERE activity_id = :activity_id
    ");
    $max_stmt->execute([':activity_id' => $activity_id]);
    $max_result = $max_stmt->fetch();
    $max_number = $max_result['max_number'] ?? 0;
    
    // 根据位置生成下一个编号
    if ($position == 1) {
        // 1号位：奇数编号
        $next_number = $max_number + 1;
        if ($next_number % 2 == 0) {
            $next_number++;
        }
    } else {
        // 4号位：偶数编号
        $next_number = $max_number + 1;
        if ($next_number % 2 == 1) {
            $next_number++;
        }
    }
    
    $order_number = str_pad($next_number, 4, '0', STR_PAD_LEFT);
    
    // 检查编号是否已存在，如果存在则递增
    $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM fyd_orders WHERE activity_id = :activity_id AND order_number = :order_number");
    while (true) {
        $check_stmt->execute([':activity_id' => $activity_id, ':order_number' => $order_number]);
        $exists = $check_stmt->fetch()['count'] > 0;
        
        if (!$exists) {
            break;
        }
        
        // 如果编号已存在，根据位置递增
        $next_number += 2; // 保持奇偶性
        $order_number = str_pad($next_number, 4, '0', STR_PAD_LEFT);
    }
    
    // 处理服务类型数组
    $service_type = '';
    if (isset($data['service_type']) && is_array($data['service_type'])) {
        $service_type = implode(',', $data['service_type']);
    }

    // 插入订单
    $sql = "
        INSERT INTO fyd_orders (
            activity_id, order_number, position, customer_name, customer_phone, 
            customer_qq, device_type, device_model, problem_description, 
            accessories, notes, status, created_at,
            pickup_time, gender, backup_phone, college, dormitory, student_id,
            login_password, existing_damage, service_type, allow_system_reinstall,
            allow_disk_format, important_data, repair_notes, staff_signature,
            customer_signature, technician1_name, technician1_time, technician1_phone,
            diagnosis, solution, technician_signature
        ) VALUES (
            :activity_id, :order_number, :position, :customer_name, :customer_phone,
            :customer_qq, :device_type, :device_model, :problem_description,
            :accessories, :notes, 'pending', NOW(),
            :pickup_time, :gender, :backup_phone, :college, :dormitory, :student_id,
            :login_password, :existing_damage, :service_type, :allow_system_reinstall,
            :allow_disk_format, :important_data, :repair_notes, :staff_signature,
            :customer_signature, :technician1_name, :technician1_time, :technician1_phone,
            :diagnosis, :solution, :technician_signature
        )
    ";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':activity_id' => $activity_id,
        ':order_number' => $order_number,
        ':position' => $position,
        ':customer_name' => $data['customer_name'],
        ':customer_phone' => $data['customer_phone'],
        ':customer_qq' => $data['customer_qq'] ?? '',
        ':device_type' => $data['device_type'],
        ':device_model' => $data['device_model'] ?? '',
        ':problem_description' => $data['problem_description'],
        ':accessories' => $data['accessories'] ?? '',
        ':notes' => $data['notes'] ?? '',
        ':pickup_time' => $data['pickup_time'] ?? null,
        ':gender' => $data['gender'] ?? '',
        ':backup_phone' => $data['backup_phone'] ?? '',
        ':college' => $data['college'] ?? '',
        ':dormitory' => $data['dormitory'] ?? '',
        ':student_id' => $data['student_id'] ?? '',
        ':login_password' => $data['login_password'] ?? '',
        ':existing_damage' => $data['existing_damage'] ?? '',
        ':service_type' => $service_type,
        ':allow_system_reinstall' => $data['allow_system_reinstall'] ?? '',
        ':allow_disk_format' => $data['allow_disk_format'] ?? '',
        ':important_data' => $data['important_data'] ?? '',
        ':repair_notes' => $data['repair_notes'] ?? '',
        ':staff_signature' => $data['staff_signature'] ?? '',
        ':customer_signature' => $data['customer_signature'] ?? '',
        ':technician1_name' => $data['technician1_name'] ?? '',
        ':technician1_time' => $data['technician1_time'] ?? null,
        ':technician1_phone' => $data['technician1_phone'] ?? '',
        ':diagnosis' => $data['diagnosis'] ?? '',
        ':solution' => $data['solution'] ?? '',
        ':technician_signature' => $data['technician_signature'] ?? ''
    ]);
    
    if ($result) {
        $order_id = $pdo->lastInsertId();
        sendResponse(true, [
            'order_id' => $order_id,
            'order_number' => $order_number
        ], '订单创建成功');
    } else {
        sendResponse(false, null, '订单创建失败');
    }
    
} catch (Exception $e) {
    sendResponse(false, null, '创建订单失败: ' . $e->getMessage());
}
?>