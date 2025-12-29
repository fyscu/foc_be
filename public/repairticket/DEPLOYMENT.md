# 飞扬俱乐部电脑维修管理系统 - 部署指南

## 系统概述

飞扬俱乐部电脑维修管理系统是一个专为四川大学飞扬俱乐部设计的电子化维修记录管理系统，支持完整的维修业务流程管理。

### 主要功能
- ✅ 表单录入管理（1、4号位单双号分离录入）
- ✅ 工作流状态管理（待接单→维修中→待取机→已完成）
- ✅ 大屏列表显示（5、6号位专用界面）
- ✅ 技术员管理和转单功能
- ✅ 短信通知系统

## 技术架构

### 前端技术栈
- **纯JavaScript** - 无需Node.js环境
- **HTML5 + CSS3** - 标准Web技术
- **Tailwind CSS** - 现代化样式框架
- **Font Awesome** - 图标库

### 后端技术栈
- **PHP 7.4+** - 服务端语言
- **MySQL 5.7+** - 数据库
- **单文件API架构** - 简化部署和维护

## 环境要求

### 服务器要求
- **Web服务器**: Apache 2.4+ 或 Nginx 1.18+
- **PHP版本**: PHP 7.4 或更高版本
- **数据库**: MySQL 5.7+ 或 MariaDB 10.3+
- **PHP扩展**: PDO, PDO_MySQL, JSON, mbstring

### 推荐配置
- **内存**: 最少512MB，推荐1GB+
- **存储**: 最少100MB可用空间
- **带宽**: 支持并发访问的稳定网络

## 部署步骤

### 1. 文件上传

将所有项目文件上传到Web服务器的根目录或子目录：

```
your-domain.com/
├── index.html              # 主页面
├── api/                    # API接口目录
│   ├── config.php         # 数据库配置
│   ├── init_database.php  # 数据库初始化
│   └── *.php             # 各种API端点
├── js/                    # JavaScript文件
├── css/                   # 样式文件
├── README.md              # 项目说明
└── DEPLOYMENT.md          # 部署指南
```

### 2. 数据库配置

编辑 `api/config.php` 文件，配置数据库连接信息：

```php
<?php
// 数据库配置
define('DB_HOST', 'localhost');        // 数据库主机
define('DB_NAME', 'feiyang_repair');   // 数据库名称
define('DB_USER', 'your_username');    // 数据库用户名
define('DB_PASS', 'your_password');    // 数据库密码
define('DB_CHARSET', 'utf8mb4');       // 字符集
?>
```

### 3. 数据库初始化

访问以下URL初始化数据库：
```
http://your-domain.com/api/init_database.php
```

成功后会看到：`{"success":true,"message":"数据库初始化成功"}`

### 4. 权限设置

确保以下目录具有适当的读写权限：
```bash
chmod 755 api/
chmod 644 api/*.php
chmod 644 index.html
chmod 644 js/*.js
chmod 644 css/*.css
```

### 5. Web服务器配置

#### Apache配置
在项目根目录创建 `.htaccess` 文件：
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ api/$1 [L]

