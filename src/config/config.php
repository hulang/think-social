<?php

declare(strict_types=1);

return [
    // 腾讯QQ登录配置
    'qq' => [
        'app_id'        => '', //应用注册成功后分配的 APP ID
        'app_secret'    => '',  //应用注册成功后分配的KEY
        'scope'         => 'get_user_info',
    ],
    // 微信扫码登录配置
    'weixin' => [
        'app_id'        => '', //应用注册成功后分配的 APP ID
        'app_secret'    => '',  //应用注册成功后分配的KEY
        'scope'         => 'snsapi_userinfo', // 如果需要静默授权,这里改成:snsapi_base,扫码登录系统会自动改为:snsapi_login
    ],
    // 新浪登录配置
    'sina' => [
        'app_id'        => '', //应用注册成功后分配的 APP ID
        'app_secret'    => '',  //应用注册成功后分配的KEY
        'scope'         => 'all',
    ],
];
