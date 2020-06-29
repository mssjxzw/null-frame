<?php


namespace Base;


class Route
{
    private static $route;
    private $map = [];
    private $name = [];

    private function __construct()
    {
        if (!is_dir(BOOT.'/Config/')) mkdir(BOOT.'/Config/',755);
        if (!file_exists(BOOT.'/Config/route.php')) file_put_contents(BOOT.'/Config/route.php',$this->getConfigTemple());
        $config = config('route');
        foreach ($config as $function => $info) {
            $domain = explode('@', $function);
            $this->map[$info['method']][$info['url']] = [
                'class' =>  $domain[0],
                'function'  =>  $domain[1],
                'middleware'    =>  $info['mid']
            ];
        }
    }

    private function __clone()
    {
    }

    public static function getObj()
    {
        if (!self::$route instanceof self) {
            self::$route = new self();
        }
        return self::$route;
    }

    public static function __callStatic($name, $arguments)
    {
        $obj = self::getObj();
        return $obj->$name(...$arguments);
    }

    public function __call($name, $arguments)
    {
        return $this->$name(...$arguments);
    }

    private function getMap(string $method = '')
    {
        return ($method === '')?$this->map:$this->map[strtolower($method)];
    }

    public static function findKey($path,$routes):string
    {

        $keys = implode(',',array_keys($routes));
        $path_array = explode('/',$path);
        $loop_num = count($path_array)-1;
        for ($i = 0;$i < $loop_num; $i++) {
            $regular = self::getRegular($path_array,$i);
            if (preg_match('/'.$regular[0].'/',$keys,$result)) {
                $url = reset($result);
                self::setQuery($url,$regular[1]);
                return $url;
            }
        }
        return '';
    }

    private static function getRegular($path,$num):array
    {
        $var = [];
        $var_regular = [];
        for ($i = 0;$i < $num;$i++) {
            $var[] = array_pop($path);
            $var_regular[] = '\{\S+\}';
        }
        return [implode('\/',array_merge($path,$var_regular)),$var];
    }

    private static function setQuery($path, $var)
    {
        $path_array = explode('/', $path);
        $num = count($var);
        $query = [];
        for ($i = 0; $i < $num; $i++) {
            $name = array_pop($path_array);
            $length = strlen($name) - 2;
            $name = substr($name,1,$length);
            $query[$name] = array_shift($var);
        }
        Request::setRestQuery($query);
    }

    public static function runMiddleware(string $type, $routeMiddleware)
    {
        $global = config('middleware');
        $middlewareObjs = [];
        if ($global && isset($global[$type]) && is_array($global[$type])) $middlewareObjs = array_merge($middlewareObjs,$global[$type]);
        if ($routeMiddleware && isset($routeMiddleware[$type]) && is_array($routeMiddleware[$type])) $middlewareObjs = array_merge($middlewareObjs,$routeMiddleware[$type]);
        foreach ($middlewareObjs as $middlewareObj) {
            $obj = new $middlewareObj();
            $obj();
        }
    }

    public function getConfigTemple()
    {
        return '<?php
return [];
';
    }
}
