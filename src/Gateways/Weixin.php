<?php

declare(strict_types=1);

namespace think\OAuth2\Gateways;

use think\OAuth2\Connector\Gateway;

class Weixin extends Gateway
{
    const API_BASE = 'https://api.weixin.qq.com/sns/';

    protected $AuthorizeURL = 'https://open.weixin.qq.com/connect/qrconnect';
    protected $AccessTokenURL = 'https://api.weixin.qq.com/sns/oauth2/access_token';

    /**
     * 得到跳转地址
     * 本函数用于构造并返回授权后的跳转URL
     * 它根据配置信息,结合授权接口的参数要求,组装一个完整的URL
     * 这个URL用于用户授权后重定向到的应用回调地址
     *
     * @return mixed|string 返回构造好的授权跳转URL
     */
    public function getRedirectUrl()
    {
        // 切换到使用AccessToken的URL,这可能是为了处理不同阶段或情况下的授权流程
        $this->switchAccessTokenURL();
        // 组装请求参数,这些参数根据OAuth2.0的规范和应用的配置进行设置
        $params = [
            // 应用ID
            'appid' => $this->config['app_id'],
            // 授权后重定向的URI
            'redirect_uri' => $this->config['callback'],
            // 响应类型,通常是"code"
            'response_type' => $this->config['response_type'],
            // 授权范围
            'scope' => $this->config['scope'],
            // 用于保持请求和回调的状态
            'state' => $this->config['state'],
        ];
        // 构造完整的授权URL,包括查询参数和#wechat_redirect的片段
        // #wechat_redirect 是微信特定的片段,用于指示浏览器应如何处理授权后的重定向
        return $this->AuthorizeURL . '?' . http_build_query($params) . '#wechat_redirect';
    }

    /**
     * 获取中转代理地址
     * 
     * 本函数用于构建一个中转代理URL,该URL包含了一系列的参数,用于在OAuth授权过程中进行中转
     * 这些参数包括应用ID、响应类型、授权范围、状态码以及回调地址等,确保了授权过程的安全和有效性
     * 
     * @return string 返回构建好的中转代理URL
     */
    public function getProxyURL()
    {
        // 构建请求参数数组,包含必要的授权信息
        $params = [
            'appid' => $this->config['app_id'],
            'response_type' => $this->config['response_type'],
            'scope' => $this->config['scope'],
            'state' => $this->config['state'],
            'return_uri' => $this->config['callback'],
        ];
        // 将参数数组转换为查询字符串,并追加到中转代理URL上
        return $this->config['proxy_url'] . '?' . http_build_query($params);
    }

    /**
     * 获取当前授权用户的openid
     * 
     * 该方法用于获取微信用户的身份标识,即openid
     * 在调用之前,需要确保用户已经授权,并且通过OAuth2.0的流程获得了访问令牌(access token)
     * 如果已经获得了token,则直接从token中提取openid返回;如果没有获得或token中不包含openid,则抛出异常
     * 
     * @return mixed|string 当前授权用户的openid
     * @throws \Exception 如果没有获取到微信用户ID,则抛出异常
     */
    public function openid()
    {
        // 尝试获取token,这一步可能涉及到与微信服务器的通信
        $this->getToken();
        // 检查token中是否包含openid,如果包含则返回openid
        if (isset($this->token['openid'])) {
            return $this->token['openid'];
        } else {
            // 如果token中不包含openid,则抛出异常,提示没有获取到微信用户ID
            throw new \Exception('没有获取到微信用户ID!');
        }
    }

    /**
     * 获取格式化后的用户信息
     * 
     * 该方法通过调用原始的用户信息获取方法,并对返回的信息进行处理和格式化,以得到一个标准化的用户信息数组
     * 这包括了对用户头像URL的处理,以确保指向最小尺寸的图片;以及根据用户的性别信息,转换为内部使用的性别标识
     * 最终返回的用户信息包含openid、unionid、渠道标识、昵称、性别和头像URL等字段
     *
     * @return mixed|array 格式化后的用户信息数组,包含openid、unionid、channel、nick、gender和avatar字段
     */
    public function userinfo()
    {
        // 调用原始方法获取未处理的用户信息
        $rsp = $this->userinfoRaw();
        // 解析并处理用户头像URL,确保其指向最小尺寸的图片
        $avatar = $rsp['headimgurl'];
        if ($avatar) {
            $avatar = \preg_replace('~\/\d+$~', '/0', $avatar);
        }
        // 格式化用户信息,包括openid、unionid、channel、nick、gender和avatar字段
        $userinfo = [
            'openid' => $this->openid(),
            'unionid' => isset($this->token['unionid']) ? $this->token['unionid'] : '',
            'channel' => 'weixin',
            'nick' => $rsp['nickname'],
            'gender' => $this->getGender($rsp['sex']),
            'avatar' => $avatar,
        ];
        return $userinfo;
    }

    /**
     * 获取原始接口返回的用户信息
     * 
     * 本函数旨在通过调用内部方法获取用户的原始信息
     * 它首先确保通过调用getToken方法来获取必要的访问令牌,然后使用这个访问令牌来呼叫外部接口,获取用户信息
     * 此函数不处理返回数据的解析或错误处理,它的目的是为了提供一个简单的方法来获取未经处理的用户信息数据流
     * 
     * @return mixed 返回调用外部接口得到的原始用户信息数据
     */
    public function userinfoRaw()
    {
        // 获取访问令牌,这是调用外部接口所需的必要步骤
        $this->getToken();
        // 使用获取的访问令牌调用userinfo接口,并返回接口的原始响应数据
        return $this->call('userinfo');
    }

