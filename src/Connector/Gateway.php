<?php

declare(strict_types=1);

namespace think\OAuth2\Connector;

use think\OAuth2\Connector\GatewayInterface;

/**
 * 所有第三方登录必须继承的抽象类
 */
abstract class Gateway implements GatewayInterface
{
    /**
     * 配置参数
     * @var mixed|array
     */
    protected $config;

    /**
     * 当前时间戳
     * @var mixed|int
     */
    protected $timestamp;

    /**
     * 默认第三方授权页面样式
     * @var mixed|string
     */
    protected $display = 'default';

    /**
     * 第三方Token信息
     * @var mixed|array
     */
    protected $token = null;

    /**
     * 是否验证回跳地址中的state参数
     * @var mixed|bool
     */
    protected $checkState = false;

    /**
     * 构造函数
     * 
     * 用于初始化OAuth2.0客户端的配置.在实例化客户端时,必须传入配置信息,以确保客户端能够正确进行认证流程
     * 如果没有传入配置信息,将抛出一个异常,强调配置信息的必要性
     * 
     * @param array $config 配置数组,包含OAuth2.0客户端所需的各项配置参数
     * @throws \Exception 如果没有传入配置信息,抛出异常
     */
    public function __construct($config = null)
    {
        // 检查是否传入了配置信息
        if (!$config) {
            throw new \Exception('传入的配置不能为空');
        }
        // 默认配置参数,包含一些必要的OAuth2.0参数
        $_config = [
            'app_id' => '',
            'app_secret' => '',
            'callback' => '',
            'response_type' => 'code',
            'grant_type' => 'authorization_code',
            'proxy' => '',
            'state' => '',
        ];
        // 将传入的配置信息与默认配置合并,形成最终的配置
        $this->config = array_merge($_config, $config);
        // 记录当前时间,用于后续可能的时间相关操作
        $this->timestamp = time();
    }

    /**
     * 设置授权页面样式
     *
     * @param string $display
     * @return mixed|self
     */
    public function setDisplay($display)
    {
        $this->display = $display;
        return $this;
    }

    /**
     * 强制验证回跳地址中的state参数
     *
     * @return mixed|self
     */
    public function mustCheckState()
    {
        $this->checkState = true;
        return $this;
    }

    /**
     * 执行GET请求操作
     *
     * @param string $url
     * @param array $params
     * @param array $headers
     * @return mixed|string
     */
    protected function GET($url, $params = [], $headers = [])
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', $url, ['proxy' => $this->config['proxy'], 'headers' => $headers, 'query' => $params]);
        return $response->getBody()->getContents();
    }

    /**
     * 执行POST请求操作
     *
     * @param string $url
     * @param array $params
     * @param array $headers
     * @return mixed|string
     */
    protected function POST($url, $params = [], $headers = [])
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->request('POST', $url, ['proxy' => $this->config['proxy'], 'headers' => $headers, 'form_params' => $params, 'http_errors' => false]);
        return $response->getBody()->getContents();
    }

    /**
     * 默认的AccessToken请求参数
     * @return mixed|array
     */
    protected function accessTokenParams()
    {
        $params = [
            'client_id' => $this->config['app_id'],
            'client_secret' => $this->config['app_secret'],
            'grant_type' => $this->config['grant_type'],
            'code' => isset($_REQUEST['code']) ? $_REQUEST['code'] : '',
            'redirect_uri' => $this->config['callback'],
        ];
        return $params;
    }

    /**
     * 获取AccessToken
     *
     * @return mixed|string
     */
    protected function getAccessToken()
    {
        if ($this->checkState === true) {
            if (!isset($_GET['state']) || $_GET['state'] != $this->config['state']) {
                throw new \Exception('传递的STATE参数不匹配!');
            }
        }
        $params = $this->accessTokenParams();
        return $this->POST($this->AccessTokenURL, $params);
    }

    /**
     * 获取token信息
     *
     * @return mixed|void
     */
    protected function getToken()
    {
        if (empty($this->token)) {
            $token = $this->getAccessToken();
            /** @scrutinizer ignore-call */
            $this->token = $this->parseToken($token);
        }
    }
}
