<?php

return [
    'info' => [
        'domain' => 'domain'
    ],
    'token' => [
        'salt' => 'woaiwanyuanshen'
    ],
    'db' => [
        'host' => 'localhost',
        'dbname' => 'scufy',
        'username' => 'user',
        'password' => 'pass'
    ],
    'wechat' => [
        'app_id' => 'id',
        'app_secret' => 'key'
    ],
    'email' => [
        'smtp_host' => 'smtp.exmail.qq.com',
        'smtp_port' => 465,
        'username' => 'user',
        'password' => 'pass'
    ],
    'sms' => [
        'secret_id' => 'id',
        'secret_key' => 'key',
        'sms_sdk_appid' => 'id',
        'template_ids' => [
            'registration' => '1',
            'assign_to_technician' => '2',
            'assign_to_user' => '3',
            'completion' => '4'
        ],
        'sign_name' => 'sign'
    ]
];

?>
