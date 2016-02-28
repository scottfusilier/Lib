<?php
namespace Lib\Container;

class AppContainer
{
    static protected $objects = [];

    public static function isRegistered($className)
    {
        return isset(self::$objects[$className]);
    }

    public static function register($instance)
    {
        $className = (new \ReflectionClass($instance))->getShortName();

        if (self::isRegistered($className)) {
            throw new \RuntimeException('class already registered');
        }

        return self::$objects[$className] = $instance;
    }

    public static function getInstance($class)
    {
        if (self::isRegistered($class)) {
            return self::$objects[$class];
        }

        $obj = new $class;

        return self::$objects[$class] = $obj;
    }
}
