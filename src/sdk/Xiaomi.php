<?php

declare(strict_types=1);

namespace think\OAuth2\sdk;

use think\OAuth2\Social;

class Xiaomi extends Social
{
    /**
     * 获取requestCode的api接口
     * @var mixed|string
     */
    protected $GetRequestCodeURL = 'https://account.xiaomi.com/oauth2/authorize';

    /**
     * 获取access_token的api接口
     * @var mixed|string
     */
    protected $GetAccessTokenURL = 'https://account.xiaomi.com/oauth2/token';

    /**
     * 获取request_code的额外参数,可在配置中修改 URL查询字符串格式
     * @var mixed|string
     */
    protected $Authorize = 'scope=1';

    /**
     * API根路径
     * @var mixed|string
     */
    protected $ApiBase = 'https://open.account.xiaomi.com/';

    /**
     * 组装接口调用参数 并调用接口
     * @param string $api 小米 API
     * @param string $param 调用API的额外参数
     * @param string $method HTTP请求方法 默认为GET
     * @return mixed|array|json
     */
    public function call($api, $param = '', $method = 'GET', $multi = false)
    {
        /* 小米 调用公共参数 */
        $params = [
            'token' => $this->Token['access_token'],
            'clientId' => $this->AppKey
        ];
        $data = $this->http($this->url($api), $this->param($params, $param), $method);
        return json_decode($data, true);
    }

    /**
     * 解析access_token方法请求后的返回值
     * @param string $result 获取access_token的方法的返回值
     * @return mixed|array
     * @throws \Exception
     */
    protected function parseToken($result, $extend)
    {
        $data = json_decode($result, true);
        if ($data['access_token'] && $data['token_type']) {
            $this->Token = $data;
            $data['openid'] = $this->openid();
            return $data;
        } else {
            throw new \Exception('获取 小米 ACCESS_TOKEN出错:未知错误');
        }
    }

    /**
     * 获取当前授权应用的openid
     * @return mixed|string
     * @throws \Exception
     */
    public function openid($unionid = false)
    {
        if ($unionid) {
            return $this->unionid();
        }
        $data = $this->call('user/profile');
        if ($data['result'] == 'ok') {
            $data = $data['data'];
            if (isset($data['userId'])) {
                return $data['userId'];
            } else {
                return $this->unionid();
            }
        } else {
            throw new \Exception('没有获取到 小米 用户openid!');
        }
    }

    /**
     * 获取当前授权应用的unionid
     * @return mixed|string
     * @throws \Exception
     */
    public function unionid()
    {
        $data = $this->call('user/profile');
        $data = $data['data'];
        if (isset($data['unionId'])) {
            return $data['unionId'];
        } else {
            throw new \Exception('没有获取到 小米 用户unionid!');
        }
    }
}
