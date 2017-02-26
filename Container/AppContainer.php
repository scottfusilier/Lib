<?php
namespace Lib\Container;

class AppContainer
{
    private static $objects = [];
    private static $callables = [];

    public static function has($key)
    {
        return !empty(self::$callables[$key]);
    }

    public static function register($key, Callable $value)
    {
         return self::$callables[$key] = $value;
    }

    public static function get($key)
    {
        if (empty(self::$callables[$key])) {
            throw new \Exception('key ' . $key . ' not registered in container');
        }
        if (!empty(self::$objects[$key])) {
            return self::$objects[$key];
        }
        $callable = self::$callables[$key];
        return self::$objects[$key] = $callable();
    }
}