    /**
     * 发起API请求
     * 
     * 本函数负责向指定的API接口发起请求,并处理请求的细节,如设置请求方法、添加必要的参数等
     * 请求成功后,会将返回的数据解析为PHP数组并返回
     * 
     * @param string $api API接口的路径
     * @param array $params 请求参数,默认为空数组
     * @param string $method 请求方法,默认为'GET'
     * @return mixed|array 解析后的API响应数据
     */
    private function call($api, $params = [], $method = 'GET')
    {
        // 将请求方法转换为大写,确保一致性
        $method = strtoupper($method);
        // 添加访问令牌和用户ID到请求参数中
        $params['access_token'] = $this->token['access_token'];
        $params['openid'] = $this->openid();
        $params['lang'] = 'zh_CN';
        // 根据请求方法和API路径以及参数发送请求,并获取响应数据
        $data = $this->$method(self::API_BASE . $api, $params);
        // 将响应数据解析为PHP数组并返回
        return json_decode($data, true);
    }

    /**
     * 根据授权界面的展示类型切换访问令牌URL
     * 
     * 本函数用于根据当前授权界面的展示类型(例如:移动设备或桌面设备),动态调整用于授权的URL
     * 对于移动设备,它会使用微信提供的移动设备授权URL;对于其他设备,它会调整配置以使用适用于网页扫码登录的scope
     * 这样做的目的是为了提供最佳的用户体验,并确保授权过程能够根据设备类型正确进行
     *
     * @return void 本函数没有返回值,它主要用于修改类内部的AuthorizeURL属性
     */
    private function switchAccessTokenURL()
    {
        // 检查当前授权界面的展示类型是否为移动设备
        if ($this->display == 'mobile') {
            // 如果是移动设备,设置AuthorizeURL为微信提供的移动设备授权URL
            $this->AuthorizeURL = 'https://open.weixin.qq.com/connect/oauth2/authorize';
        } else {
            // 如果不是移动设备,修改配置以使用适用于网页扫码登录的scope
            // 微信扫码网页登录,只支持此scope
            $this->config['scope'] = 'snsapi_login';
        }
    }

    /**
     * 获取访问令牌(AccessToken)的参数数组
     * 
     * 此方法用于构造请求访问令牌时所需的参数
     * 这些参数通常包括应用的ID、秘密、授权类型以及可能的授权码
     * 应用ID和秘密是应用程序在OAuth 2.0授权过程中用于验证其身份的关键信息
     * 授权类型指定了应用获取访问令牌的方式,而授权码则是应用在用户授权后从授权服务器获取的临时代码,用于换取访问令牌
     * 
     * @return mixed|array 包含获取访问令牌所需参数的数组
     */
    protected function accessTokenParams()
    {
        // 初始化参数数组
        $params = [
            // 应用程序的ID,用于标识请求来源的应用
            'appid' => $this->config['app_id'],
            // 应用程序的秘密,用于验证应用程序的身份
            'secret' => $this->config['app_secret'],
            // 授权类型,指定应用如何获取访问令牌
            'grant_type' => $this->config['grant_type'],
            // 用户授权代码,如果存在则添加,用于换取访问令牌
            'code' => isset($_REQUEST['code']) ? $_REQUEST['code'] : '',
        ];
        // 返回构造好的参数数组
        return $params;
    }

    /**
     * 解析access_token请求的响应
     * 
     * 本函数用于处理获取access_token接口返回的数据
     * 微信API在获取access_token时,返回的数据是以JSON格式编码的字符串
     * 本函数首先尝试将该字符串解码为PHP数组,然后检查是否包含'access_token'键
     * 如果包含,表示获取access_token成功,函数将返回解码后的数组
     * 如果不包含'access_token'键,表示获取access_token失败,函数将抛出一个异常,异常信息包含错误详情
     * 
     * @param string $token 待解析的JSON字符串,通常是获取access_token的接口返回值
     * @return mixed|array 解码后的PHP数组,包含access_token等相关信息
     * @throws \Exception 如果解析后的数据不包含access_token键,则抛出异常
     */
    protected function parseToken($token)
    {
        $data = json_decode($token, true);
        if (isset($data['access_token'])) {
            return $data;
        } else {
            throw new \Exception("获取微信 ACCESS_TOKEN 出错:{$token}");
        }
    }

    /**
     * 根据给定的性别值,转换为对应的性别代号
     * 
     * 本函数旨在提供一个统一的性别表示方法,将传入的性别值(通常为数字)
     * 转换为简写的性别代号(m表示男性,f表示女性,n表示未知)
     * 这样做的目的是为了在程序中更方便地处理性别信息,尤其是在需要将性别信息与其他数据一起存储或传输的情况下
     *
     * @param string $gender 性别值,通常为数字,表示不同的性别
     * @return mixed|string 返回对应的性别代号,可能的值为m、f或n
     */
    private function getGender($gender)
    {
        // 默认情况下,返回未知性别代号
        $return = null;
        switch ($gender) {
            case 1:
                // 当性别值为1时,表示男性,返回对应的代号m
                $return = 'm';
                break;
            case 2:
                // 当性别值为2时,表示女性,返回对应的代号f
                $return = 'f';
                break;
            default:
                // 对于其他未知的性别值,返回代号n表示未知性别
                $return = 'n';
                break;
        }
        return $return;
    }
}
