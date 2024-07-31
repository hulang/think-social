<?php

declare(strict_types=1);

namespace think\OAuth2\Gateways;

use think\OAuth2\Connector\Gateway;
use think\OAuth2\Helper\Str;

class Facebook extends Gateway
{
    const API_BASE = 'https://graph.facebook.com/v3.1/';

    protected $AuthorizeURL = 'https://www.facebook.com/v3.1/dialog/oauth';
    protected $AccessTokenURL = 'https://graph.facebook.com/v3.1/oauth/access_token';

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
     * 该方法用于获取当前已授权用户的唯一标识符(openid)
     * 在许多社交媒体平台或OAuth认证系统中,openID被用作用户身份的唯一标识
     * 此方法通过调用`userinfo()`方法来获取用户信息,并从中提取出openid返回
     *
     * @return mixed|string 返回当前授权用户的openid标识
     */
    public function openid()
    {
        // 调用userinfo方法获取用户信息
        $userinfo = $this->userinfo();
        // 返回用户信息中的openid字段
        return $userinfo['openid'];
    }

    /**
     * 获取格式化后的用户信息
     * 
     * 该方法从外部API获取原始用户信息后,对其进行格式化处理,以统一的结构返回
     * 主要包括用户的标识、渠道、昵称、性别、头像和邮箱等信息
     * 
     * @return mixed|array 格式化后的用户信息数组,包含openid、channel、nick、gender、avatar和email等字段
     */
    public function userinfo()
    {
        // 调用方法获取原始用户信息
        $rsp = $this->userinfoRaw();
        // 初始化用户信息数组
        $userinfo = [
            'openid' => $rsp['id'],
            // 用户的唯一标识
            // 用户信息来源渠道
            'channel' => 'facebook',
            // 用户昵称
            'nick' => $rsp['name'],
            // 根据原始信息尝试获取用户性别
            'gender' => $this->getGender($rsp),
            // 用户头像链接
            'avatar' => $this->getAvatar($rsp),
            // 用户邮箱,如果原始信息中存在
            'email' => isset($rsp['email']) ? $rsp['email'] : '',
        ];
        return $userinfo;
    }

    /**
     * 获取原始接口返回的用户信息
     * 
     * 本函数用于通过调用外部接口,获取当前用户的详细信息
     * 它首先确保有一个有效的访问令牌,然后根据配置中指定的字段,请求用户的特定数据
     * 默认请求的字段包括id, name, gender和经过特定宽度处理的图片URL
     * 
     * @return mixed 返回从外部接口获取的用户信息。信息的具体格式取决于外部接口的响应
     */
    public function userinfoRaw()
    {
        // 确保有一个有效的访问令牌
        $this->getToken();
        // 根据配置确定请求的用户字段,如果未配置,则使用默认字段
        $fields = isset($this->config['fields']) ? $this->config['fields'] : 'id,name,gender,picture.width(400)';
        // 调用外部接口获取用户信息,使用GET方法,并传递访问令牌和请求的字段
        $params = [
            'access_token' => $this->token['access_token'],
            'fields' => $fields,
        ];
        return $this->call('me', $params, 'GET');
    }

    /**
     * 发起请求到指定API
     * 
     * 本函数负责根据给定的方法和参数,向指定的API接口发起请求,并返回处理后的结果
     * 支持GET和POST请求方法
     * 
     * @param string $api API接口的路径
     * @param array $params 请求参数,默认为空数组
     * @param string $method 请求方法,默认为'GET'
     * @return mixed|array 请求结果的数组形式
     */
    private function call($api, $params = [], $method = 'GET')
    {
        // 将请求方法转换为大写,确保一致性
        $method  = strtoupper($method);
        // 构建请求数组,包括请求方法和请求URL
        $request = [
            'method' => $method,
            'uri' => self::API_BASE . $api,
        ];
        // 根据请求方法和URL以及参数发送请求,并获取响应数据
        $data = $this->$method($request['uri'], $params);
        // 解析响应数据为数组,并返回
        return json_decode($data, true);
    }

    /**
     * 构建访问令牌请求的参数数组
     * 
     * 此方法用于组装从Facebook获取访问令牌所需的请求参数。
     * 它包括了应用的ID、秘密、授权码以及回调URL。
     * 这些参数是根据Facebook OAuth授权流程的需要来配置的。
     * 
     * @return mixed|array 返回一个包含请求参数的数组
     */
    protected function accessTokenParams()
    {
        // 初始化参数数组
        $params = [
            // 应用ID,从配置中获取
            'client_id' => $this->config['app_id'],
            // 应用秘密,从配置中获取
            'client_secret' => $this->config['app_secret'],
            // 如果存在授权码,则将其添加到参数中,否则为空字符串
            'code' => isset($_REQUEST['code']) ? $_REQUEST['code'] : '',
            // 回调URL,从配置中获取
            'redirect_uri' => $this->config['callback'],
        ];
        // 返回构建好的参数数组
        return $params;
    }

    /**
     * 解析访问令牌(access_token)的响应
     * 
     * 该方法用于解析获取access_token的API调用返回的结果
     * 它首先尝试将返回的字符串解析为JSON对象,如果解析成功,它将检查是否存在错误
     * 如果存在错误,它将抛出一个包含错误消息的异常
     * 如果没有错误,它将返回解析后的token数组
     * 
     * @param string $token API调用返回的字符串,预期是JSON格式的访问令牌信息
     * @return mixed|array 解析后的JSON数据,包含访问令牌和其他相关数据
     * @throws \Exception 如果API调用返回错误信息,则抛出异常
     */
    protected function parseToken($token)
    {
        // 将返回的字符串解析为PHP数组
        $token = json_decode($token, true);
        // 检查是否存在错误信息
        if (isset($token['error'])) {
            // 如果存在错误信息,抛出异常
            throw new \Exception($token['error']['message']);
        }
        // 如果没有错误,返回解析后的token数组
        return $token;
    }

    /**
     * 根据响应数据获取性别代码
     * 
     * 该函数旨在将响应数据中的性别信息转换为简化的性别代码('m'代表男性,'f'代表女性,'n'代表未知)
     * 这样做的目的是为了统一性别表示方式,便于后续处理和使用
     * 
     * @param array $rsp 响应数据,期望其中包含'gender'键来指示性别
     * @return mixed|string 返回性别代码:'m'代表男性,'f'代表女性,'n'代表未知(当响应数据中没有指定性别时)
     */
    private function getGender($rsp)
    {
        // 检查响应数据中是否指定了性别,如果没有,则默认为未知('n')。
        $gender = isset($rsp['gender']) ? $rsp['gender'] : null;
        $return = 'n';
        // 根据获取到的性别值,转换为对应的性别代码。
        switch ($gender) {
            case 'male':
                $return = 'm';
                break;
            case 'female':
                $return = 'f';
                break;
        }
        // 返回转换后的性别代码。
        return $return;
    }

    /**
     * 获取用户头像URL
     * 
     * 该方法用于从给定的响应数据中提取用户的头像URL
     * 如果响应数据中包含了头像的URL,则直接返回该URL,否则,返回一个空字符串
     * 这有助于在不直接操作原始响应数据的情况下,方便地获取用户头像的链接
     * 
     * @param array $rsp 响应数据,期望其中包含用户的头像信息
     * @return mixed|string 用户头像的URL,如果无法提取到则返回空字符串
     */
    private function getAvatar($rsp)
    {
        // 检查响应数据中是否包含头像URL
        if (isset($rsp['picture']['data']['url'])) {
            return $rsp['picture']['data']['url'];
        }
        return '';
    }
}