# 启用CORS
Header always set Access-Control-Allow-Origin "*"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Authorization"
```

#### Nginx配置
在Nginx配置文件中添加：
```nginx
location /api/ {
    try_files $uri $uri/ =404;
    
    # 启用CORS
    add_header Access-Control-Allow-Origin *;
    add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS";
    add_header Access-Control-Allow-Headers "Content-Type, Authorization";
    
    # 处理OPTIONS请求
    if ($request_method = 'OPTIONS') {
        return 204;
    }
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

## 功能测试

### 1. 基础功能测试

访问主页面：`http://your-domain.com/`

检查以下功能：
- [ ] 页面正常加载，无JavaScript错误
- [ ] 导航菜单正常工作
- [ ] 数据统计正常显示

### 2. 订单录入测试

进入"订单录入"页面：
- [ ] 1号位（奇数编号）录入测试
- [ ] 4号位（偶数编号）录入测试
- [ ] 表单验证正常工作
- [ ] 提交后数据正确保存

### 3. 工作流测试

进入"订单管理"页面：
- [ ] 订单状态正常显示
- [ ] 状态转换功能正常
- [ ] 技术员分配功能正常
- [ ] 操作日志正确记录

### 4. 大屏功能测试

测试大屏显示：
- [ ] 6号位大屏正常显示待处理订单
- [ ] 5号位大屏正常显示待取机订单
- [ ] 实时数据刷新正常
- [ ] 快捷操作功能正常

### 5. 短信功能测试

测试短信通知：
- [ ] 单条短信发送功能
- [ ] 批量短信发送功能
- [ ] 发送历史记录正常
- [ ] 模板管理功能正常

## 性能优化

### 1. 数据库优化

```sql
-- 为常用查询添加索引
ALTER TABLE fyd_orders ADD INDEX idx_status (status);
ALTER TABLE fyd_orders ADD INDEX idx_created_at (created_at);
ALTER TABLE fyd_orders ADD INDEX idx_technician_id (technician_id);
ALTER TABLE fyd_technicians ADD INDEX idx_status (status);
ALTER TABLE fyd_sms_logs ADD INDEX idx_order_id (order_id);
```

### 2. 缓存配置

在Apache中启用缓存：
```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
</IfModule>
```

### 3. 压缩配置

启用Gzip压缩：
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
```

## 安全配置

### 1. 数据库安全

- 使用专用数据库用户，仅授予必要权限
- 定期备份数据库
- 启用MySQL慢查询日志监控

### 2. 文件安全

```apache
# 禁止直接访问配置文件
<Files "config.php">
    Order allow,deny
    Deny from all
</Files>

# 禁止访问敏感目录
<DirectoryMatch "^/.*(\.git|\.svn|\.env)">
    Order allow,deny
    Deny from all
</DirectoryMatch>
```

### 3. 输入验证

系统已内置以下安全措施：
- SQL注入防护（使用PDO预处理语句）
- XSS防护（输出转义）
- CSRF防护（表单令牌验证）
- 输入长度限制和格式验证

## 监控和维护

### 1. 日志监控

定期检查以下日志：
- Web服务器访问日志
- PHP错误日志
- MySQL慢查询日志
- 应用程序操作日志

### 2. 数据备份

建议设置自动备份：
```bash
#!/bin/bash
# 数据库备份脚本
mysqldump -u username -p password feiyang_repair > backup_$(date +%Y%m%d_%H%M%S).sql
```

### 3. 性能监控

监控关键指标：
- 页面加载时间
- API响应时间
- 数据库查询性能
- 系统资源使用率

## 故障排除

### 常见问题

1. **页面无法加载**
   - 检查Web服务器配置
   - 确认文件权限设置
   - 查看错误日志

2. **数据库连接失败**
   - 验证数据库配置信息
   - 检查数据库服务状态
   - 确认用户权限

3. **API请求失败**
   - 检查CORS配置
   - 验证API端点路径
   - 查看PHP错误日志

4. **短信发送失败**
   - 检查短信接口配置
   - 验证网络连接
   - 查看发送日志

### 联系支持

如遇到技术问题，请提供以下信息：
- 系统环境信息
- 错误日志内容
- 问题复现步骤
- 预期行为描述

## 更新升级

### 版本更新流程

1. 备份当前系统和数据库
2. 下载新版本文件
3. 更新代码文件
4. 运行数据库迁移脚本
5. 测试功能正常性
6. 清理缓存文件

### 数据迁移

如需迁移到新服务器：
1. 导出数据库：`mysqldump -u username -p feiyang_repair > backup.sql`
2. 复制所有项目文件
3. 在新服务器导入数据库：`mysql -u username -p feiyang_repair < backup.sql`
4. 更新配置文件
5. 测试系统功能

---

## 系统已就绪！🎉

飞扬俱乐部电脑维修管理系统现已完成开发和部署准备。按照本指南进行部署后，系统即可投入正式使用。

如有任何问题或需要技术支持，请参考故障排除部分或联系开发团队。