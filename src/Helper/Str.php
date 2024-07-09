<?php

declare(strict_types=1);

namespace think\OAuth2\Helper;

class Str
{
    /**
     * 将给定字符串的第一个字符转换为大写,并将其余字符转换为小写.
     * 
     * 此函数用于处理字符串,确保它们以大写字符开始,同时其余部分为小写.
     * 这对于遵守某些命名约定或格式化文本非常有用.
     * 
     * @param string $str 要处理的字符串.
     * @return string 返回处理后的字符串,第一个字符大写,其余字符小写.
     */
    public static function uFirst($str)
    {
        return ucfirst(strtolower($str));
    }
    /**
     * 构建参数字符串
     * 该方法用于将参数数组构建为一个查询字符串,可以选项是否对参数进行URL编码,以及排除某些参数.
     *
     * @param array $params 参数数组,包含需要构建到字符串中的键值对.
     * @param bool $urlencode 是否对参数值进行URL编码,默认为false表示不编码.
     * @param array $except 一个数组,包含需要从参数字符串中排除的键名.
     * @return string 返回构建好的参数字符串,参数之间用"&"分隔.
     */
    public static function buildParams($params, $urlencode = false, $except = ['sign'])
    {
        // 初始化参数字符串
        $param_str = '';
        // 遍历参数数组
        foreach ($params as $k => $v) {
            // 检查当前参数键是否在排除列表中,如果是,则跳过
            if (in_array($k, $except)) {
                continue;
            }
            // 连接参数键和等号
            $param_str .= $k . '=';
            // 根据$urlencode参数值决定是否对参数值进行URL编码
            $param_str .= $urlencode ? rawurlencode($v) : $v;
            // 连接下一个参数的前缀"&"
            $param_str .= '&';
        }
        // 移除最后一个"&",得到最终的参数字符串
        return rtrim($param_str, '&');
    }
    /**
     * 生成随机字符串
     * 
     * 该方法用于生成指定长度的随机字符串.随机字符串由大写字母、小写字母和数字组成,
     * 可用于生成密码、令牌或其他需要随机字符串的场景.
     * 
     * @param int $length 随机字符串的长度,默认为16.
     * @return string 生成的随机字符串.
     */
    public static function random($length = 16)
    {
        // 定义包含所有可能字符的字符串池
        $str_pol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        // 打乱字符串池中的字符顺序,并返回指定长度的子字符串
        return substr(str_shuffle($str_pol), 0, $length);
    }
    /**
     * 获取客户端IP地址
     * 
     * 本函数用于获取当前请求的客户端的IP地址.首先尝试从$_SERVER数组中获取REMOTE_ADDR键对应的值,
     * 这是最直接的获取客户端IP的方式.如果该值不存在,则尝试通过getenv函数获取REMOTE_ADDR的环境变量值.
     * 如果所有尝试都失败,函数将默认返回127.0.0.1,即本地回环地址.
     * 
     * @return string 客户端的IP地址
     */
    public static function getClientIP()
    {
        // 默认值为本地回环地址,用于在无法获取真实客户端IP时使用
        $ip = '127.0.0.1';
        // 检查$_SERVER数组中是否含有REMOTE_ADDR键,该键通常由Web服务器设置,包含客户端的IP地址
        if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
            $ip = $_SERVER['REMOTE_ADDR'];
            // 如果上述尝试失败,尝试通过getenv函数获取REMOTE_ADDR的环境变量值,这是另一种获取客户端IP的方式
        } else if (getenv('REMOTE_ADDR')) {
            $ip = getenv('REMOTE_ADDR');
        }
        // 返回获取到的IP地址,可能是客户端的真实IP,也可能是默认的回环地址
        return $ip;
    }
}
