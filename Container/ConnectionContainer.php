<?php
namespace Lib\Container;

class ConnectionContainer
{
    private static $connections = array();

    public static function getConnection($dbConfig = array())
    {
        if (!isset(self::$connections[$dbConfig['name']])) {
            try {
                self::$connections[$dbConfig['name']] = new \PDO(implode(';', $dbConfig['dsn']),$dbConfig['login'],$dbConfig['password'],$dbConfig['options']);
            } catch (\PDOException $e) {
                die('Connection Error');
            }
        }

        return self::$connections[$dbConfig['name']];
    }

    public static function cleanUp()
    {
        foreach (self::$connections as $key => $value) {
            unset(self::$connections[$key]);
            unset($value);
        }
    }
}
