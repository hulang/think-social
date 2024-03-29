<?php

/**
 * 第三方登陆实例抽象类
 *
 */

declare(strict_types=1);

namespace think\OAuth2;

use think\OAuth2\Connector\GatewayInterface;
use think\OAuth2\Helper\Str;

abstract class OAuth
{
    protected static function init($gateway, $config = null)
    {
        $gateway = Str::uFirst($gateway);
        $class   = __NAMESPACE__ . '\\Gateways\\' . $gateway;
        if (class_exists($class)) {
            $app = new $class($config);
            if ($app instanceof GatewayInterface) {
                return $app;
            }
            throw new \Exception("第三方登录基类 [$gateway] 必须继承抽象类 [GatewayInterface]");
        }
        throw new \Exception("第三方登录基类 [$gateway] 不存在");
    }

    public static function __callStatic($gateway, $config)
    {
        return self::init($gateway, ...$config);
    }
}
