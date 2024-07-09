<?php

declare(strict_types=1);

namespace think\OAuth2\Gateways;

use think\OAuth2\Connector\Gateway;
use think\OAuth2\Helper\Str;

class Alipay extends Gateway
{
    const RSA_PRIVATE = 1;
    const RSA_PUBLIC = 2;
    const API_BASE = 'https://openapi.alipay.com/gateway.do';

    protected $AuthorizeURL = 'https://openauth.alipay.com/oauth2/publicAppAuthorize.htm';
    protected $AccessTokenURL = 'https://openapi.alipay.com/gateway.do';

    /**
     * 构建授权跳转URL
     * 
     * 本函数根据配置信息生成授权应用的跳转URL,用于引导用户进行授权流程
     * URL中包含的应用ID、回调地址、授权范围和状态信息,是与授权服务端交互所必需的参数
     *
     * @return mixed|string 返回构建好的授权跳转URL
     */
    public function getRedirectUrl()
    {
        // 初始化参数数组,包含应用ID、回调地址、授权范围和状态信息
        $params = [
            'app_id' => $this->config['app_id'],
            'redirect_uri' => $this->config['callback'],
            'scope' => $this->config['scope'],
            'state' => $this->config['state'],
        ];
        // 将参数数组拼接成查询字符串,并追加到授权URL后返回
        return $this->AuthorizeURL . '?' . http_build_query($params);
    }

    /**
     * 获取当前授权用户的openid标识
     * 
     * 该方法用于获取支付宝用户的唯一标识符(openid)
     * 它首先尝试从内部token存储中获取openid,如果token中不存在openid,则抛出一个异常,表明未能从支付宝获取用户ID
     * 
     * @return mixed|string 当前授权用户的openid
     * @throws \Exception 如果没有获取到支付宝用户ID,则抛出异常
     */
    public function openid()
    {
        // 调用getToken方法来确保token是最新和有效的
        $this->getToken();
        // 检查token中是否包含openid,如果包含则返回openid
        if (isset($this->token['openid'])) {
            return $this->token['openid'];
        } else {
            // 如果token中不包含openid,则抛出异常
            throw new \Exception('没有获取到支付宝用户ID！');
        }
    }

    /**
     * 获取格式化后的用户信息
     * 
     * 本函数通过调用userinfoRaw方法获取原始用户信息,然后对这些信息进行格式化处理
     * 最终返回一个包含用户openid、渠道、昵称、性别和头像信息的数组。
     * 这样做的目的是为了统一用户信息的格式,方便后续的处理和使用
     * 
     * @return mixed|array 格式化后的用户信息数组,包含openid、channel、nick、gender和avatar字段。
     */
    public function userinfo()
    {
        // 调用userinfoRaw方法获取原始用户信息
        $rsp = $this->userinfoRaw();
        // 初始化格式化后的用户信息数组
        $userinfo = [
            // 使用token中的openid作为用户标识
            'openid' => $this->token['openid'],
            // 设定渠道为alipay,表示该用户来自支付宝
            'channel' => 'alipay',
            // 使用原始用户信息中的昵称
            'nick' => $rsp['nick_name'],
            // 将原始用户的性别转换为小写,以统一性别信息的格式
            'gender' => strtolower($rsp['gender']),
            // 使用原始用户信息中的头像链接
            'avatar' => $rsp['avatar'],
        ];
        // 返回格式化后的用户信息
        return $userinfo;
    }

    /**
     * 获取原始接口返回的用户信息
     * 
     * 本函数旨在通过调用接口获取用户的详细信息
     * 它首先确保令牌(token)的有效性,然后发起请求以获取用户信息
     * 返回的数据是未经处理的,包含了来自接口的所有信息
     * 
     * @return mixed|array 包含用户信息的响应数组,数组中有一个键名为'alipay_user_info_share_response'
     *               其对应的值是包含用户详细信息的数组
     */
    public function userinfoRaw()
    {
        // 获取令牌,确保后续接口调用的授权
        $this->getToken();
        // 调用'alipay.user.info.share'接口来获取用户信息
        $rsp = $this->call('alipay.user.info.share');
        // 返回包含用户信息的响应部分
        return $rsp['alipay_user_info_share_response'];
    }

    /**
     * 发起请求的私有方法
     * 
     * 该方法用于封装请求过程,支持不同的请求方法(如POST、GET),并自动处理签名及编码问题
     * 主要包括以下几个步骤：
     * 1. 根据传入的请求方法,转换为大写字母格式
     * 2. 构建请求的基本参数,包括app_id、method、charset、sign_type、timestamp、version及auth_token
     * 3. 将传入的参数与基本参数合并
     * 4. 生成签名,并添加到参数中
     * 5. 根据请求方法,发送请求到指定的API基础URL
     * 6. 对返回的数据进行编码转换(从gbk转为utf-8),并解析为数组返回
     * 
     * @param string $api API接口名称
     * @param array $params 请求参数
     * @param string $method 请求方法,默认为POST
     * @return mixed|array 解析后的响应数据
     */
    private function call($api, $params = [], $method = 'POST')
    {
        // 转换请求方法为大写
        $method = strtoupper($method);
        // 构建请求的基础参数
        $_params = [
            'app_id' => $this->config['app_id'],
            'method' => $api,
            'charset' => 'UTF-8',
            'sign_type' => 'RSA2',
            'timestamp' => date("Y-m-d H:i:s"),
            'version' => '1.0',
            'auth_token' => $this->token['access_token'],
        ];
        // 合并传入参数和基础参数
        $params = array_merge($_params, $params);
        // 生成签名,并添加到参数中
        $params['sign'] = $this->signature($params);
        // 根据请求方法发送请求,并获取响应
        $data = $this->$method(self::API_BASE, $params);
        // 对返回的数据进行编码转换
        $data = mb_convert_encoding($data, 'utf-8', 'gbk');
        // 解析响应数据为数组并返回
        return json_decode($data, true);
    }

