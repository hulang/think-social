<?php

declare(strict_types=1);

namespace think\OAuth2\Gateways;

use think\OAuth2\Connector\Gateway;
use think\OAuth2\Helper\Str;

class Twitter extends Gateway
{
    const API_BASE = 'https://api.twitter.com/';

    private $tokenSecret = '';

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
     * 此方法用于根据配置信息初始化与客户端请求相关的参数
     * 特别是OAuth认证中所需的token信息,以及用户ID和用户名等
     * 这些参数对于建立与客户端的安全连接至关重要
     *
     * @return void
     */
    private function clientParams()
    {
        // 如果配置中存在oauth_token,将其赋值给token数组
        if (isset($this->config['oauth_token']) && !empty($this->config['oauth_token'])) {
            $this->token['oauth_token'] = $this->config['oauth_token'];
        }
        // 如果配置中存在oauth_token_secret,将其赋值给token数组和tokenSecret变量
        if (isset($this->config['oauth_token_secret']) && !empty($this->config['oauth_token_secret'])) {
            $this->token['oauth_token_secret'] = $this->config['oauth_token_secret'];
            $this->tokenSecret                 = $this->config['oauth_token_secret'];
        }
        // 如果配置中存在user_id,将其赋值给token数组
        if (isset($this->config['user_id']) && !empty($this->config['user_id'])) {
            $this->token['user_id'] = $this->config['user_id'];
        }
        // 如果配置中存在screen_name,将其赋值给token数组
        if (isset($this->config['screen_name']) && !empty($this->config['screen_name'])) {
            $this->token['screen_name'] = $this->config['screen_name'];
        }
    }

    /**
     * 得到跳转地址
     * 本函数用于获取OAuth授权后的重定向URL
     * 它首先向服务提供商请求一个临时的OAuth令牌,然后构造并返回一个包含该令牌的授权URL,用户将被重定向到这个URL进行授权过程
     *
     * @return mixed|string 返回构造好的授权URL,用于重定向用户进行OAuth授权
     */
    public function getRedirectUrl()
    {
        // 向OAuth服务提供商请求一个临时的请求令牌,用于后续的授权流程
        $oauthToken = $this->call('oauth/request_token', ['oauth_callback' => $this->config['callback']], 'POST');
        // 构造授权URL,包含刚才请求到的请求令牌,用户将被重定向到这个URL进行授权
        return self::API_BASE . 'oauth/authenticate?oauth_token=' . $oauthToken['oauth_token'];
    }

    /**
     * 获取当前授权用户的openid标识
     * 
     * 该方法用于获取当前已授权用户的唯一标识符,即openid
     * openid是一种在互联网上标识用户身份的方式,它是由开放身份验证(OpenID)提供的一种标准方法
     * 通过调用此方法,应用程序可以获取到用户的openid,从而实现用户的身份验证和授权
     * 
     * @return mixed|string 返回当前授权用户的openid标识
     */
    public function openid()
    {
        // 调用userinfoRaw方法获取用户信息的原始数据
        $data = $this->userinfoRaw();
        // 从用户信息数据中提取并返回用户的openid标识
        return $data['id_str'];
    }

    /**
     * 获取格式化后的用户信息
     * 
     * 该方法从原始用户数据中提取特定字段,格式化为统一的用户信息数组
     * 主要用于处理来自Twitter的用户数据,将其转换为内部系统可以识别的格式
     * 
     * @return mixed|array 格式化后的用户信息数组,包含openid、channel、nick、gender和avatar字段
     */
    public function userinfo()
    {
        // 调用userinfoRaw方法获取原始用户数据
        $data = $this->userinfoRaw();
        // 初始化返回数组,包含用户的关键信息
        $return = [
            // 用户的唯一标识符
            'openid' => $data['id_str'],
            // 数据来源渠道,此处为Twitter
            'channel' => 'twitter',
            // 用户的昵称
            'nick' => $data['name'],
            // Twitter不提供性别信息,此处设为默认值'n'
            'gender' => 'n',
            // 用户的头像URL
            'avatar' => $data['profile_image_url_https'],
        ];
        // 返回格式化后的用户信息
        return $return;
    }

    /**
     * 获取原始接口返回的用户信息
     * 
     * 本方法用于通过OAuth令牌获取特定用户的详细信息
     * 如果尚未获取到令牌,则会尝试获取令牌,并处理可能的错误
     * 一旦获得令牌,将使用它来发出请求以获取用户信息
     * 
     * @return mixed 返回包含用户信息的响应,通常是JSON格式
     * @throws \Exception 如果获取Twitter ACCESS_TOKEN出错,则抛出异常
     */
    public function userinfoRaw()
    {
        // 检查是否已经获得了令牌,如果没有,则尝试获取
        if (!$this->token) {
            $this->token = $this->getAccessToken();
            // 如果令牌中包含oauth_token_secret,则保存它
            if (isset($this->token['oauth_token_secret'])) {
                $this->tokenSecret = $this->token['oauth_token_secret'];
            } else {
                // 如果获取令牌过程中出错,抛出异常
                throw new \Exception("获取Twitter ACCESS_TOKEN 出错：" . json_encode($this->token));
            }
        }
        // 使用令牌发起请求,获取用户信息
        return $this->call('1.1/users/show.json', $this->token, 'GET', true);
    }

