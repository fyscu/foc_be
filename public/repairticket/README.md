# 飞扬俱乐部电脑维修管理系统

## 项目简介

这是一个为四川大学飞扬俱乐部设计的电脑维修业务管理系统，用于电子化管理"大修"活动中的维修记录单。系统采用前后端分离架构，支持多角色协作和实时数据更新。

## 技术栈

### 前端
- 纯JavaScript + HTML + CSS
- Tailwind CSS 样式框架
- 响应式设计

### 后端
- PHP + MySQL
- 单文件API端点架构
- RESTful API设计

## 功能特性

### 1. 表单录入管理
- 1、4号位分别负责单双号编号录入
- 自动编号生成和验证
- 快速连续录入支持

### 2. 工作流状态管理
- 待接单 → 维修中 → 待取机 → 已完成
- 状态自动流转和手动控制
- 完整的操作日志记录

### 3. 大屏列表显示
- 6号位：技术员管理和订单分配
- 5号位：客服管理和短信通知
- 实时数据更新

### 4. 技术员管理
- 技术员信息管理
- 订单分配和转单功能
- 工作量统计

### 5. 活动管理
- 多活动支持
- 活动切换和数据隔离
- 统计报表

## 项目结构

```
├── index.html              # 主页面
├── js/
│   ├── app.js              # 主应用逻辑
│   ├── api.js              # API接口管理
│   └── pages.js            # 页面组件
├── api/                    # 后端API
│   ├── config.php          # 数据库配置
│   ├── init_database.php   # 数据库初始化
│   ├── get_orders.php      # 获取订单列表
│   ├── create_order.php    # 创建订单
│   ├── update_order_status.php # 更新订单状态
│   ├── get_order_stats.php # 获取统计数据
│   ├── get_technicians.php # 获取技术员列表
│   ├── assign_technician.php # 分配技术员
│   ├── send_sms.php        # 发送短信通知
│   └── get_activities.php  # 获取活动列表
└── README.md               # 项目说明
```

## 数据库设计

### 主要数据表
- `fyd_activities`: 活动表
- `fyd_orders`: 维修订单表
- `fyd_technicians`: 技术员表
- `fyd_order_logs`: 订单操作日志表

## 安装部署

### 1. 环境要求
- PHP 7.4+
- MySQL 5.7+
- Web服务器（Apache/Nginx）

### 2. 数据库初始化
访问 `api/init_database.php` 初始化数据库和默认数据

### 3. 配置数据库
编辑 `api/config.php` 文件，修改数据库连接信息：
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'fyd_repair_system');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### 4. 部署访问
将项目文件上传到Web服务器，通过浏览器访问 `index.html`

## 使用说明

### 1. 订单录入
- 选择录入位置（1号位或4号位）
- 填写客户和设备信息
- 系统自动生成对应的单双号编号

### 2. 订单管理
- 查看所有订单状态
- 筛选和搜索订单
- 状态流转管理

### 3. 大屏操作
- 6号位：接收订单，分配技术员
- 5号位：发送完成通知，处理取机

## 开发说明

### API接口规范
所有API返回格式：
```json
{
    "success": true/false,
    "data": {},
    "message": "操作结果信息"
}
```

### 状态定义
- `pending`: 待接单
- `processing`: 维修中
- `ready`: 待取机
- `completed`: 已完成

## 联系方式

如有问题或建议，请联系飞扬俱乐部技术团队。