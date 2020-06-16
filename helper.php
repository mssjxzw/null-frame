<?php
function config($name, $field = '')
{
    global $config;
    if (isset($config[$name])) {
        return $field?$config[$name][$field]:$config[$name];
    } else {
        if (!file_exists(BOOT.'/Config/'.$name.'.php')) return null;
        $config[$name] = require_once BOOT.'/Config/'.$name.'.php';
        return $field?$config[$name][$field]:$config[$name];
    }
}
function workLog($name, \Exception $exception){
    $path = BOOT . '/Logs/';
    $filename = $name.'['.date('Y-m-d').'].log';
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
    $data = '['.date('Y-m-d H:i:s').']'. $exception->getMessage() . ':' .PHP_EOL . $exception->getTraceAsString().PHP_EOL.PHP_EOL;
    file_put_contents($path.$filename, $data,FILE_APPEND);
}
function convertUnderline ( $str , $ucfirst = true)
{
    $str = ucwords(str_replace('_', ' ', $str));
    $str = str_replace(' ','',$str);
    return $ucfirst ? $str : lcfirst($str);
}
function makeUnderline ($str)
{
    return strtolower(preg_replace('/([a-z])([A-Z])/', "$1_$2", $str));
}
function println($str)
{
    print_r($str.PHP_EOL);
}
function request($field = '')
{
    $all = array_merge(\Base\Request::getQuery(),\Base\Request::getBody(),\Base\Request::getRestQuery());
    return $field?$all[$field]:$all;
}
function getHeader($field = '')
{
    $headers = \Base\Request::getHeader();
    if ($field) {
        return $headers[$field];
    } else {
        return $headers;
    }
}
function dirFormat(string $dir)
{
    return (substr($dir, -1, 1) != '/')?$dir.'/':$dir;
}
function getConfigTpl()
{
    return "<?php
return [];";
}
function dy(...$vars)
{
    foreach ($vars as $var) {
        var_dump($var);
    }
    die;
}
/**
 * [isInTime 获取随机字符串]
 * @Author mssjxzw
 * @param  [type]  $data [description]
 * @param  [type]  $k    [description]
 * @return [type]        [description]
 */
function getRandStr($long, $except = '')
{
    $str = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    if ($except) {
        $arr = array_flip(str_split($str));
        if (preg_match("/([\x81-\xfe][\x40-\xfe])/", $except, $match)) {
            $except = preg_replace("/[\\x80-\\xff]/", "", $except);
        }
        $arr_except = array_unique(str_split($except));
        foreach ($arr_except as $key => $value) {
            unset($arr[$value]);
        }
        $str = implode('', array_flip($arr));
    }
    $len = strlen($str);
    $return = '';
    for ($i = 0; $i < $long; $i++) {
        $c = mt_rand(0, $len - 1);
        $return .= substr($str, $c, 1);
    }
    return $return;
}
