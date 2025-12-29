<?php
require_once 'config.php';
header('Content-Type: text/html; charset=utf-8');
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
        echo "<div class='error'>订单不存在</div>";
        exit;
    }
    
    // 检查订单状态
    if ($order['status'] !== 'processing') {
        echo "<div class='error'>该订单当前不在维修中状态，无法填写维修记录</div>";
        exit;
    }
} catch (Exception $e) {
    echo "<div class='error'>系统错误: " . $e->getMessage() . "</div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>技术员维修记录 - <?php echo htmlspecialchars($order['order_number']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #f3f4f6;
        }
        .container {
            max-width: 800px;
        }
        .error {
            color: #ef4444;
            padding: 1rem;
            margin: 1rem;
            background-color: #fee2e2;
            border-radius: 0.375rem;
            text-align: center;
        }
        .success {
            color: #10b981;
            padding: 1rem;
            margin: 1rem;
            background-color: #d1fae5;
            border-radius: 0.375rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">技术员维修记录</h1>
            <div class="border-b pb-4 mb-4">
                <!-- 订单基本信息 -->
                <h3 class="text-lg font-semibold text-gray-800 mb-3">订单基本信息</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-sm text-gray-600">订单编号</p>
                        <p class="font-medium"><?php echo htmlspecialchars($order['order_number']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">录入位置</p>
                        <p class="font-medium"><?php echo htmlspecialchars($order['position'] == 1 ? '1号位 (单号)' : '4号位 (双号)'); ?></p>
                    </div>
                </div>

                <!-- 机主个人基本信息 -->
                <h3 class="text-lg font-semibold text-gray-800 mb-3">机主个人基本信息</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-sm text-gray-600">姓名</p>
                        <p class="font-medium"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">性别</p>
                        <p class="font-medium"><?php echo htmlspecialchars($order['gender'] ?? '未填写'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">手机号</p>
                        <p class="font-medium"><?php echo htmlspecialchars($order['customer_phone']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">备用联系人手机号</p>
                        <p class="font-medium"><?php echo htmlspecialchars($order['backup_phone'] ?? '未填写'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">就读学院</p>
                        <p class="font-medium"><?php echo htmlspecialchars($order['college'] ?? '未填写'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">居住宿舍</p>
                        <p class="font-medium"><?php echo htmlspecialchars($order['dormitory'] ?? '未填写'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">学号</p>
                        <p class="font-medium"><?php echo htmlspecialchars($order['student_id'] ?? '未填写'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">QQ号码</p>
                        <p class="font-medium"><?php echo htmlspecialchars($order['customer_qq'] ?? '未填写'); ?></p>
                    </div>
                </div>

                <!-- 待修电脑基本信息 -->
                <h3 class="text-lg font-semibold text-gray-800 mb-3">待修电脑基本信息</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-sm text-gray-600">设备类型</p>
                        <p class="font-medium"><?php echo htmlspecialchars($order['device_type']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">品牌与型号</p>
                        <p class="font-medium"><?php echo htmlspecialchars($order['device_model'] ?? '未填写'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">开机密码</p>
                        <p class="font-medium"><?php echo htmlspecialchars($order['login_password'] ?? '未填写'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">外带附件</p>
                        <p class="font-medium"><?php echo htmlspecialchars($order['accessories'] ?? '无'); ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-600">电脑已经存在的外观及硬件损坏与缺陷</p>
                        <p class="font-medium"><?php echo nl2br(htmlspecialchars($order['existing_damage'] ?? '无')); ?></p>
                    </div>
                </div>

                <!-- 所需服务 -->
                <h3 class="text-lg font-semibold text-gray-800 mb-3">所需服务</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-600">服务类型</p>
                        <p class="font-medium"><?php echo htmlspecialchars($order['service_type'] ?? '未指定'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">是否可以系统重装</p>
                        <p class="font-medium"><?php echo htmlspecialchars($order['allow_system_reinstall'] ?? '未指定'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">是否可以清空硬盘</p>
                        <p class="font-medium"><?php echo htmlspecialchars($order['allow_disk_format'] ?? '未指定'); ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-600">故障描述</p>
                        <p class="font-medium"><?php echo nl2br(htmlspecialchars($order['problem_description'])); ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-600">需要进行备份的重要数据</p>
                        <p class="font-medium"><?php echo nl2br(htmlspecialchars($order['important_data'] ?? '无')); ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-600">对于故障或维修要求的补充描述</p>
                        <p class="font-medium"><?php echo nl2br(htmlspecialchars($order['repair_notes'] ?? '无')); ?></p>
                    </div>
                </div>

                <!-- 备注 -->
                <h3 class="text-lg font-semibold text-gray-800 mb-3">备注</h3>
                <div class="mb-4">
                    <p class="font-medium"><?php echo nl2br(htmlspecialchars($order['notes'] ?? '无')); ?></p>
                </div>
            </div>
            
            <form id="repairForm" class="space-y-6">
                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                
                <!-- 技术员信息 -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">技术员信息</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">技术员姓名</label>
                            <input type="text" name="technician1_name" value="<?php echo htmlspecialchars($order['technician_name'] ?? ''); ?>" readonly
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">联系方式</label>
                            <input type="text" name="technician1_phone" value="<?php echo htmlspecialchars($order['technician_phone'] ?? ''); ?>" readonly
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">接手时间</label>
                            <input type="datetime-local" name="technician1_time" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
                
                <!-- 故障诊断 -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">故障诊断</h3>
                    <textarea name="diagnosis" rows="4" required
                              placeholder="请详细描述故障诊断结果..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <!-- 解决方案 -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">解决方案</h3>
                    <textarea name="solution" rows="4" required
                              placeholder="请详细描述解决方案..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <!-- 技术员签名 -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">技术员签名确认</h3>
                    <div id="signatureCanvas" class="border border-gray-300 rounded-md h-40 bg-gray-50 flex items-center justify-center">
                        <canvas id="technicianSignature" width="400" height="150" class="cursor-crosshair"></canvas>
                    </div>
                    <input type="hidden" name="technician_signature" id="technicianSignatureData">
                    <div class="flex justify-end mt-2">
                        <button type="button" id="clearSignature" class="text-sm text-blue-600 hover:text-blue-800">
                            清除签名
                        </button>
                    </div>
                </div>
                
                <!-- 提交按钮 -->
                <div class="flex justify-end space-x-4 pt-6 border-t">
                    <button type="submit" 
                            class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        提交维修记录
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化签名画布
            initSignatureCanvas();
            
            // 表单提交处理
            document.getElementById('repairForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // 获取表单数据
                const formData = new FormData(e.target);
                const repairData = Object.fromEntries(formData.entries());
                
                // 验证签名
                if (!repairData.technician_signature) {
                    showMessage('请完成签名确认', 'error');
                    return;
                }
                
                try {
                    // 提交维修记录
                    const response = await fetch('update_repair_record.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(repairData)
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showMessage('维修记录已提交，订单状态已更新为待取机', 'success');
                        
                        // 禁用表单
                        const form = document.getElementById('repairForm');
                        const inputs = form.querySelectorAll('input, textarea, button');
                        inputs.forEach(input => input.disabled = true);
                        
                        // 3秒后关闭页面
                        setTimeout(() => {
                            window.close();
                        }, 3000);
                    } else {
                        showMessage('提交失败: ' + result.message, 'error');
                    }
                } catch (error) {
                    console.error('提交维修记录失败:', error);
                    showMessage('提交失败，请重试', 'error');
                }
            });
        });
        
        // 初始化签名画布
        function initSignatureCanvas() {
            const canvas = document.getElementById('technicianSignature');
            const ctx = canvas.getContext('2d');
            const signatureInput = document.getElementById('technicianSignatureData');
            
            // 调整画布大小以适应容器
            const resizeCanvas = () => {
                const container = canvas.parentElement;
                canvas.width = container.clientWidth;
                canvas.height = container.clientHeight;
                
                // 重新设置画布样式
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.strokeStyle = '#000';
            };
            
            // 初始调整画布大小
            resizeCanvas();
            
            // 窗口大小变化时重新调整
            window.addEventListener('resize', resizeCanvas);
            
            let isDrawing = false;
            let lastX = 0;
            let lastY = 0;
            
            // 清除签名按钮
            document.getElementById('clearSignature').addEventListener('click', function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                signatureInput.value = '';
            });
            
            // 鼠标事件处理
            function startDrawing(e) {
                isDrawing = true;
                const pos = getPosition(e);
                lastX = pos.x;
                lastY = pos.y;
            }
            
            function draw(e) {
                if (!isDrawing) return;
                
                const pos = getPosition(e);
                
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                
                lastX = pos.x;
                lastY = pos.y;
                
                // 保存签名数据
                signatureInput.value = canvas.toDataURL();
            }
            
            function stopDrawing() {
                isDrawing = false;
            }
            
            // 获取鼠标或触摸的位置
            function getPosition(e) {
                const rect = canvas.getBoundingClientRect();
                let x, y;
                
                // 触摸事件
                if (e.type.includes('touch')) {
                    const touch = e.touches[0] || e.changedTouches[0];
                    x = touch.clientX - rect.left;
                    y = touch.clientY - rect.top;
                } 
                // 鼠标事件
                else {
                    x = e.clientX - rect.left;
                    y = e.clientY - rect.top;
                }
                
                return { x, y };
            }
            
            // 绑定鼠标事件
            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('mouseout', stopDrawing);
            
            // 绑定触摸事件
            canvas.addEventListener('touchstart', function(e) {
                e.preventDefault(); // 防止滚动
                startDrawing(e);
            });
            
            canvas.addEventListener('touchmove', function(e) {
                e.preventDefault(); // 防止滚动
                draw(e);
            });
            
            canvas.addEventListener('touchend', function(e) {
                e.preventDefault(); // 防止滚动
                stopDrawing();
            });
        }
        
        // 显示消息
        function showMessage(message, type) {
            const container = document.querySelector('.container');
            const messageDiv = document.createElement('div');
            messageDiv.className = type;
            messageDiv.textContent = message;
            
            // 移除之前的消息
            const oldMessages = document.querySelectorAll('.error, .success');
            oldMessages.forEach(msg => msg.remove());
            
            // 添加新消息
            container.insertBefore(messageDiv, container.firstChild);
            
            // 自动消失
            if (type !== 'error') {
                setTimeout(() => {
                    messageDiv.remove();
                }, 5000);
            }
        }
    </script>
</body>
</html>