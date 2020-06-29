<?php

namespace Base;

class Request
{
    private static $self;
    private  $path;
    private  $method;
    private  $query;
    private  $body;
    private  $header;
    private  $rest;

    private function __construct()
    {
        $this->path = $_REQUEST['s'] ?? '/';
        $this->method = $_SERVER['REQUEST_METHOD'];
        $g = $_GET;
        if (isset($g['s'])) unset($g['s']);
        $this->query = $this->filterPunc($g);
        $this->body = $_POST;
        $this->header = $this->fetchHeader();
        $this->rest = [];
    }

    private function __clone()
    {
    }

    /**
     * @return Request
     */
    public static function getObj()
    {
        if (!self::$self instanceof self) {
            self::$self = new self();
        }
        return self::$self;
    }

    /**
     * @return string
     */
    public static function getPath()
    {
        return self::getObj()->path;
    }

    /**
     * @param string $field
     * @return array|mixed
     */
    public static function getHeader(string $field = '')
    {
        return ($field === '')?self::getObj()->header:self::getObj()->header[$field];
    }

    /**
     * @return mixed
     */
    public static function getMethod()
    {
        return self::getObj()->method;
    }

    /**
     * @param string $field
     * @return mixed
     */
    public static function getQuery(string $field = '')
    {
        return ($field === '')?self::getObj()->query:self::getObj()->query[$field];
    }

    /**
     * @param string $field
     * @return array|mixed
     */
    public static function getRestQuery(string $field = '')
    {
        return ($field === '')?self::getObj()->rest:self::getObj()->rest[$field];
    }

    /**
     * @param $rest
     * @return Request
     */
    public static function setRestQuery($rest)
    {
        $self = self::getObj();
        $self->rest = $rest;
        self::$self = $self;
        return $self;
    }

    /**
     * @param string $field
     * @return mixed
     */
    public static function getBody(string $field = '')
    {
        return ($field === '')?self::getObj()->body:self::getObj()->body[$field];
    }

    /**
     * @param array $data
     * @return array
     */
    private function filterPunc(array $data)
    {
        foreach ($data as $key => $value) {
            $value = preg_replace('/[[:punct:]]/i','', $value);
            $value = preg_replace('/\s/i','', $value);
            $data[$key] = $value;
        }
        return $data;
    }

    /**
     * @return array
     */
    private function fetchHeader()
    {
        $header = [];
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) == 'HTTP_') {
                $name = convertUnderline(strtolower(substr($key, 5)));
                $header[$name] = $value;
            }
        }
        return $header;
    }
}
