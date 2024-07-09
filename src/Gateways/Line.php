<?php

declare(strict_types=1);

namespace think\OAuth2\Gateways;

use think\OAuth2\Connector\Gateway;
use think\OAuth2\Helper\Str;

class Line extends Gateway
{
    const API_BASE = 'https://api.line.me/v2/';

    protected $AuthorizeURL = 'https://access.line.me/oauth2/v2.1/authorize';
    protected $AccessTokenURL = 'https://api.line.me/oauth2/v2.1/token';

    /**
     * 构造函数
     *
     * 本构造函数用于初始化类的实例
     * 它接受一个可选的配置数组参数,用于设置类的各种选项
     * 如果没有提供配置数组,则使用默认配置
     *
     * @param mixed|array $config 可选的配置数组,包含初始化类所需的各种设置
     */
    public function __construct($config = null)
    {
        // 调用父类的构造函数进行初始化
        parent::__construct($config);
        // 设置客户端参数,这是初始化过程的一部分
        $this->clientParams();
    }

    /**
     * 设置客户端请求的参数
     * 
     * 此方法用于处理客户端请求时可能需要的参数配置,特别是针对需要访问令牌(access token)的情况
     * 它检查配置中是否已存在access token,并且如果不为空,则将其添加到请求的token中
     * 这样做的目的是为了确保客户端在发送请求时具有正确的认证信息,以便能够成功访问受保护的资源或执行需要授权的操作
     *
     * @return void 本方法没有返回值,它主要用于配置请求参数
     */
    private function clientParams()
    {
        // 检查配置中是否已存在access token,并且不为空
        if (isset($this->config['access_token']) && !empty($this->config['access_token'])) {
            // 如果存在且不为空,则将access token添加到token数组中
            $this->token['access_token'] = $this->config['access_token'];
        }
    }

    /**
     * 获取授权跳转的URL地址
     * 
     * 本函数根据配置信息生成授权请求的URL,用于引导用户进行授权流程
     * 授权完成后,用户将被重定向到配置中的回调URL,并带上授权代码
     * 
     * @return mixed|string 返回授权跳转的URL地址
     */
    public function getRedirectUrl()
    {
        // 组装授权请求的参数
        $params = [
            // 授权类型,通常为"code"
            'response_type' => $this->config['response_type'],
            // 应用的ID
            'client_id' => $this->config['app_id'],
            // 授权完成后重定向的URL
            'redirect_uri' => $this->config['callback'],
            // 应用请求的权限范围
            'scope' => $this->config['scope'],
            // 用于保持请求和回调之间的状态,防止CSRF攻击
            'state' => $this->config['state'] ?: Str::random(),
        ];
        // 构建授权URL,并返回
        return $this->AuthorizeURL . '?' . http_build_query($params);
    }

    /**
     * 获取当前授权用户的openid标识
     *
     * 该方法用于获取当前已授权用户的唯一标识符,即openid
     * 通过调用内部方法userinfoRaw,获取用户信息的原始数据,并从中提取出userId作为结果返回
     * 这在需要识别和跟踪用户,尤其是在与第三方登录系统集成时非常有用
     *
     * @return mixed|string 返回当前授权用户的openid标识
     */
    public function openid()
    {
        // 调用userinfoRaw方法获取用户信息的原始数据
        $rsp = $this->userinfoRaw();
        // 从原始数据中提取userId,并作为方法的结果返回
        return $rsp['userId'];
    }

    /**
     * 获取格式化后的用户信息
     * 
     * 本函数通过调用userinfoRaw方法获取原始用户信息,并对其进行格式化处理,以符合特定的数据结构要求
     * 特别注意,LINE平台不提供性别信息,因此在此处设定默认值为'n'
     * 
     * @return mixed|array 格式化后的用户信息,包括openid、channel、nick、gender和avatar等字段
     */
    public function userinfo()
    {
        // 调用原始用户信息获取方法
        $rsp = $this->userinfoRaw();
        // 初始化用户信息数组,并填充从原始响应中获取的数据
        $userinfo = [
            // 用户ID
            'openid' => $rsp['userId'],
            // 用户所处的渠道,此处固定为LINE
            'channel' => 'line',
            // 用户显示名称
            'nick' => $rsp['displayName'],
            // LINE不提供性别信息,此处设为默认值'n'
            'gender' => 'n',
            // 用户头像URL,如果存在,则添加/large以获取大图
            'avatar' => isset($rsp['pictureUrl']) ? $rsp['pictureUrl'] . '/large' : '',
        ];
        return $userinfo;
    }

    /**
     * 获取原始接口返回的用户信息
     * 
     * 本函数旨在通过调用外部接口,使用之前获取的令牌(token)来获取用户的详细信息
     * 如果接口返回错误信息,将抛出一个异常
     * 否则,将返回包含用户信息的数据
     * 
     * @throws \Exception 如果接口返回错误信息,则抛出异常
     * @return mixed|array 返回包含用户信息的数据数组
     */
    public function userinfoRaw()
    {
        // 获取访问令牌,这是调用接口的前提
        $this->getToken();
        // 使用GET方法调用'profile'接口,传入令牌获取用户信息
        $data = $this->call('profile', $this->token, 'GET');
        // 检查接口返回数据中是否包含错误信息
        if (isset($data['error'])) {
            // 如果包含错误信息,则抛出异常,异常信息为错误描述
            throw new \Exception($data['error_description']);
        }
        // 如果没有错误,返回获取到的用户信息数据
        return $data;
    }

    /**
     * 发起API请求
     * 
     * 本函数负责向指定的API端点发送请求,并处理请求的细节,如方法类型、认证信息等
     * 它封装了请求的构建和发送过程,使上层调用者能够更简单地与API进行交互
     * 
     * @param string $api API端点的路径
     * @param array $params 请求参数,默认为空数组
     * @param string $method 请求方法,默认为'GET'
     * @return mixed|array 解析后的JSON响应
     */
    private function call($api, $params = [], $method = 'GET')
    {
        // 将请求方法转换为大写,以统一处理
        $method = strtoupper($method);
        // 构建请求的基础信息,包括方法和URI
        $request = [
            'method' => $method,
            'uri' => self::API_BASE . $api,
        ];
        // 设置认证头,支持Bearer和其他类型的认证令牌
        $headers = ['Authorization' => (isset($this->token['token_type']) ? $this->token['token_type'] : 'Bearer') . ' ' . $this->token['access_token']];
        // 根据请求方法和参数发送请求,并获取响应
        // 这里假设$this->$method()是一个动态调用方法,用于发送不同HTTP方法的请求
        $data = $this->$method($request['uri'], $params, $headers);
        // 解析响应的JSON,并以数组形式返回
        return json_decode($data, true);
    }

    /**
     * 解析访问令牌(access_token)的响应
     * 
     * 该方法用于解析获取访问令牌的API调用返回的响应字符串
     * 如果返回的响应包含错误信息,将抛出一个异常
     * 否则,将返回解析后的token数组
     * 
     * @param string $token API调用返回的响应字符串,通常是一个JSON对象
     * @return mixed|array 解析后的token数据,包含access_token和其他相关数据
     * @throws \Exception 如果响应中包含错误信息,则抛出异常
     */
    protected function parseToken($token)
    {
        // 解析JSON响应字符串为数组
        $token = json_decode($token, true);
        // 检查响应中是否包含错误信息
        if (isset($token['error'])) {
            // 如果包含错误信息,抛出异常
            throw new \Exception($token['error_description']);
        }
        // 如果没有错误,返回解析后的token数据
        return $token;
    }
}
