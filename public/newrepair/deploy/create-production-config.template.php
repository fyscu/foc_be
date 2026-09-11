<?php
// 这是生产配置模板，不要放到 public 目录下。
// 实际文件路径：
// /opt/1panel/apps/openresty/openresty/www/sites/focapi.feiyang.ac.cn/index/config.feidaxiu.php
return [
    'db' => [
        'host' => 'mysql',
        'dbname' => 'foc',
        'username' => 'foc',
        'password' => '填入数据库密码',
        'charset' => 'utf8mb4',
    ],
    'wechat' => [
        'appid' => '填入服务号 appid',
        'secret' => '填入服务号 secret',
        'oauth_callback_base' => 'https://focapp.feiyang.ac.cn/public/newrepair/api/index.php',
        'scope' => 'snsapi_base',
    ],
    'security' => [
        'session_name' => 'FDXSESSID',
        'cookie_secure' => true,
        'bind_token_ttl_minutes' => 30,
        'technician_cookie_name' => 'FDXTECH',
        'technician_cookie_ttl_seconds' => 60 * 60 * 24 * 30,
        'auth_cookie_key' => '可选：单独填一个随机长密钥，用于技术员持久登录 cookie',
        'encryption_key' => '填入随机长密钥，用于开机密码等敏感字段加密',
    ],
];
