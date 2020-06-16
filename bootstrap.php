<?php
function load()
{
    require_once 'helper.php';
    $controller_path = '/Controllers/';
    $model_path = '/Models/';
    $validation_path = '/Request/';
    if ($path = config('app','controller_path')) $controller_path = dirFormat($path);
    if ($path = config('app','model_path')) $model_path = dirFormat($path);
    if ($path = config('app','validation_path')) $validation_path = dirFormat($path);
    define('CONTROLLERS', BOOT.$controller_path);
    define('MODELS', BOOT.$model_path);
    try {
        $map = \Base\Route::getMap(\Base\Request::getMethod());
        if ($search = \Base\Route::findKey(\Base\Request::getPath(),$map)){
            $class = str_replace('/','\\',$controller_path).$map[$search]['class'];
            $action = $map[$search]['function'];
            $validation = str_replace('/','\\',$validation_path).$map[$search]['class'];
            $check_file = str_replace('\\','/',$validation_path).'.php';
            if (file_exists(BOOT.$check_file)) {
                $checkObj = new $validation();
                $checkObj->check();
            }
            \Base\Route::runMiddleware('before',$map[$search]['middleware']);
            (new $class())->$action();
            \Base\Route::runMiddleware('after',$map[$search]['middleware']);
            echo \Base\Response::send();
        } else {
            throw new \Exception('访问失败', 404);
        }
    } catch (\Exception $exception) {
        $name = str_replace(BOOT,'',$exception->getFile());
        if ($name !== '\bootstrap.php') {
            $name = preg_replace('/\W/i','-',$name);
            workLog($name, $exception);
        }
        echo json_encode(['code'=>$exception->getCode(),'message'=>$exception->getMessage()]);
    }
}
