<?php
require_once 'config.php';

try {
    // 创建数据库连接（不指定数据库）
    $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 创建数据库
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);
    
    // 创建活动表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fyd_activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            activity_name VARCHAR(255) NOT NULL COMMENT '活动名称',
            activity_date DATE NOT NULL COMMENT '活动日期',
            description TEXT COMMENT '活动描述',
            is_current BOOLEAN DEFAULT FALSE COMMENT '是否为当前活动',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='活动表'
    ");
    
    // 创建技术员表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fyd_technicians (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL COMMENT '技术员姓名',
            phone VARCHAR(20) COMMENT '联系电话',
            specialty VARCHAR(255) COMMENT '专业领域',
            status ENUM('online', 'offline', 'busy') DEFAULT 'offline' COMMENT '状态',
            current_orders INT DEFAULT 0 COMMENT '当前订单数',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='技术员表'
    ");
    
    // 创建订单表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fyd_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            activity_id INT NOT NULL COMMENT '活动ID',
            order_number VARCHAR(20) NOT NULL COMMENT '订单编号',
            position TINYINT NOT NULL COMMENT '录入位置(1或2)',
            customer_name VARCHAR(100) NOT NULL COMMENT '客户姓名',
            customer_phone VARCHAR(20) NOT NULL COMMENT '客户电话',
            customer_qq VARCHAR(20) COMMENT '客户QQ',
            device_type VARCHAR(50) NOT NULL COMMENT '设备类型',
            device_model VARCHAR(100) COMMENT '设备型号',
            problem_description TEXT NOT NULL COMMENT '故障描述',
            accessories TEXT COMMENT '配件情况',
            notes TEXT COMMENT '备注',
            status ENUM('pending', 'processing', 'ready', 'completed') DEFAULT 'pending' COMMENT '订单状态',
            technician_id INT COMMENT '技术员ID',
            repair_notes TEXT COMMENT '维修记录',
            completion_time TIMESTAMP NULL COMMENT '完成时间',
            sms_sent BOOLEAN DEFAULT FALSE COMMENT '是否已发送短信',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (activity_id) REFERENCES fyd_activities(id),
            FOREIGN KEY (technician_id) REFERENCES fyd_technicians(id),
            UNIQUE KEY unique_order_number (activity_id, order_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='维修订单表'
    ");
    
    // 创建订单日志表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fyd_order_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL COMMENT '订单ID',
            action VARCHAR(50) NOT NULL COMMENT '操作类型',
            old_status VARCHAR(20) COMMENT '原状态',
            new_status VARCHAR(20) COMMENT '新状态',
            technician_id INT COMMENT '操作技术员ID',
            notes TEXT COMMENT '操作备注',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES fyd_orders(id),
            FOREIGN KEY (technician_id) REFERENCES fyd_technicians(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单操作日志表'
    ");
    
    // 创建短信日志表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fyd_sms_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL COMMENT '订单ID',
            phone VARCHAR(20) NOT NULL COMMENT '接收手机号',
            message TEXT NOT NULL COMMENT '短信内容',
            template_type VARCHAR(50) COMMENT '模板类型',
            status ENUM('sent', 'failed', 'pending') DEFAULT 'pending' COMMENT '发送状态',
            sent_at TIMESTAMP NULL COMMENT '发送时间',
            response_data TEXT COMMENT '接口响应数据',
            error_message TEXT COMMENT '错误信息',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES fyd_orders(id) ON DELETE CASCADE,
            INDEX idx_order_id (order_id),
            INDEX idx_phone (phone),
            INDEX idx_status (status),
            INDEX idx_sent_at (sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='短信发送日志表'
    ");
    
    // 插入默认数据
    // 创建默认活动
    $pdo->exec("
        INSERT IGNORE INTO fyd_activities (activity_name, activity_date, description, is_current) 
        VALUES ('测试活动', CURDATE(), '系统测试活动', TRUE)
    ");
    
    // 插入默认技术员
    $pdo->exec("
        INSERT IGNORE INTO fyd_technicians (name, phone, specialty, status) VALUES
        ('张三', '13800138001', '硬件维修', 'online'),
        ('李四', '13800138002', '软件故障', 'online'),
        ('王五', '13800138003', '系统重装', 'offline')
    ");
    
    sendResponse(true, null, '数据库初始化成功');
    
} catch (Exception $e) {
    sendResponse(false, null, '数据库初始化失败: ' . $e->getMessage());
}
?>