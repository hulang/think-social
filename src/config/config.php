<?php

// +----------------------------------------------------------------------
// | 配置
// +----------------------------------------------------------------------

declare(strict_types=1);

return [
    // 腾讯QQ登录配置
    'qq' => [
        // 应用注册成功后分配的 APP ID
        'app_id' => '',
        // 应用注册成功后分配的KEY
        'app_secret' => '',
        'scope' => 'get_user_info',
    ],
    // 微信扫码登录配置
    'weixin' => [
        // 应用注册成功后分配的 APP ID
        'app_id' => '',
        // 应用注册成功后分配的KEY
        'app_secret' => '',
        // 如果需要静默授权,这里改成:snsapi_base,扫码登录系统会自动改为:snsapi_login 
        'scope' => 'snsapi_userinfo',
    ],
    // 新浪登录配置
    'sina' => [
        // 应用注册成功后分配的 APP ID
        'app_id' => '',
        // 应用注册成功后分配的KEY 
        'app_secret' => '',
        'scope' => 'all',
    ],
];
