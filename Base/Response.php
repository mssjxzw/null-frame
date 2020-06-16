<?php


namespace Base;


class Response
{
    private static $obj;
    public int $code = 200;
    public string $message = 'success';
    private string $root_path = '/Views/';
    public array $data = [];
    public string $type = 'json';
    public string $view_path;

    private function __construct()
    {
        if ($path = config('app', 'view_path')) $this->root_path = self::dirFormat($path);
    }

    private function __clone()
    {
    }

    public static function getObj()
    {
        if (!self::$obj instanceof self) {
            self::$obj = new self();
        }
        return self::$obj;
    }

    private function send()
    {
        switch ($this->type)
        {
            case 'json':
                return json_encode(['code'=>$this->code, 'message'=>$this->message, 'data'=>$this->data]);
                break;

            case 'view':
                require $this->view_path;
                return null;
                break;

            default:
                return $this->data;
        }
    }

    private function failureJson($code = 500,string $message = '')
    {
        $this->code = $code;
        $this->message = $message;
        $this->type = 'json';
        return $this;
    }

    private function json($data = [], $code = 0, $message = '')
    {
        if ($data) $this->data = $data;
        if ($code) $this->code = $code;
        $this->message = $message??self::getMessage($code);
        $this->type = 'json';
        return $this;
    }

    private function view($uri, $data = [])
    {
        $temp = explode('.', $uri);
        if ($temp[1] === 'html') {
            $this->view_path = BOOT.$uri;
        } else {
            $this->view_path = BOOT.$this->root_path.$uri.'.html';
        }
        $this->type = 'view';
        return $this;
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

    private static function getMessage(int $code)
    {
        return config('errorcode', $code);
    }

    private static function dirFormat(string $dir):string
    {
        return (substr($dir, -1, 1) != '/')?$dir.'/':$dir;
    }
}
