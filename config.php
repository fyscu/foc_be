<?php
return [
    'info' => [
        'domain' => 'https://focapi.feiyang.ac.cn',
        'appdomain' => 'https://focapp.feiyang.ac.cn',
        'adminreg' => false,
        'weeklyset' => 5,
        'ticketcooldown' => false,
        'ticketcooldowndays' => 1
    ],
    'token' => [
        'salt' => '会长爱玩原神'
    ],
    'qiniu' => [
        'accessKey' => 'dev@fyscu.com',
        'secretKey' => 'dev@fyscu.com',
        'bucket' => 'fyforum',
        'domain' => 'https://qncdn.feiyang.ac.cn',
        'uploadUrl' => 'https://up-z2.qiniup.com/'
    ],
    'db' => [
        'host' => 'host',
        'dbname' => 'name',
        'username' => 'name',
        'password' => 'pass'
    ],
    'wechat' => [
        'app_id' => 'dev@fyscu.com',
        'app_secret' => 'dev@fyscu.com'
    ],
    'email' => [
        'smtp_host' => 'smtp.wjlnb.com',
        'smtp_port' => 465,
        'username' => 'wjl@wjlo.cc',
        'password' => 'dev@fyscu.com'
    ],
    'sms' => [
        'secret_id' => 'dev@fyscu.com',
        'secret_key' => 'dev@fyscu.com',
        'sms_sdk_appid' => '114514',
        'template_ids' => [
            'registration' => '621159',
            'migration' => '621159',
            'reassign' => '2262137',
            'changephone' => '2262129',
            'assign_to_technician' => '1115370',
            'assign_to_user' => '1115369',
            'completion' => '1115372',
            'repair_completion' => '2294549',
            'beclosed' => '2272153'
        ],
        'sign_name' => '飞扬维修'
    ]
];

?>