    /**
     * 获取访问令牌的参数数组
     * 此方法构造了请求访问令牌所需的所有参数,包括固定的参数和根据情况动态获取的授权码
     * 
     * @return mixed|array 包含访问令牌请求所需参数的数组
     */
    protected function accessTokenParams()
    {
        // 初始化参数数组
        $params = [
            // 应用的唯一标识
            'app_id' => $this->config['app_id'],
            // 接口方法名称
            'method' => 'alipay.system.oauth.token',
            // 字符编码格式
            'charset' => 'UTF-8',
            // 签名算法类型
            'sign_type' => 'RSA2',
            // 当前时间戳
            'timestamp' => date("Y-m-d H:i:s"),
            // 接口版本号
            'version' => '1.0',
            // 授权类型
            'grant_type' => $this->config['grant_type'],
            // 用户授权码,从URL参数中获取
            'code' => isset($_GET['auth_code']) ? $_GET['auth_code'] : '',
        ];
        // 生成签名并添加到参数数组
        $params['sign'] = $this->signature($params);
        // 返回构造好的参数数组
        return $params;
    }

    /**
     * 对给定的数据进行支付宝签名
     * 
     * 此函数用于生成支付宝交易的RSA签名
     * 它首先对数据数组进行排序,然后构建一个规范化查询字符串
     * 接下来,它使用私钥对这个字符串进行签名,最后返回Base64编码的签名结果
     * 
     * @param array $data 待签名的数据数组
     * @return mixed|string Base64编码的RSA签名
     * @throws \Exception 如果私钥不正确,抛出异常
     */
    private function signature($data = [])
    {
        // 对数据数组按照键名进行排序
        ksort($data);
        // 构建规范化查询字符串
        $str = Str::buildParams($data);
        // 获取RSA私钥
        $rsaKey = $this->getRsaKeyVal(self::RSA_PRIVATE);
        // 获取私钥资源
        $res = openssl_get_privatekey($rsaKey);
        // 检查私钥是否有效
        if ($res !== false) {
            // 初始化签名变量
            $sign = '';
            // 使用私钥和SHA256算法对字符串进行签名
            openssl_sign($str, $sign, $res, OPENSSL_ALGO_SHA256);
            // 返回Base64编码的签名结果
            return base64_encode($sign);
        }
        // 如果私钥无效,抛出异常
        throw new \Exception('支付宝RSA私钥不正确');
    }

    /**
     * 根据类型获取RSA密钥值
     * 本函数用于根据传入的类型参数,返回相应的RSA密钥值
     * 可以是公钥或私钥
     * 
     * @param int $type 密钥类型,默认为RSA_PUBLIC表示公钥,其他值表示私钥
     * @return mixed|string 返回相应的RSA密钥值,格式化为PEM格式
     * @throws \Exception 如果未配置支付宝RSA密钥,抛出异常
     */
    private function getRsaKeyVal($type = self::RSA_PUBLIC)
    {
        // 根据类型确定密钥名称和头部/尾部信息
        if ($type === self::RSA_PUBLIC) {
            $keyname = 'pem_public';
            $header = '-----BEGIN PUBLIC KEY-----';
            $footer = '-----END PUBLIC KEY-----';
        } else {
            $keyname = 'pem_private';
            $header = '-----BEGIN RSA PRIVATE KEY-----';
            $footer = '-----END RSA PRIVATE KEY-----';
        }
        // 从配置中获取密钥值
        $rsa = $this->config[$keyname];
        // 如果密钥值是文件路径,则读取文件内容
        if (is_file($rsa)) {
            $rsa = file_get_contents($rsa);
        }
        // 如果密钥值为空,则抛出异常
        if (empty($rsa)) {
            throw new \Exception('支付宝RSA密钥未配置');
        }
        // 移除密钥中的换行符和头部/尾部信息,为后续格式化做准备
        $rsa = str_replace([PHP_EOL, $header, $footer], '', $rsa);
        // 格式化密钥为PEM格式,每64个字符换行
        $rsaVal = $header . PHP_EOL . chunk_split($rsa, 64, PHP_EOL) . $footer;
        return $rsaVal;
    }

    /**
     * 解析access_token方法请求后的返回值
     * @param string $token 获取access_token的方法的返回值
     * @return mixed|array 解析后的token数据,包含openid和其他必要信息
     * @throws \Exception 如果解析失败,抛出异常
     */
    protected function parseToken($token)
    {
        // 将返回的token从gbk编码转换为utf-8编码,以确保字符正确解析
        $token = mb_convert_encoding($token, 'utf-8', 'gbk');
        // 解析JSON格式的token信息
        $data  = json_decode($token, true);
        // 检查是否包含alipay_system_oauth_token_response字段
        if (isset($data['alipay_system_oauth_token_response'])) {
            // 如果包含,提取用户ID并将其命名为openid,这是支付宝OAuth中用户的唯一标识
            $data = $data['alipay_system_oauth_token_response'];
            $data['openid'] = $data['user_id'];
            return $data;
        } else {
            // 如果不包含,抛出异常,说明获取access_token的过程中出现了错误
            throw new \Exception("获取支付宝 ACCESS_TOKEN 出错：{$token}");
        }
    }
}
