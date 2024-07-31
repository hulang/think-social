<?php

declare(strict_types=1);

namespace think\OAuth2;

use think\OAuth2\Connector\GatewayInterface;
use think\OAuth2\Helper\Str;

abstract class OAuth
{
    /**
     * 根据指定的网关名称初始化相应的网关实例
     * 
     * 该方法用于动态加载并初始化指定的第三方登录网关类
     * 它首先通过网关名称构建类名,然后检查该类是否存在且是否实现了GatewayInterface接口
     * 如果条件满足,则实例化该类并返回其对象;否则,抛出异常指出相应的类不存在或不实现正确的接口
     * 
     * @param string $gateway 网关名称,用于构建网关类名
     * @param array|null $config 网关配置数组,用于初始化网关类
     * @return GatewayInterface 实例化后的网关类对象,实现了GatewayInterface接口
     * @throws \Exception 如果网关类不存在或不实现GatewayInterface接口,则抛出异常
     */
    protected static function init($gateway, $config = null)
    {
        // 将网关名称的首字母大写,以符合类名的命名规范
        $gateway = Str::uFirst($gateway);
        // 根据命名空间和网关名称构建完整的类名
        $class = __NAMESPACE__ . '\\Gateways\\' . $gateway;
        // 检查类是否存在
        if (class_exists($class)) {
            // 实例化类,并传入配置参数
            $app = new $class($config);
            // 检查实例是否实现了GatewayInterface接口
            if ($app instanceof GatewayInterface) {
                // 如果实现正确,返回实例对象
                return $app;
            }
            // 如果实例没有实现GatewayInterface接口,抛出异常
            throw new \Exception("第三方登录基类 [$gateway] 必须继承抽象类 [GatewayInterface]");
        }
        // 如果类不存在,抛出异常
        throw new \Exception("第三方登录基类 [$gateway] 不存在");
    }

    /**
     * 静态方法调用的魔术方法
     * 当尝试调用不存在的静态方法时,此方法会被调用
     * 它设计用于作为入口点,初始化并返回特定的网关实例
     * 
     * @param string $gateway 网关名称,用于指定要初始化的网关类
     * @param array $config 一个或多个配置参数,这些参数将传递给初始化方法
     * @return object 返回初始化后的网关对象
     */
    public static function __callStatic($gateway, $config)
    {
        // 通过动态传递参数来初始化指定的网关类
        return self::init($gateway, ...$config);
    }
}
