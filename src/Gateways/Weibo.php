<?php

declare(strict_types=1);

namespace think\OAuth2\Gateways;

use think\OAuth2\Connector\Gateway;

class Weibo extends Gateway
{
    const API_BASE = 'https://api.weibo.com/2/';

    protected $AuthorizeURL = 'https://api.weibo.com/oauth2/authorize';
    protected $AccessTokenURL = 'https://api.weibo.com/oauth2/access_token';

    /**
     * 得到跳转地址
     * 
     * 本函数用于构建授权跳转的URL
     * 在OAuth 2.0的流程中,应用需要引导用户访问授权服务器,请求用户授权
     * 这个函数根据当前配置和需要,构造了这个授权请求的URL
     * 
     * @return mixed|string 返回构建好的授权跳转URL
     */
    public function getRedirectUrl()
    {
        // 切换到使用访问令牌的URL,这在某些OAuth实现中可能是必需的
        $this->switchAccessTokenURL();
        // 构建请求参数数组
        $params = [
            // 应用的唯一标识
            'client_id' => $this->config['app_id'],
            // 授权后重定向的URI
            'redirect_uri' => $this->config['callback'],
            // 请求的权限范围
            'scope' => $this->config['scope'],
            // 用于保持请求和回调之间的状态
            'state' => $this->config['state'],
            // 展示模式,例如弹出窗口或全屏
            'display' => $this->display,
        ];
        // 使用http_build_query函数将参数数组转换为查询字符串,并返回完整的授权URL
        return $this->AuthorizeURL . '?' . http_build_query($params);
    }

    /**
     * 获取当前授权用户的OpenID
     * 
     * 本函数旨在通过已有的授权信息,获取当前用户在新浪微博的唯一标识符OpenID
     * 首先,它尝试从内部token存储中获取OpenID
     * 如果token中不存在OpenID,则表明授权信息不完整,此时抛出一个异常,提示没有获取到新浪微博用户ID
     * 
     * @return mixed|string 当前授权用户的新浪微博OpenID
     * @throws \Exception 如果没有获取到OpenID,则抛出异常
     */
    public function openid()
    {
        // 尝试获取授权令牌
        $this->getToken();
        // 检查令牌中是否包含OpenID信息
        if (isset($this->token['openid'])) {
            // 如果包含,返回OpenID
            return $this->token['openid'];
        } else {
            // 如果不包含,抛出异常
            throw new \Exception('没有获取到新浪微博用户ID!');
        }
    }

    /**
     * 获取格式化后的用户信息
     * 
     * 本函数通过调用原始的用户信息获取函数,然后格式化这些信息,以一种统一和方便使用的方式返回
     * 它主要包括用户的唯一标识、来源渠道、昵称、性别和头像信息
     * 
     * @return mixed|array 格式化后的用户信息数组,包含openid、channel、nick、gender和avatar字段
     */
    public function userinfo()
    {
        // 调用原始方法获取未格式化的用户信息
        $rsp = $this->userinfoRaw();
        // 格式化用户信息,并指定用户渠道为微博
        $userinfo = [
            'openid' => $this->openid(),
            'channel' => 'weibo',
            'nick' => $rsp['screen_name'],
            'gender' => $rsp['gender'],
            'avatar' => $rsp['avatar_hd'],
        ];
        // 返回格式化后的用户信息
        return $userinfo;
    }

    /**
     * 获取原始接口返回的用户信息
     * 
     * 本函数旨在通过调用外部接口,获取指定用户的详细信息
     * 它首先确保令牌(token)的有效性,然后向特定接口发送请求,请求参数包括用户的唯一标识符(openid)
     * 
     * @return mixed 返回接口调用的结果,包含用户的详细信息
     */
    public function userinfoRaw()
    {
        // 获取并确保令牌的有效性
        $this->getToken();
        // 调用外部接口获取用户信息,参数为用户的openid
        return $this->call('users/show.json', ['uid' => $this->openid()]);
    }

    /**
     * 发起API请求
     * 
     * 本函数负责向指定的API接口发起请求,并处理请求的细节,如设置请求方法、添加访问令牌等
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
        // 将访问令牌添加到请求参数中,用于身份验证
        $params['access_token'] = $this->token['access_token'];
        // 根据请求方法和API路径以及参数发送请求,并获取响应数据
        $data = $this->$method(self::API_BASE . $api, $params);
        // 将响应数据解析为PHP数组并返回
        return json_decode($data, true);
    }

    /**
     * 根据设备类型切换访问令牌授权URL
     * 
     * 本函数用于确定授权流程中的访问令牌授权URL
     * 根据当前设备的类型(是否为移动设备),它会切换到相应的授权URL
     * 这确保了在不同设备类型上的一致性和兼容性
     * 
     * @return void 本函数没有返回值,它主要用于修改类的内部状态(AuthorizeURL属性)
     */
    private function switchAccessTokenURL()
    {
        // 检查当前显示模式是否为移动模式
        if ($this->display == 'mobile') {
            // 如果是移动模式,设置授权URL为移动版微博的授权地址
            $this->AuthorizeURL = 'https://open.weibo.cn/oauth2/authorize';
        }
    }

    /**
     * 解析access_token方法请求后的返回值
     * @param string $token 获取access_token的方法的返回值
     * @return mixed|array 解析后的数据,包含access_token和openid
     * @throws \Exception 如果获取access_token失败,抛出异常
     */
    protected function parseToken($token)
    {
        // 解析JSON格式的返回值
        $data = json_decode($token, true);
        // 检查是否成功获取了access_token
        if (isset($data['access_token'])) {
            // 将uid重命名为openid,为了符合常见的命名习惯
            $data['openid'] = $data['uid'];
            // 移除uid字段,避免混淆
            unset($data['uid']);
            // 返回包含access_token和openid等信息的数组
            return $data;
        } else {
            // 如果未成功获取access_token,抛出异常并附带错误信息
            throw new \Exception("获取新浪微博ACCESS_TOKEN出错:{$data['error']}");
        }
    }
}
