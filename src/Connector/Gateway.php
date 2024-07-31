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
     * 设置授权页面的显示样式
     * 
     * 本方法用于设定授权页面的显示方式
     * 通过传入不同的参数值,可以控制授权页面的布局和样式,提供给调用者更灵活的界面定制选项
     *
     * @param string $display 显示样式的标识符.此参数值决定了授权页面的布局和样式
     *                        具体的取值和对应的显示样式需要参考相关文档或实现
     * @return mixed|self 返回值可以是本对象的实例,也可能是其他类型的值
     *                    具体返回类型取决于方法的实现和业务逻辑的需要
     */
    public function setDisplay($display)
    {
        $this->display = $display;
        return $this;
    }

    /**
     * 设置强制验证回跳地址中的state参数
     *
     * 本方法用于配置OAuth授权流程中是否强制验证回跳地址中的state参数
     * state参数是一个用于防止CSRF攻击的随机字符串,它在授权请求中生成,并在用户授权后回跳时进行验证,确保请求的来源和流程的完整性
     *
     * @return mixed|$this
     */
    public function mustCheckState()
    {
        // 将检查state参数的标志设置为true,表示必须验证state参数
        $this->checkState = true;
        // 返回对象实例,支持链式调用
        return $this;
    }

    /**
     * 执行HTTP GET请求
     * 
     * 通过GuzzleHttp客户端发送GET请求到指定URL,允许通过参数和头部信息进行自定义
     * 主要用于从远程服务器获取数据
     * 
     * @param string $url 请求的URL地址
     * @param array $params 查询字符串参数,用于附加在URL上
     * @param array $headers HTTP请求头部信息,用于自定义请求的头部字段
     * @return mixed|string 返回GET请求的结果,作为字符串形式的响应体
     */
    protected function GET($url, $params = [], $headers = [])
    {
        // 创建GuzzleHttp客户端实例
        $client = new \GuzzleHttp\Client();
        // 发送GET请求,包括代理和头部信息在内的请求选项
        $options = [
            'proxy' => $this->config['proxy'],
            'headers' => $headers,
            'query' => $params
        ];
        $response = $client->request('GET', $url, $options);
        // 获取并返回响应的主体内容
        return $response->getBody()->getContents();
    }

    /**
     * 执行HTTP POST请求
     * 
     * 通过GuzzleHttp库发送POST请求到指定的URL,允许通过代理发送请求,并支持自定义请求头和参数
     * 主要用于需要提交数据的场景,如表单提交或API数据推送
     * 
     * @param string $url 请求的URL地址
     * @param array $params POST请求的参数,以键值对形式提供
     * @param array $headers 请求的自定义头部信息,用于设置请求头
     * @return mixed|string 返回请求结果的字符串内容
     */
    protected function POST($url, $params = [], $headers = [])
    {
        // 创建GuzzleHttp客户端实例
        $client = new \GuzzleHttp\Client();
        // 发送POST请求,包括代理设置、请求头和表单参数
        $options = [
            'proxy' => $this->config['proxy'],
            'headers' => $headers,
            'form_params' => $params,
            'http_errors' => false
        ];
        $response = $client->request('POST', $url, $options);
        // 获取并返回响应的主体内容
        return $response->getBody()->getContents();
    }

    /**
     * 构建访问令牌（AccessToken）的请求参数数组
     * 
     * 此方法用于生成请求访问令牌时所需参数的数组.这些参数包括：
     * - client_id：应用的标识符,用于识别请求的应用
     * - client_secret：应用的密钥,用于验证应用的身份
     * - grant_type：授权类型,指定如何获取访问令牌
     * - code：授权码,由授权服务器颁发,用于交换访问令牌
     * - redirect_uri：回调URL,用于接收授权码
     * 
     * @return mixed|array 返回一个包含所有必要参数的数组
     */
    protected function accessTokenParams()
    {
        // 初始化参数数组
        $params = [
            // 应用ID
            'client_id' => $this->config['app_id'],
            // 应用密钥
            'client_secret' => $this->config['app_secret'],
            // 授权类型
            'grant_type' => $this->config['grant_type'],
            // 授权码,如果存在则赋值,否则为空字符串
            'code' => isset($_REQUEST['code']) ? $_REQUEST['code'] : '',
            // 回调URL
            'redirect_uri' => $this->config['callback'],
        ];
        // 返回构建好的参数数组
        return $params;
    }

    /**
     * 获取AccessToken
     * Access Token是用于向API进行身份验证的令牌.这个方法通过发送请求来获取它
     *
     * @return mixed|string 返回获取的AccessToken,如果检查到STATE参数不匹配,则抛出异常
     * @throws \Exception 如果传入的STATE参数与配置的不一致,则抛出异常
     */
    protected function getAccessToken()
    {
        // 检查是否需要验证状态参数
        if ($this->checkState === true) {
            // 如果状态参数不存在或不匹配,则抛出异常
            if (!isset($_GET['state']) || $_GET['state'] != $this->config['state']) {
                throw new \Exception('传递的STATE参数不匹配!');
            }
        }
        // 构建获取AccessToken所需的参数
        $params = $this->accessTokenParams();
        // 使用POST方法向AccessTokenURL发送请求,返回获取的AccessToken
        return $this->POST($this->AccessTokenURL, $params);
    }

    /**
     * 获取token信息
     * 
     * 该方法用于获取当前实例的token值
     * 如果token尚未被获取或为空,则通过调用getAccessToken方法获取token,并通过parseToken方法解析token值
     * 这样设计是为了确保token的获取和解析只在必要时发生,从而提高程序的效率
     *
     * @return mixed|void 返回解析后的token值.如果没有进行过token的获取和解析操作,可能返回void
     */
    protected function getToken()
    {
        // 检查当前的token值是否已经存在且不为空
        if (empty($this->token)) {
            // 如果token不存在或为空,则调用getAccessToken方法获取token值
            $token = $this->getAccessToken();
            // 解析获取到的token值,并将解析后的结果存储在$this->token中
            /** @scrutinizer ignore-call */
            $this->token = $this->parseToken($token);
        }
    }
}
