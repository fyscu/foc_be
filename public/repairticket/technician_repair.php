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
        SELECT o.*, t.name as technician_name, t.phone as technician_phone
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
    
    // 检查订单状态，只有处于维修中的订单才能填写
    if ($order['status'] != 'processing') {
        echo "<div class='error'>该订单当前不处于维修中状态，无法填写维修记录</div>";
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
        $diagnosis = $_POST['diagnosis'] ?? '';
        $solution = $_POST['solution'] ?? '';
        $technician1_name = $_POST['technician1_name'] ?? '';
        $technician1_phone = $_POST['technician1_phone'] ?? '';
        $technician_signature = $_POST['technician_signature'] ?? '';
        
        // 更新订单信息
        $update_stmt = $pdo->prepare("
            UPDATE fyd_orders SET 
                diagnosis = :diagnosis,
                solution = :solution,
                technician1_name = :technician1_name,
                technician1_phone = :technician1_phone,
                technician1_time = NOW(),
                technician_signature = :technician_signature,
                updated_at = NOW()
            WHERE id = :order_id
        ");
        
        $update_stmt->execute([
            ':diagnosis' => $diagnosis,
            ':solution' => $solution,
            ':technician1_name' => $technician1_name,
            ':technician1_phone' => $technician1_phone,
            ':technician_signature' => $technician_signature,
            ':order_id' => $order_id
        ]);
        
        // 如果技术员已签名，将订单状态更新为待取机
        if (!empty($technician_signature)) {
            $status_stmt = $pdo->prepare("
                UPDATE fyd_orders SET 
                    status = 'ready'
                WHERE id = :order_id
            ");
            $status_stmt->execute([':order_id' => $order_id]);
        }
        
        $message = '<div class="success">维修记录已保存成功！</div>';
        
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
    <title>技术员维修记录 - 订单 <?php echo htmlspecialchars($order['order_number']); ?></title>
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
                四川大学飞扬俱乐部 - 技术员维修记录
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
                        <p><span class="font-medium">创建时间:</span> <?php echo htmlspecialchars($order['created_at']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">故障描述</h3>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <?php echo nl2br(htmlspecialchars($order['problem_description'])); ?>
                </div>
            </div>
            
            <?php if (!empty($order['service_type'])): ?>
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">所需服务</h3>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <?php 
                    $services = explode(',', $order['service_type']);
                    foreach ($services as $service) {
                        echo '<span class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded mr-2 mb-2">' . htmlspecialchars($service) . '</span>';
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
            
            <form method="post" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">技术员姓名</label>
                    <input type="text" name="technician1_name" value="<?php echo htmlspecialchars($order['technician1_name'] ?? $order['technician_name'] ?? ''); ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">技术员联系方式</label>
                    <input type="text" name="technician1_phone" value="<?php echo htmlspecialchars($order['technician1_phone'] ?? $order['technician_phone'] ?? ''); ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">故障诊断</label>
                    <textarea name="diagnosis" rows="4" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($order['diagnosis'] ?? ''); ?></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">解决方案</label>
                    <textarea name="solution" rows="4" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($order['solution'] ?? ''); ?></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">技术员签名确认</label>
                    <input type="text" name="technician_signature" value="<?php echo htmlspecialchars($order['technician_signature'] ?? ''); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-sm text-gray-500 mt-1">签名后，订单将自动更新为"待取机"状态</p>
                </div>
                
                <div class="flex justify-end space-x-4 pt-6 border-t">
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        保存维修记录
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>