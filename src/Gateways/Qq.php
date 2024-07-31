<?php

declare(strict_types=1);

namespace think\OAuth2\Gateways;

use think\OAuth2\Connector\Gateway;

class Qq extends Gateway
{
    const API_BASE = 'https://graph.qq.com/';

    protected $AuthorizeURL = 'https://graph.qq.com/oauth2.0/authorize';
    protected $AccessTokenURL = 'https://graph.qq.com/oauth2.0/token';

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
     * 本函数根据配置信息和当前显示模式,构造用于授权登录的跳转URL
     * 它包含了授权流程中必需的参数,如响应类型、客户端ID、回调地址等
     * 
     * @return mixed|string 返回构造好的授权跳转URL
     */
    public function getRedirectUrl()
    {
        // 构造授权请求的参数数组
        $params = [
            'response_type' => $this->config['response_type'],
            'client_id' => $this->config['app_id'],
            'redirect_uri' => $this->config['callback'],
            'state' => $this->config['state'],
            'scope' => $this->config['scope'],
            'display' => $this->display,
        ];
        // 将参数数组转换为查询字符串,并追加到授权URL后返回
        return $this->AuthorizeURL . '?' . http_build_query($params);
    }

    /**
     * 获取当前授权用户的OpenID
     * 
     * 本函数旨在通过调用内部方法获取并存储当前用户的OpenID(以及可选的UnionID),如果尚未获取或存储,则通过外部接口获取
     * OpenID是一种用于标识用户的身份的唯一标识符
     * 
     * @return mixed|string 当前授权用户的OpenID
     */
    public function openid()
    {
        // 调用getToken方法来确保token的有效性
        $this->getToken();
        // 检查token中是否已经包含了openid,如果没有则进行获取
        if (!isset($this->token['openid']) || !$this->token['openid']) {
            // 调用getOpenID方法来获取用户的openid和unionid
            $userID = $this->getOpenID();
            // 将获取到的openid存储到token中
            $this->token['openid']  = $userID['openid'];
            // 如果getOpenID返回了unionid,则将其一并存储
            $this->token['unionid'] = isset($userID['unionid']) ? $userID['unionid'] : '';
        }
        // 返回当前授权用户的OpenID
        return $this->token['openid'];
    }

    /**
     * 获取格式化后的用户信息
     * 
     * 该方法从QQ登录API响应中提取并格式化用户信息
     * 它包括用户的唯一标识、昵称、性别、头像等信息
     * 头像URL通过正则表达式处理,以确保指向最大的头像版本
     * 如果用户信息中没有指定的头像版本,则回退到另一个版本
     * 
     * @return mixed|array 格式化后的用户信息数组,包含openid、unionid、channel、nick、gender和avatar字段
     */
    public function userinfo()
    {
        // 调用原始用户信息获取方法
        $rsp = $this->userinfoRaw();
        // 选择用户头像的较大版本,如果不存在,则选择较小版本
        $avatar = $rsp['figureurl_qq_2'] ?: $rsp['figureurl_qq_1'];
        // 如果头像URL存在,则替换URL中的数字后缀为0,以获取最大尺寸的头像
        if ($avatar) {
            $avatar = \preg_replace('~\/\d+$~', '/0', $avatar);
        }
        // 格式化用户信息数组,包括openid、unionid、channel、nick、gender和avatar字段
        $userinfo = [
            'openid' => $this->openid(),
            'unionid' => isset($this->token['unionid']) ? $this->token['unionid'] : '',
            'channel' => 'qq',
            'nick' => $rsp['nickname'],
            'gender' => $rsp['gender'] == "男" ? 'm' : 'f',
            'avatar' => $avatar,
        ];
        return $userinfo;
    }

    /**
     * 获取原始接口返回的用户信息
     * 
     * 本函数旨在调用指定的API接口,以获取用户信息的原始数据
     * 这里的“原始数据”指的是接口直接返回的,未经过任何处理或格式化的信息
     * 使用本函数可以获取到最直接、最详细的用户信息,以便于进一步的处理或分析
     * 
     * @return mixed 返回调用API接口所得到的原始数据,数据格式取决于接口的返回
     */
    public function userinfoRaw()
    {
        // 调用'call'方法来请求'user/get_user_info'接口,返回接口的原始响应数据
        return $this->call('user/get_user_info');
    }

