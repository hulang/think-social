<?php

declare(strict_types=1);

namespace think\OAuth2\Gateways;

use think\OAuth2\Connector\Gateway;

class Google extends Gateway
{
    const API_BASE = 'https://www.googleapis.com/';
    const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    protected $AccessTokenURL = 'https://www.googleapis.com/oauth2/v4/token';

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
     * 获取授权跳转URL
     * 
     * 本函数根据配置信息生成授权应用的跳转URL
     * 这个URL用于引导用户进行授权流程,授权成功后,用户将被重定向到配置中的回调URL,并带上授权代码或令牌
     * 
     * @return mixed|string 返回授权跳转的URL
     */
    public function getRedirectUrl()
    {
        // 构建授权请求的参数数组
        $params = [
            // 应用的ID
            'client_id' => $this->config['app_id'],
            // 授权后重定向的URI
            'redirect_uri' => $this->config['callback'],
            // 授权响应类型,通常是"code"或"token"
            'response_type' => $this->config['response_type'],
            // 应用请求的权限范围
            'scope' => $this->config['scope'],
            // 用于保持请求和回调的状态,防止CSRF攻击 
            'state' => $this->config['state'],
        ];
        // 使用http_build_query函数将参数数组转换为查询字符串,并返回完整的授权URL
        return self::AUTHORIZE_URL . '?' . http_build_query($params);
    }

    /**
     * 获取当前授权用户的openid标识
     * 
     * 该方法用于获取当前已授权用户的唯一标识符,即openid
     * 通过调用内部方法userinfoRaw 获取用户信息,并从中提取出用户的id字段作为结果返回
     * 
     * @return mixed|string 返回当前授权用户的openid标识
     */
    public function openid()
    {
        // 调用userinfoRaw方法获取用户信息
        $userinfo = $this->userinfoRaw();
        // 返回用户信息中的id字段,即用户的openid
        return $userinfo['id'];
    }

    /**
     * 获取格式化后的用户信息
     * 
     * 该方法从外部服务(如Google)获取用户的原始信息,并将其格式化为内部使用的标准格式
     * 这样做的目的是为了统一不同来源的用户数据,便于后续的处理和使用
     * 
     * @return mixed|array 格式化后的用户信息,包括openid、channel、nick、email、gender和avatar
     */
    public function userinfo()
    {
        // 从外部服务获取原始用户信息
        $rsp = $this->userinfoRaw();

        // 初始化格式化后的用户信息数组
        $userinfo = [
            // 用户的唯一标识
            'openid' => $rsp['id'],
            // 用户信息来源渠道
            'channel' => 'google',
            // 用户昵称
            'nick' => $rsp['name'],
            // 用户邮箱,如果未提供则为空字符串
            'email' => isset($rsp['email']) ? $rsp['email'] : '',
            // 用户性别,如果未提供则默认为'n'
            'gender' => isset($rsp['gender']) ? $this->getGender($rsp['gender']) : 'n',
            // 用户头像链接
            'avatar' => $rsp['picture'],
        ];

        // 返回格式化后的用户信息
        return $userinfo;
    }

    /**
     * 获取原始接口返回的用户信息
     * 
     * 本函数用于通过OAuth 2.0授权流程后,获取用户的详细信息
     * 它首先确保令牌(token)的有效性,然后调用特定的API端点来获取用户信息
     * 返回的信息是未经处理的原始数据,可供进一步的解析和使用
     * 
     * @return mixed 返回从API调用中获取的原始用户信息
     */
    public function userinfoRaw()
    {
        // 检查并获取令牌,确保后续API调用的授权有效性
        $this->getToken();
        // 调用API获取用户信息,URL路径为'oauth2/v2/userinfo'
        return $this->call('oauth2/v2/userinfo');
    }

    /**
     * 发起API请求
     * 
     * 本函数负责向指定的API端点发送请求,并处理响应
     * 支持GET和POST请求方法,通过调用不同的HTTP方法来实现
     * 
     * @param string $api API端点的路径
     * @param array $params 请求参数,默认为空数组
     * @param string $method 请求方法,默认为'GET'
     * @return mixed|array 解析后的JSON响应数据
     */
    private function call($api, $params = [], $method = 'GET')
    {
        // 将请求方法转换为大写,确保一致性
        $method  = strtoupper($method);
        // 设置请求头,包含授权令牌
        $headers = [
            'Authorization' => 'Bearer ' . $this->token['access_token'],
        ];
        // 根据请求方法发送请求,并获取响应
        $data = $this->$method(self::API_BASE . $api, $params, $headers);
        // 解析JSON响应,并以数组形式返回
        return json_decode($data, true);
    }

    /**
     * 解析访问令牌的响应
     * 
     * 本函数用于处理获取访问令牌后的响应,尝试从中提取有效的访问令牌
     * 如果响应中包含有效的访问令牌,则返回整个响应数据
     * 如果响应不包含访问令牌,则抛出一个异常,指示获取访问令牌失败
     * 
     * @param string $token 获取访问令牌的响应字符串,预期是一个JSON格式的字符串
     * @return mixed|array 包含访问令牌和其他相关信息的数组
     * @throws \Exception 如果无法获取有效的访问令牌,则抛出异常
     */
    protected function parseToken($token)
    {
        // 将响应字符串解析为PHP数组
        $data = json_decode($token, true);
        // 检查解析后的数据中是否包含访问令牌
        if (isset($data['access_token'])) {
            // 如果包含访问令牌,则返回整个数据数组
            return $data;
        } else {
            // 如果不包含访问令牌,则抛出异常,指出获取访问令牌失败
            throw new \Exception("获取谷歌 ACCESS_TOKEN 出错:{$token}");
        }
    }

    /**
     * 根据传入的性别字符串,转换为简化的性别标识
     * 
     * 该方法旨在将输入的性别字符串(如"male"或"female")转换为单个字符表示,以便于后续处理或存储
     * 默认情况下,如果输入的性别不符合预期的值,则返回'n'表示未定义或未知性别
     *
     * @param string $gender 性别字符串,预期为"male"或"female"
     * @return mixed|string 返回简化的性别标识,'m'表示男性,'f'表示女性,'n'表示未知或未定义
     */
    private function getGender($gender)
    {
        // 默认返回值为'n',代表未知或未定义性别
        $return = 'n';
        switch ($gender) {
            case 'male':
                // 当输入为"male"时,转换为'm'
                $return = 'm';
                break;
            case 'female':
                // 当输入为"female"时,转换为'f'
                $return = 'f';
                break;
        }
        return $return;
    }
}
