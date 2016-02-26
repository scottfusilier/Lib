<?php

namespace Lib\Container;

class AppContainer
{
    static protected $objects = [];

    public static function register($instance)
    {
        $className = (new \ReflectionClass($instance))->getShortName();

        if (isset(self::$objects[$className])) {
            throw new \RuntimeException('class already registered');
        }

        return self::$objects[$className] = $instance;
    }

    public static function getInstance($class)
    {
        if (isset(self::$objects[$class])) {
            return self::$objects[$class];
        }

        $obj = new $class;

        return self::$objects[$class] = $obj;
    }
}
