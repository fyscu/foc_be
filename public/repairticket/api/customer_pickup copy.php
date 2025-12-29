<?php
require_once 'api/config.php';

// 获取订单ID
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 如果没有订单ID，显示错误
if (!$order_id) {
    echo "<div class='error'>无效的订单ID</div>";
    exit;
}

// 获取订单信息
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT o.*, t.name as technician_name
        FROM fyd_orders o
        LEFT JOIN fyd_technicians t ON o.technician_id = t.id
        WHERE o.id = :order_id
    ");
    $stmt->execute([':order_id' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo "<div class='error'>找不到订单信息</div>";
        exit;
    }
    
    // 检查订单状态，只有处于待取机的订单才能填写
    if ($order['status'] != 'ready') {
        echo "<div class='error'>该订单当前不处于待取机状态，无法填写取机确认</div>";
        exit;
    }
    
} catch (Exception $e) {
    echo "<div class='error'>系统错误: " . $e->getMessage() . "</div>";
    exit;
}

// 处理表单提交
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 获取表单数据
        $pickup_time = $_POST['pickup_time'] ?? null;
        $customer_signature = $_POST['customer_signature'] ?? '';
        
        // 更新订单信息
        $update_stmt = $pdo->prepare("
            UPDATE fyd_orders SET 
                pickup_time = :pickup_time,
                customer_signature = :customer_signature,
                status = 'completed',
                completion_time = NOW(),
                updated_at = NOW()
            WHERE id = :order_id
        ");
        
        $update_stmt->execute([
            ':pickup_time' => $pickup_time,
            ':customer_signature' => $customer_signature,
            ':order_id' => $order_id
        ]);
        
        $message = '<div class="success">取机确认已保存成功！订单已标记为已完成。</div>';
        
        // 重新获取订单信息
        $stmt->execute([':order_id' => $order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $message = '<div class="error">保存失败: ' . $e->getMessage() . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>客户取机确认 - 订单 <?php echo htmlspecialchars($order['order_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .error {
            background-color: #FEE2E2;
            color: #B91C1C;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }
        .success {
            background-color: #D1FAE5;
            color: #065F46;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-center text-blue-800 mb-6">
                四川大学飞扬俱乐部 - 客户取机确认
            </h1>
            
            <?php echo $message; ?>
            
            <div class="bg-blue-50 p-4 rounded-lg mb-6">
                <h2 class="text-lg font-semibold text-blue-800 mb-2">订单信息</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p><span class="font-medium">订单编号:</span> <?php echo htmlspecialchars($order['order_number']); ?></p>
                        <p><span class="font-medium">客户姓名:</span> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                        <p><span class="font-medium">联系电话:</span> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                    </div>
                    <div>
                        <p><span class="font-medium">设备类型:</span> <?php echo htmlspecialchars($order['device_type']); ?></p>
                        <p><span class="font-medium">品牌型号:</span> <?php echo htmlspecialchars($order['device_model']); ?></p>
                        <p><span class="font-medium">技术员:</span> <?php echo htmlspecialchars($order['technician_name'] ?? '未分配'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">故障描述</h3>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <?php echo nl2br(htmlspecialchars($order['problem_description'])); ?>
                </div>
            </div>
            
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">维修结果</h3>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p><span class="font-medium">故障诊断:</span></p>
                    <p class="mb-4"><?php echo nl2br(htmlspecialchars($order['diagnosis'] ?? '未填写')); ?></p>
                    
                    <p><span class="font-medium">解决方案:</span></p>
                    <p><?php echo nl2br(htmlspecialchars($order['solution'] ?? '未填写')); ?></p>
                </div>
            </div>
            
            <?php if ($order['status'] === 'ready'): ?>
            <form method="post" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">取机通知时间</label>
                    <input type="datetime-local" name="pickup_time" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">机主信息确认签字</label>
                    <input type="text" name="customer_signature" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex justify-end space-x-4 pt-6 border-t">
                    <button type="submit" 
                            class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        确认取机完成
                    </button>
                </div>
            </form>
            <?php else: ?>
            <div class="bg-green-50 p-4 rounded-lg">
                <p class="text-green-800 font-medium">该订单已完成取机确认</p>
                <p class="text-sm text-gray-600 mt-2">取机时间: <?php echo htmlspecialchars($order['pickup_time'] ?? '未记录'); ?></p>
                <p class="text-sm text-gray-600">客户签名: <?php echo htmlspecialchars($order['customer_signature'] ?? '未签名'); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>