    /**
     * 发起请求
     * 
     * 本函数负责向QQ API发起请求,根据请求方法和参数进行相应的处理
     * 主要包括设置请求方法、拼接请求参数、发送请求并解析响应
     * 
     * @param string $api API接口路径
     * @param array $params 请求参数,默认为空数组
     * @param string $method 请求方法,默认为'GET'
     * @return mixed|array 解析后的响应数据
     * @throws \Exception 如果请求出错,抛出异常
     */
    private function call($api, $params = [], $method = 'GET')
    {
        // 将请求方法转换为大写
        $method = strtoupper($method);
        // 添加必传参数
        $params['openid'] = $this->openid();
        $params['oauth_consumer_key'] = $this->config['app_id'];
        $params['access_token'] = $this->token['access_token'];
        $params['format'] = 'json';
        // 根据请求方法发送请求
        $data = $this->$method(self::API_BASE . $api, $params);
        // 解析响应数据
        $ret = json_decode($data, true);
        // 如果请求出错,抛出异常
        if ($ret['ret'] != 0) {
            throw new \Exception("qq获取用户信息出错:" . $ret['msg']);
        }
        // 返回请求结果
        return $ret;
    }

    /**
     * 解析access_token方法请求后的返回值
     * @param string $token 获取access_token的方法的返回值
     * @return mixed|array 包含access_token的数据数组
     * @throws \Exception 如果没有找到access_token,则抛出异常
     */
    protected function parseToken($token)
    {
        // 使用parse_str函数解析token字符串为数组
        parse_str($token, $data);
        // 检查解析后的数据中是否包含access_token
        if (isset($data['access_token'])) {
            // 如果包含access_token,则返回解析后的数据数组
            return $data;
        } else {
            // 如果不包含access_token,则抛出异常,指出获取access_token出错
            throw new \Exception("获取腾讯QQ ACCESS_TOKEN 出错:" . $token);
        }
    }

    /**
     * 通过接口获取用户的OpenID
     * 
     * 本函数通过发送HTTP请求到腾讯QQ连接API,使用访问令牌(access token)来获取用户的身份标识符OpenID
     * 如果在配置中指定了需要UnionID,函数也会尝试获取这个更全局的用户标识符
     * UnionID机制用于区分同一个用户在不同QQ互联应用中的身份,当用户在多个应用中授权时非常有用
     * 
     * @return mixed|string 返回包含OpenID(以及可能的UnionID)的数组
     * @throws \Exception 如果获取OpenID失败,抛出异常并附带错误描述
     */
    private function getOpenID()
    {
        // 创建GuzzleHTTP客户端,用于发送HTTP请求
        $client = new \GuzzleHttp\Client();
        // 构建请求参数,包括访问令牌
        $query = ['access_token' => $this->token['access_token']];
        // 如果配置中要求获取UnionID,则添加到请求参数中
        // 如果要获取unionid,先去申请:http://wiki.connect.qq.com/%E5%BC%80%E5%8F%91%E8%80%85%E5%8F%8D%E9%A6%88
        if (isset($this->config['withUnionid']) && $this->config['withUnionid'] === true) {
            $query['unionid'] = 1;
        }
        // 发送GET请求到QQ互联API的用户信息接口
        $response = $client->request('GET', self::API_BASE . 'oauth2.0/me', ['query' => $query]);
        // 从响应中提取并处理用户信息JSON字符串
        $data = $response->getBody()->getContents();
        $data = json_decode(trim(substr($data, 9), " );\n"), true);
        // 检查是否成功获取到OpenID,如果失败则抛出异常
        if (isset($data['openid'])) {
            return $data;
        } else {
            throw new \Exception("获取用户openid出错:" . $data['error_description']);
        }
    }
}
