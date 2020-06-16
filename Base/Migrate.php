<?php


namespace Base;

class Migrate
{
    private string $dbPath = BOOT.'/Database/';
    private string $modelPath = BOOT.'/Models/';
    private string $ext = '.php';

    public function __construct()
    {
        if ($path = config('app', 'migrate_path')) $this->dbPath = BOOT.self::dirFormat($path);
        if ($path = config('app', 'model_path')) $this->modelPath = BOOT.self::dirFormat($path);
    }

    /**
     * 生成迁移文件
     * @param $class
     * @param bool $isCreateModel
     * @return bool
     */
    public function make($class, $isCreateModel = true)
    {
        if (!is_dir($this->dbPath)) {
            mkdir($this->dbPath,755);
        }
        $filename = $this->dbPath.date('Ymdhis').$class.$this->ext;
        $table = makeUnderline($class);
        $migrate = $this->getMigrateTemplate($table);
        file_put_contents($filename,$migrate);
        println('finish make migrate');
        if (!file_exists($modelPath = $this->modelPath.$class.$this->ext) && $isCreateModel) {
            $model = $this->getModelTemplate($table,$class);
            file_put_contents($modelPath,$model);
            println('finish make model');
        }
        return true;
    }

    /**
     * 运行迁移
     * @return int
     */
    public function run()
    {
        try {
            $list = $this->checkFiles();
            $mysql = new Mysql('migrate');
            $this->makeMigrateTable($mysql);
            $table = $mysql->select();
            $list = array_diff($list,array_column($table,'name'));
            println('start migrate...');
            foreach ($list as $filename) {
                $config = require $this->dbPath.$filename;
                if (!isset($config['action'])) throw new \Exception('action error!');
                if (!isset($config['table']) || !is_string($config['table']) || !$config['table']) throw new \Exception($filename.':config table error!');
                if (!isset($config['field']) || !is_array($config['field']) || !$config['field']) throw new \Exception($filename.':config field error!');
                switch ($config['action']) {
                    case 'create table':
                        $create = $mysql->setTable($config['table'])->setFields($config['field']);
                        if (isset($config['key']) && is_string($config['key']) && $config['key']) $create = $create->setKey($config['key']);
                        if (isset($config['index']) && is_array($config['index']) && $config['index']) $create = $create->setIndexs($config['index']);
                        if (isset($config['engine']) && is_string($config['engine']) && $config['engine']) $create = $create->setEngine($config['engine']);
                        if (isset($config['charset']) && is_string($config['charset']) && $config['charset']) $create = $create->setCharset($config['charset']);
                        $create->createTable();
                        println('create '.$config['table'].' complete！');
                        break;

                    case 'change field':
                        if (isset($config['add field']) && is_array($config['add field']) && $config['add field']) $mysql->commandAddField($config);
                        if (isset($config['change field']) && is_array($config['change field']) && $config['change field']) $mysql->commandChangeField($config);
                        if (isset($config['drop field']) && is_array($config['drop field']) && $config['drop field']) $mysql->commandDropField($config);
                        break;

                    case "rename table":
                        $mysql->commandRenameTable($config);
                        break;

                    default:
                        throw new \Exception('illegal action!');
                }
                $this->recordMigrate($filename);
            }
            return 1;
        } catch (\Exception $exception) {
            println($exception->getMessage());
            $name = str_replace(BOOT,'',$exception->getFile());
            $name = preg_replace('/\W/i','-',$name);
            workLog($name, $exception);
        }
    }

    /**
     * 记录迁移
     * @param $filename
     * @return bool
     * @throws \Exception
     */
    private function recordMigrate($filename)
    {
        $mysql = new Mysql('migrate');
        $mysql->create(['name'=>$filename,'time'=>time()]);
        return true;
    }

    /**
     * 检查迁移文件
     * @return array|false
     */
    private function checkFiles()
    {
        if (!file_exists($this->dbPath)) die('you have no table!');
        $default = ['.','..'];
        $list = scandir($this->dbPath);
        $list = array_diff($list,$default);
        if (!$list) die('you have no table!');
        return $list;
    }

    /**
     * 迁移记录表
     * @param Mysql $mysql
     * @return mixed
     */
    private function makeMigrateTable(Mysql $mysql)
    {
        $prefix = config('mysql','prefix');
        return $mysql->exec('CREATE TABLE IF NOT EXISTS `'.$prefix.'migrate`(
   `id` BIGINT UNSIGNED AUTO_INCREMENT,
   `name` VARCHAR(100) NOT NULL,
   `time` INT NOT NULL,
   PRIMARY KEY ( `id` )
)ENGINE=InnoDB DEFAULT CHARSET=utf8;');
    }

    /**
     * 获取迁移模板
     * @param $table
     * @return string
     */
    private function getMigrateTemplate($table)
    {
        $prefix = config('mysql', 'prefix');
        return  "<?php
return [
    'table'     =>  '{$table}',
    'action'    =>  '',
    'field'     =>  [
        'id'    =>  [
            'type'      =>  'bigint',
            'unsigned'  =>  true,
            'auto_inc'  =>  true,
        ],
        'created'=> [
            'type'      =>  'int',
        ],
        'updated'=> [
            'type'      =>  'int',
        ],
    ],
    'key'       =>  'id',
];";
    }

    /**
     * 获取model模板
     * @param $table
     * @param $class
     * @return string
     */
    private function getModelTemplate($table,$class)
    {
        return "<?php

namespace Models;

use Base\Model;

class $class extends Model
{
    protected \$table = '{$table}';
}";
    }

    public function back($num,string $file)
    {
        if ($file) {
            $config = require $this->dbPath.$file.$this->ext;
            $this->rollback($config);
        } else {
            $mysql = new Mysql('migrate');
            $list = $mysql->orderBy('id','desc')->limit($num)->select();
            foreach ($list as $row) {
                $config = require $this->dbPath.$row['name'];
                $this->rollback($config);
            }
        }
    }

    private function rollback($config)
    {
        println('Rollback start!');

        println('Rollback finish!');
    }
}