    /**
     * 发起API请求
     * 
     * 本函数负责构造请求并发送到指定的API端点
     * 它支持GET和POST请求方法,并可以处理返回的JSON数据
     * 请求过程包括构建OAuth参数、计算签名、设置请求头等步骤
     * 
     * @param string $api API端点的路径
     * @param array $params 请求参数,可以为空
     * @param string $method 请求方法,默认为GET
     * @param bool $isJson 是否期望返回JSON格式的数据,默认为false
     * @return mixed|array 返回处理后的响应数据
     */
    private function call($api, $params = [], $method = 'GET', $isJson = false)
    {
        // 将请求方法转换为大写
        $method = strtoupper($method);
        // 构建基础的请求信息
        $request = [
            'method' => $method,
            'uri' => self::API_BASE . $api,
        ];
        // 构建OAuth参数
        $oauthParams = $this->getOAuthParams($params);
        // 计算并添加OAuth签名
        $oauthParams['oauth_signature'] = $this->signature($request, $oauthParams);
        // 设置Authorization头部信息
        $headers = ['Authorization' => $this->getAuthorizationHeader($oauthParams)];
        // 发送请求并获取响应数据
        $data = $this->$method($request['uri'], $params, $headers);
        // 如果期望返回JSON格式的数据,则进行解码
        if ($isJson) {
            return json_decode($data, true);
        }
        // 否则,将响应数据解析为数组并返回
        parse_str($data, $data);
        return $data;
    }

    /**
     * 构建OAuth认证所需的参数数组
     * 
     * 该方法用于生成OAuth认证过程中的必要参数,这些参数包括了OAuth协议规定的若干必选参数
     * 以及根据当前实例配置和环境生成的动态参数,如oauth_nonce和oauth_timestamp等
     * 
     * @param array $params 用户自定义的OAuth参数数组,这些参数将被合并到默认参数数组中
     * @return mixed|array 返回包含默认参数和用户自定义参数合并后的OAuth参数数组
     */
    private function getOAuthParams($params = [])
    {
        // 定义默认的OAuth参数数组,包含了OAuth认证过程中必需的参数
        $_default = [
            // 应用的标识符,从配置中获取
            'oauth_consumer_key' => $this->config['app_id'],
            // 随机生成的字符串,用于防止重放攻击
            'oauth_nonce' => Str::random(),
            // OAuth签名算法,目前固定为HMAC-SHA1
            'oauth_signature_method' => 'HMAC-SHA1',
            // 当前时间戳,用于确保请求的有效性
            'oauth_timestamp' => $this->timestamp,
            // 用户令牌,此处留空,可能在后续过程中获取并填充
            'oauth_token' => '',
            // OAuth协议版本号,目前版本为1.0
            'oauth_version' => '1.0',
        ];
        // 将用户自定义参数与默认参数数组合并,返回最终的OAuth参数数组
        return array_merge($_default, $params);
    }

    /**
     * 签名操作
     * 用于对请求进行签名验证,确保请求的安全性和完整性
     * 签名过程包括对参数进行排序、拼接,并使用密钥进行哈希计算
     *
     * @param array $request 包含请求方法和URI的数据数组
     * @param array $params 请求中额外的参数数组
     * @return string 返回计算得到的签名字符串
     */
    private function signature($request, $params = [])
    {
        // 对参数数组进行排序,确保签名的稳定性
        ksort($params);
        // 构建参数字符串,为签名做准备
        $sign_str = Str::buildParams($params, true);
        // 拼接请求方法、URI和参数字符串,形成待签名的字符串
        $sign_str = $request['method'] . '&' . rawurlencode($request['uri']) . '&' . rawurlencode($sign_str);
        // 构建签名密钥,结合了应用密钥和令牌密钥
        $sign_key = $this->config['app_secret'] . '&' . $this->tokenSecret;
        // 使用HMAC-SHA1算法对待签名字符串进行哈希计算,并进行Base64编码,最后对编码后的结果进行URL编码
        return rawurlencode(base64_encode(hash_hmac('sha1', $sign_str, $sign_key, true)));
    }

    /**
     * 构建OAuth授权头部信息
     * 
     * 本函数用于生成OAuth协议所需的授权头部信息
     * OAuth是一种用于授权的开放标准,它允许用户让第三方应用访问该用户在另一服务上的资源,而无需将用户名密码透露给第三方应用
     * 
     * @param array $params OAuth参数数组
     * @return string 授权头部信息字符串
     */
    private function getAuthorizationHeader($params)
    {
        // 初始化返回的授权信息字符串
        $return = 'OAuth ';
        // 遍历参数数组,构建授权头信息
        foreach ($params as $k => $param) {
            // 将参数键值对加入到授权信息字符串中,键值对之间用逗号和空格分隔
            $return .= $k . '="' . $param . '", ';
        }
        // 移除最后一个逗号和空格,确保字符串格式正确
        return rtrim($return, ', ');
    }

    /**
     * 通过OAuth获取访问令牌
     * 
     * 本函数负责通过OAuth协议从授权服务器获取访问令牌(access token)
     * 在OAuth流程中,这一步通常是在用户成功授权后,将授权信息发送到应用服务器以获取访问令牌
     * 对于Twitter这样的OAuth 1.0a服务,这一步是获取长期访问权限的关键步骤
     * 
     * @return mixed|array 返回包含访问令牌信息的数组,例如访问令牌本身、令牌密钥、令牌类型等
     */
    protected function getAccessToken()
    {
        // 使用POST方法调用'oauth/access_token'接口,参数来自$_GET全局数组
        // 这里解释了为什么使用POST方法：因为按照OAuth协议的规定,获取访问令牌通常需要发送一些敏感信息,使用POST方法可以增加安全性
        return $this->call('oauth/access_token', $_GET, 'POST');
    }
}
