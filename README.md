## ThinkPHP 8.0.0+ 社会化登录扩展包

### 环境

- php >= 8.0.0
- ThinkPHP >=8.0.0

目前已支持
- QQ
- 微信
- 新浪
- 百度
- Gitee
- Github
- Oschina
- Google
- Facebook
- 淘宝
- 抖音
- 小米
- 钉钉

```sh
composer require hulang/think-social
```

# `config/social.php`配置
```php
<?php

declare(strict_types=1);

/**
 * 配置信息
 */

return [
    // 腾讯QQ
    'qq' => [
        'app_key' => '', // 应用注册成功后分配的 APP ID
        'app_secret' => '',  // 应用注册成功后分配的KEY
        'callback' => '', // 应用回调地址
    ],
    // 微信扫码
    'weixin' => [
        'app_key' => '', // 应用注册成功后分配的 APP ID
        'app_secret' => '',  // 应用注册成功后分配的KEY
        'callback' => '', // 应用回调地址
    ],
    // 新浪
    'sina' => [
        'app_key' => '', // 应用注册成功后分配的 APP ID
        'app_secret' => '',  // 应用注册成功后分配的KEY
        'callback' => '', // 应用回调地址
    ],
    // 百度
    'baidu' => [
        'app_key' => '', // 应用注册成功后分配的 APP ID
        'app_secret' => '',  // 应用注册成功后分配的KEY
        'callback' => '', // 应用回调地址
    ],
    // Gitee
    'gitee' => [
        'app_key' => '', // 应用注册成功后分配的 APP ID
        'app_secret' => '',  // 应用注册成功后分配的KEY
        'callback' => '', // 应用回调地址
    ],
    // Github
    'github' => [
        'app_key' => '', // 应用注册成功后分配的 APP ID
        'app_secret' => '',  // 应用注册成功后分配的KEY
        'callback' => '', // 应用回调地址
    ],
    // Google
    'google' => [
        'app_key' => '', // 应用注册成功后分配的 APP ID
        'app_secret' => '',  // 应用注册成功后分配的KEY
        'callback' => '', // 应用回调地址
    ],
    // Facebook
    'facebook' => [
        'app_key' => '', // 应用注册成功后分配的 APP ID
        'app_secret' => '',  // 应用注册成功后分配的KEY
        'callback' => '', // 应用回调地址
    ],
    // Oschina
    'oschina' => [
        'app_key' => '', // 应用注册成功后分配的 APP ID
        'app_secret' => '',  // 应用注册成功后分配的KEY
        'callback' => '', // 应用回调地址
    ],
    // 淘宝
    'taobao' => [
        'app_key' => '', // 应用注册成功后分配的 APP ID
        'app_secret' => '',  // 应用注册成功后分配的KEY
        'callback' => '', // 应用回调地址
    ],
    // 抖音
    'douyin' => [
        'app_key' => '', // 应用注册成功后分配的 APP ID
        'app_secret' => '',  // 应用注册成功后分配的KEY
        'callback' => '', // 应用回调地址
    ],
    // 小米
    'xiaomi' => [
        'app_key' => '', // 应用注册成功后分配的 APP ID
        'app_secret' => '',  // 应用注册成功后分配的KEY
        'callback' => '', // 应用回调地址
    ],
    // 钉钉
    'dingtalk' => [
        'app_key' => '', // 应用注册成功后分配的 APP ID
        'app_secret' => '',  // 应用注册成功后分配的KEY
        'callback' => '', // 应用回调地址
    ]
];


```

## 用法示例
```html
<a href="{:url('Oauth/login',['type'=>'qq'])}">QQ登录</a>
<a href="{:url('Oauth/login',['type'=>'sina'])}">新浪微博登录</a>
<a href="{:url('Oauth/login',['type'=>'weixin'])}">微信登录</a>
<a href="{:url('Oauth/login',['type'=>'baidu'])}">百度登录</a>
<a href="{:url('Oauth/login',['type'=>'gitee'])}">gitee登录</a>
<a href="{:url('Oauth/login',['type'=>'github'])}">github登录</a>
<a href="{:url('Oauth/login',['type'=>'oschaina'])}">oschaina登录</a>
<a href="{:url('Oauth/login',['type'=>'google'])}">google登录</a>
<a href="{:url('Oauth/login',['type'=>'facebook'])}">facebook登录</a>
<a href="{:url('Oauth/login',['type'=>'taobao'])}">淘宝登录</a>
<a href="{:url('Oauth/login',['type'=>'douyin'])}">抖音登录</a>
<a href="{:url('Oauth/login',['type'=>'xiaomi'])}">小米登录</a>
<a href="{:url('Oauth/login',['type'=>'dingtalk'])}">钉钉登录</a>
```


```php
<?php

namespace app\index\controller;

use think\OAuth2\OAuth;

class Oauth
{
    // 登录地址
    public function login($type = null)
    {
        if ($type == null) {
            $this->error('参数错误');
        }
        // 获取对象实例
        $sns = \think\OAuth2\Social::getInstance($type);
        // 设置回跳地址
        $sns['Callback'] = $this->makeCallback($type);
        // 跳转到授权页面
        $this->redirect($sns->getRequestCodeURL());
    }

    // 授权回调地址
    public function callback($type = null, $code = null)
    {
        if ($type == null || $code == null) {
            $this->error('参数错误');
        }
        $sns = \think\OAuth2\Social::getInstance($type);
        // 获取TOKEN
        $token = $sns->getAccessToken($code);
        //获取当前第三方登录用户信息
        if (is_array($token)) {
            $user_info = \think\OAuth2\GetInfo::getInstance($type, $token);
            dump($user_info); // 获取第三方用户资料
            $sns->openid(); //统一使用$sns->openid()获取openid
            //$sns->unionid();//QQ和微信、淘宝可以获取unionid
            dump($sns->openid());
            echo '登录成功!!';
            echo '正在持续开发中，敬请期待!!';
        } else {
            echo "获取第三方用户的基本信息失败";
        }
    }

    /**
     * 生成回跳地址
     *
     * @return string
     */
    private function makeCallback($name)
    {
        //注意需要生成完整的带http的地址
        return url('/oauth/callback/' . $name, '', 'html', true);
    }
}

```
