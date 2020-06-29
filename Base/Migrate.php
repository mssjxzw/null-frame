<?php


namespace Base;

class Migrate
{
    private $dbPath = BOOT.'/Database/';
    private $modelPath = BOOT.'/Models/';
    private $ext = '.php';

    public function __construct()
    {
        if ($path = config('app', 'migrate_path')) $this->dbPath = BOOT.self::dirFormat($path);
        if ($path = config('app', 'model_path')) $this->modelPath = BOOT.self::dirFormat($path);
    }

    /**
     * 生成迁移文件
     * @param $action
     * @param $class
     * @param bool $isCreateModel
     * @return bool
     */
    public function make($action, $class, $isCreateModel = true)
    {
        if (!is_dir($this->dbPath)) {
            mkdir($this->dbPath,755);
        }
        $filename = $this->dbPath.date('Ymdhis').convertUnderline($action).$class.$this->ext;
        $table = makeUnderline($class);
        switch ($action) {
            case 'create':
                $migrate = $this->getCreateMigrateTemplate($table);
                break;
            case 'update':
                $migrate = $this->getUpdateMigrateTemplate($table);
                break;
            case 'rename':
                $migrate = $this->getRenameMigrateTemplate($table);
                break;
            case 'drop':
                $migrate = $this->getDropMigrateTemplate($table);
                break;
        }
        file_put_contents($filename,$migrate);
        println('finish make migrate');
        if (!file_exists($modelPath = $this->modelPath.$class.$this->ext) && $isCreateModel && $action == 'create') {
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
                switch ($config['action']) {
                    case 'create table':
                        if (!isset($config['field']) || !is_array($config['field']) || !$config['field']) throw new \Exception($filename.':config field error!');
                        $create = $mysql->setTable($config['table'])->setFields($config['field']);
                        if (isset($config['prefix']) && is_string($config['prefix']) && $config['prefix']) $create = $create->setPrefix($config['prefix']);
                        if (isset($config['key']) && is_string($config['key']) && $config['key']) $create = $create->setKey($config['key']);
                        if (isset($config['index']) && is_array($config['index']) && $config['index']) $create = $create->setIndexs($config['index']);
                        if (isset($config['engine']) && is_string($config['engine']) && $config['engine']) $create = $create->setEngine($config['engine']);
                        if (isset($config['charset']) && is_string($config['charset']) && $config['charset']) $create = $create->setCharset($config['charset']);
                        $create->createTable();
                        println('create '.$config['table'].' complete！');
                        break;

                    case 'update field':
                        $change = $mysql->setTable($config['table']);
                        if (isset($config['prefix']) && is_string($config['prefix']) && $config['prefix']) $change = $change->setPrefix($config['prefix']);
                        if (isset($config['add field']) && is_array($config['add field']) && $config['add field']) $change->addFields($config['add field']);
                        if (isset($config['change field']) && is_array($config['change field']) && $config['change field']) $change->changeFields($config['change field']);
                        if (isset($config['drop field']) && is_array($config['drop field']) && $config['drop field']) $change->dropFields($config['drop field']);
                        break;

                    case "rename table":
                        if (!isset($config['rename']) || !is_string($config['rename']) || !$config['rename']) throw new \Exception($filename.':config rename error!');
                        $rename = $mysql->setTable($config['table']);
                        if (isset($config['prefix']) && is_string($config['prefix']) && $config['prefix']) $rename = $rename->setPrefix($config['prefix']);
                        $rename->renameTable($config['rename']);
                        println('rename '.$config['table'].' complete！');
                        break;

                    case 'drop table':
                        $drop = $mysql;
                        if (isset($config['prefix']) && is_string($config['prefix']) && $config['prefix']) $drop = $drop->setPrefix($config['prefix']);
                        $drop->dropTables($config['table']);
                        println('drop '.$config['table'].' complete！');
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
    private function getCreateMigrateTemplate($table)
    {
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
     * 获取更新字段模板
     * @param $table
     * @return string
     */
    private function getUpdateMigrateTemplate($table)
    {
        return  "<?php
return [
    'table'     =>  '{$table}',
    'action'    =>  'update field',
    'add'       =>  [
        'example'    =>  [
            'type'      =>  'bigint',
            'unsigned'  =>  true,
            'auto_inc'  =>  true,
        ],
    ],
    'change'    =>  [
        'example'    =>  [
            'rename'      =>  'example2',
            'type'      =>  '',
        ],
    ],
    'drop'      =>  ['example1','example2','example3'],
];";
    }

    /**
     * 获取重命名模板
     * @param $table
     * @return string
     */
    private function getRenameMigrateTemplate($table)
    {
        return  "<?php
return [
    'table'     =>  '{$table}',
    'action'    =>  'rename table',
    'rename'    =>  '',
];";
    }

    /**
     * 获取删表模板
     * @param $table
     * @return string
     */
    private function getDropMigrateTemplate($table)
    {
        return  "<?php
return [
    'table'     =>  '{$table}',
    'action'    =>  'drop table',
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
