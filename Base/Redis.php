<?php


namespace Base;


class Redis
{
    private static $obj;
    private function __construct()
    {
    }
    private function __clone()
    {
    }
    public static function getObj()
    {
        if (!self::$obj instanceof self) {
            self::$obj = new \Redis();
            self::$obj->connect('127.0.0.1', 6379);
//            self::$obj->auth('mssjxzw');
        }
        return self::$obj;
    }
}
