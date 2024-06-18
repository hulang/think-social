<?php

declare(strict_types=1);

class WxProxy
{
    protected $AuthorizeURL = 'https://open.weixin.qq.com/connect/oauth2/authorize';
    /**
     * 根据请求中的参数执行相应的操作,主要是处理授权流程中的重定向
     * 如果请求中包含'code'参数,表示授权已经完成,此时将用户重定向到返回URI,并附带授权码
     * 如果请求中不包含'code'参数,表示授权流程尚未开始或正在进行中,此时构建授权请求的参数,并重定向到授权URL
     * 这个方法是整个授权流程的关键节点,负责根据不同的请求状态引导用户完成授权过程
     */
    public function run()
    {
        // 检查请求中是否包含'code'参数,用于判断授权是否已经完成
        if (isset($_GET['code'])) {
            // 授权完成,构造重定向URL,将授权码和状态码传递给客户端
            header('Location: ' . $_COOKIE['return_uri'] . '?code=' . $_GET['code'] . '&state=' . $_GET['state']);
        } else {
            // 授权未完成,构建授权请求的参数
            $protocol = $this->is_HTTPS() ? 'https://' : 'http://';
            $params = [
                'appid' => $_GET['appid'],
                'redirect_uri' => $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['DOCUMENT_URI'],
                'response_type' => $_GET['response_type'],
                'scope' => $_GET['scope'],
                'state' => $_GET['state'],
            ];
            // 设置cookie来记录返回URI,用于授权完成后重定向回客户端指定的URL
            setcookie('return_uri', urldecode($_GET['return_uri']), $_SERVER['REQUEST_TIME'] + 60, '/');
            // 构造授权URL,并重定向到授权页面,开始授权流程
            header('Location: ' . $this->AuthorizeURL . '?' . http_build_query($params) . '#wechat_redirect');
        }
    }
    /**
     * 检查当前请求是否通过HTTPS协议进行
     * 
     * 本函数通过检查不同的服务器变量来确定请求是否使用了HTTPS
     * 这是因为不同的服务器（如Apache、IIS等）在设置HTTPS时可能会使用不同的变量
     * 此外,还通过检查服务器端口是否为443（HTTPS的默认端口）来确定
     * 
     * @return bool 如果请求是通过HTTPS进行的,则返回true；否则返回false
     */
    protected function is_HTTPS()
    {
        // 检查HTTPS变量是否已设置
        if (!isset($_SERVER['HTTPS'])) {
            return false;
        }
        // 如果HTTPS变量值为1,表示使用了HTTPS（Apache服务器典型配置）
        if ($_SERVER['HTTPS'] === 1) {
            // Apache
            return true;
            // 如果HTTPS变量值为'on',表示使用了HTTPS（IIS服务器典型配置）
        } elseif ($_SERVER['HTTPS'] === 'on') {
            // IIS
            return true;
            // 如果服务器端口为443,也认为是使用了HTTPS
        } elseif ($_SERVER['SERVER_PORT'] == 443) {
            // 其他
            return true;
        }
        // 如果以上条件都不满足,则认为没有使用HTTPS
        return false;
    }
}

$app = new WxProxy();
$app->run();
