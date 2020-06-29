<?php


namespace Base;


class Mysql
{
    private static $con;
    private  $config;
    protected  $table = '';
    protected  $field = '';
    protected  $where = '';
    protected  $join = '';
    protected  $prefix = '';
    protected  $orderBy = [];
    protected  $limit = 0;
    protected  $offset = 0;
    protected  $isFetchSql = false;
    protected  $isExplain = false;
    protected  $make_table = [
        'table'         =>  '',
        'prefix'        =>  '',
        'field'         =>  [],
        'key'           =>  '',
        'index'         =>  [],
        'engine'        =>  '',
        'charset'       =>  '',
    ];
    protected  $errors = [];

    function __construct($table = '',$prefix = '')
    {
        if (!class_exists('PDO')) {
            throw new \Exception('No PDO!',500);
        }
        if (empty(self::$con)) {
            $this->connect();
        }
        $this->config = config('mysql');
        if (isset($this->config['prefix']) && $this->config['prefix']) $this->prefix = $this->config['prefix'];
        if ($prefix) $this->prefix = $prefix;
        if ($table) $this->table = $this->prefix.$table;
    }

    protected function connect($host = null, $db = null, $user = null, $password = null)
    {
        $config = config('mysql');

        if ($host) $config['host'] = $host;
        if ($db) $config['db'] = $db;
        if ($user) $config['user'] = $user;
        if ($password) $config['password'] = $password;

        $dsn = $config['type'] . ':host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['db'];
        $option = [
            \PDO::ATTR_PERSISTENT => $config['isLong']?$config['isLong']:false,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ];
        self::$con = new \PDO($dsn,$config['user'],$config['password'],$option);
        return self::$con;
    }

    public function table(string $table)
    {
        $this->table = $this->prefix.$table;;
        return $this;
    }

    public function field(...$field)
    {
        $this->field = implode(',', $field);
        return $this;
    }

    public function orderBy(...$input)
    {
        if (is_array($input[0])){
            array_merge($this->orderBy,$input[0]);
        } else {
            $this->orderBy[] = $input[0].' '.$input[1];
        }
        return $this;
    }

    public function where($where)
    {
        switch (gettype($where)) {
            case 'string':
                if ($this->where) {
                    $this->where .= " and $where";
                } else {
                    $this->where = $where;
                }
                break;
            case 'array':
                if ($this->where) {
                    $this->where .= ' and ' . implode(' and ', $where);
                } else {
                    $this->where = implode(' and ', $where);
                }
                break;

            default:
                throw new \Exception('wrong condition type!', 500);
        }
        return $this;
    }

    public function limit(int $limit)
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset)
    {
        $this->offset = $offset;
        return $this;
    }

    public function join(string $table, string $on, string $type)
    {
        switch ($type) {
            case 'left':
                $this->join .= " left join $table on $on";
                break;
            case 'right':
                $this->join .= " right join $table on $on";
                break;
            case 'inner':
                $this->join .= " inner join $table on $on";
                break;
            case 'full':
                $this->join .= " full outer join $table on $on";
                break;

            default:
                throw new \Exception('wrong type!', 500);
        }
        return $this;
    }

    public function select()
    {
        $sql = $this->selectSql();

        if ($this->isFetchSql) return $sql;
        if ($this->isExplain) $sql = 'explain '.$sql;

        if ($result = self::$con->query($sql)) {
            return  $result->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            throw new \Exception('false',500);
        }
    }

    private function selectSql()
    {
        if (!$this->field) $this->field = '*';
        $sql = 'select '.$this->field.' from '.$this->table;

        if ($this->join) $sql .= $this->join;
        if ($this->where) $sql .= ' where '.$this->where;
        if ($this->orderBy) $sql .= ' order by '.implode(',',$this->orderBy);
        if ($this->limit) $sql .= ' limit '.$this->limit;
        if ($this->offset) $sql .= ' offset '.$this->offset;

        return $sql;
    }

    public function first()
    {
        $sql = $this->selectSql();

        if ($this->isFetchSql) return $sql;
        if ($this->isExplain) $sql = 'explain '.$sql;

        if ($result = self::$con->query($sql)) {
            return  $result->fetch(\PDO::FETCH_ASSOC);
        } else {
            throw new \Exception('false',500);
        }
    }

    public function find(int $id)
    {
        $sql = 'select * from '.$this->table.' where `id` = '.$id;

        if ($this->isFetchSql) return $sql;
        if ($this->isExplain) $sql = 'explain '.$sql;

        if ($result = self::$con->query($sql)) {
            return $result->fetch(\PDO::FETCH_ASSOC);
        } else {
            throw new \Exception('false',500);
        }
    }

    public function create(array $data)
    {
        if (is_array(reset($data))) throw new \Exception('wrong data!',500);

        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            if ($value !== null) {
                $fields[] = '`'.$key.'`';
                $values[] = is_numeric($value)?$value:'\''. $value . '\'';
            }
        }

        $fields = implode(',', $fields);
        $values = implode(',', $values);
        $sql = 'insert into '.$this->table." ($fields) values ($values)";

        if ($this->isFetchSql) return $sql;

        if (self::$con->exec($sql)) {
            return self::$con->lastInsertId();
        } else {
            throw new \Exception('false',500);
        }
    }

    public function createAll(array $data)
    {
        if (!is_array(reset($data))) throw new \Exception('wrong data!',500);

        foreach ($data as $key => $value) {
            foreach ($value as $k => $v) {
                if ($v !== null) {
                    $fields[] = '`'.$k.'`';
                    $values[$key][] = is_numeric($v)?$v:'\''. $v . '\'';
                }
            }
        }

        if (count(array_unique($fields)) != count(reset($values))) throw new \Exception('sql is wrong!', 500);

        $fields = implode(',', array_unique($fields));

        foreach ($values as $value) {
            $valueSqls[] = '(' . implode(',', $value) . ')';
        }
        $values = implode(',', $valueSqls);

        $sql = 'insert into '.$this->table." ($fields) value $values";

        if ($this->isFetchSql) return $sql;

        if ($num = self::$con->exec($sql)) {

            for ($i = 0,$id=self::$con->lastInsertId(); $i < $num; $i++) {
                $result[] = $id;
                $id = $id+1;
            }
            if (isset($result)) {
                return $result;
            } else {
                return $num;
            }
        } else {
            throw new \Exception('false',500);
        }
    }

    public function lastId()
    {
        return self::$con->lastInsertId();
    }

    public function update(array $data)
    {
        if (is_array(reset($data))) throw new \Exception('wrong data!',500);

        foreach ($data as $key => $value) {
            $set[] = "$key=$value";
        }

        $setSql = implode(',', $set);
        $sql = 'update '.$this->table." set $setSql";

        if ($this->where) $sql .= ' where '.$this->where;

        if ($this->isFetchSql) return $sql;

        if ($result = self::$con->exec($sql)) {
            return $result;
        } else {
            throw new \Exception('false',500);
        }
    }

    public function updateDiff(array $data)
    {
        if (!is_array(reset($data))) throw new \Exception('wrong data!',500);

        $sql = 'update '.$this->table.' set';
        $num = 0;

        foreach ($data as $field => $updates) {
            if ($num > 0) $sql .= ',';
            $sql .= " $field = case";

            foreach ($updates as $where => $value) {
                $sql .= " when $where then $value";
            }

            $sql .= ' end';
            $num++;
        }

        if ($this->where) $sql .= ' where '.$this->where;

        if ($this->isFetchSql) return $sql;

        if ($result = self::$con->exec($sql)) {
            return $result;
        } else {
            throw new \Exception('false',500);
        }
    }

    public function deleteId(...$ids)
    {
        if (is_array(reset($ids))) $ids = implode(',', reset($ids));
        if (is_array($ids)) $ids = implode(',', $ids);
        $sql = 'delete from '.$this->table." where id in ($ids)";

        if ($this->isFetchSql) return $sql;

        if ($result = self::$con->exec($sql)) {
            return $result;
        } else {
            throw new \Exception('false',500);
        }
    }

    public function delete()
    {
        $sql = 'delete from '.$this->table;
        if ($this->where) $sql .= " where $this->where";
        if ($this->limit) $sql .= " limit $this->limit";
        if ($this->offset) $sql .= " offset $this->offset";

        if ($this->isFetchSql) return $sql;

        if ($result = self::$con->exec($sql)) {
            return $result;
        } else {
            throw new \Exception('false',500);
        }
    }

    public function exec($sql)
    {
        $sql = $this->filterComment($sql);
        return self::$con->exec($sql);
    }

    public function fetchSql()
    {
        $this->isFetchSql = true;
        return $this;
    }

    public function explain()
    {
        $this->isExplain = true;
        return $this;
    }

    public function isExplain()
    {
        return $this->isExplain;
    }

    public function isFetchSql()
    {
        return $this->isFetchSql;
    }

    public function reBuild()
    {
        return $this->exec('truncate table '.$this->table);
    }

    public function showColumns()
    {
        return self::$con->query('SHOW COLUMNS FROM `'.$this->table.'`')->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function setField(string $field,array $config):object
    {
        if (!$field || !$config) return (object)[];
        $this->make_table['field'][$field] = $config;
        return $this;
    }

    public function setFields(array $fields):object
    {
        if (!$fields) return (object)[];
        $this->make_table['field'] = array_merge($this->make_table['field'],$fields);
        return $this;
    }

    public function setTable(string $table):object
    {
        if (!$table) return (object)[];
        $this->make_table['table'] = $table;
        return $this;
    }

    public function setIndex(string $field,string $type):object
    {
        if (!$field || !$type) return (object)[];
        $this->make_table['index'][$field] = $type;
        return $this;
    }

    public function setIndexs(array $indexs):object
    {
        if (!$indexs) return (object)[];
        $this->make_table['index'] = array_merge($this->make_table['index'],$indexs);
        return $this;
    }

    public function setEngine(string $engine):object
    {
        if (!$engine) return (object)[];
        $this->make_table['engine'] = $engine;
        return $this;
    }

    public function setCharset($charset):object
    {
        if (!$charset) return (object)[];
        $this->make_table['charset'] = $charset;
        return $this;
    }

    public function setKey(string $key):object
    {
        if (!$key) return (object)[];
        $this->make_table['key'] = $key;
        return $this;
    }

    public function setPrefix(string $prefix):object
    {
        if (!$prefix) return (object)[];
        $this->make_table['prefix'] = $prefix;
        return $this;
    }

    public function createTable():bool
    {
        if (!$this->make_table['prefix']) $this->make_table['prefix'] = $this->config['prefix']??'';
        $sql = 'CREATE TABLE `'.$this->make_table['prefix'].$this->make_table['table'].'`(';

        if (!$this->make_table['field']) {
            $this->error[] = $this->make_table['prefix'].$this->make_table['table'].':field don\'t be empty!';
            return false;
        }
        foreach ($this->make_table['field'] as $field => $info) {
            $sql .= " `$field` ".$info['type'];
            if (isset($info['length']) && $info['length']) $sql .= '('.$info['length'].')';
            if (isset($info['unsigned']) && $info['unsigned']) $sql .= ' UNSIGNED';
            if (isset($info['need']) && $info['need']) $sql .= ' NOT NULL';
            if (isset($info['default']) && $info['default'] !== null) $sql .= ' default "'.$info['default'].'"';
            if (isset($info['auto_inc']) && $info['auto_inc']) $sql .= ' AUTO_INCREMENT';
            if (isset($info['comment']) && $info['comment']) $sql .= ' COMMENT \''.$info['comment'].'\'';
            $sql .= ',';
        }

        if (isset($this->make_table['index']) && $this->make_table['index'] && is_array($this->make_table['index'])) {
            foreach ($this->make_table['index'] as $field => $type) {
                $sql .= " $type $field($field),";
            }
        }

        if (isset($this->make_table['key']) && $this->make_table['key']) $sql .= ' PRIMARY KEY ( `'.$this->make_table['key'].'` ),';
        $sql = substr($sql,0,-1);
        $sql .= ')';

        if (isset($this->make_table['engine']) && $this->make_table['engine']) {
            $sql .= ' ENGINE='.$this->make_table['engine'];
        } elseif (isset($this->config['engine']) && $this->config['engine']) {
            $sql .= ' ENGINE='.$this->config['engine'];
        } else {
            $sql .= ' ENGINE=InnoDB';
        }

        if (isset($this->make_table['charset']) && $this->make_table['charset']) $sql .= ' DEFAULT CHARSET='.$this->make_table['charset'];

        self::$con->exec($sql);
        return true;
    }

    public function addFields(array $add)
    {
        if (!$this->make_table['prefix']) $this->make_table['prefix'] = $this->config['prefix']??'';
        $table = $this->make_table['prefix'].$this->make_table['table'];
        $add_sql = "alter table $table add (";
        $n = 0;
        foreach ($add as $field => $attributes) {
            if ($n > 0) $add_sql .= ',';
            $add_sql .= $field.' '.$attributes['type'];
            if (isset($attributes['length']) && $attributes['length']) $add_sql .= '('.$attributes['length'].')';
            if (isset($attributes['unsigned']) && $attributes['unsigned']) $add_sql .= ' UNSIGNED';
            if (isset($attributes['need']) && $attributes['need']) $add_sql .= ' NOT NULL';
            if (isset($attributes['default']) && $attributes['default'] !== null) $add_sql .= ' default "'.$attributes['default'].'"';
            if (isset($attributes['auto_inc']) && $attributes['auto_inc']) $add_sql .= ' AUTO_INCREMENT';
            if (isset($attributes['comment']) && $attributes['comment']) $add_sql .= ' COMMENT \''.$attributes['comment'].'\'';
            $n++;
        }
        $add_sql .= ')';
        self::$con->exec($add_sql);
        return true;
    }

    public function changeFields(array $change)
    {
        if (!$this->make_table['prefix']) $this->make_table['prefix'] = $this->config['prefix']??'';
        $table = $this->make_table['prefix'].$this->make_table['table'];
        $sql = "alter table $table ";
        $n = 0;
        foreach ($change as $name => $attributes) {
            if ($n > 0) $sql .= ',';
            if (isset($attributes['rename']) && $attributes['rename']) {
                $sql .= " change $name ".$attributes['rename'].' '.$attributes['type'];
            } else {
                $sql .= " change $name $name ".$attributes['type'];
            }
            if (isset($attributes['length']) && $attributes['length']) $sql .= '('.$attributes['length'].')';
            if (isset($attributes['unsigned']) && $attributes['unsigned']) $sql .= ' UNSIGNED';
            if (isset($attributes['need']) && $attributes['need']) $sql .= ' NOT NULL';
            if (isset($attributes['default']) && $attributes['default'] !== null) $sql .= ' default "'.$attributes['default'].'"';
            if (isset($attributes['auto_inc']) && $attributes['auto_inc']) $sql .= ' AUTO_INCREMENT';
            if (isset($attributes['comment']) && $attributes['comment']) $sql .= ' COMMENT \''.$attributes['comment'].'\'';
        }
        self::$con->exec($sql);
        return true;
    }

    public function dropFields(array $drop)
    {
        if (!$this->make_table['prefix']) $this->make_table['prefix'] = $this->config['prefix']??'';
        $table = $this->make_table['prefix'].$this->make_table['table'];
        $sql = "alter table $table";
        foreach ($drop as $key => $field) {
            if ($key > 0) $sql .= ',';
            $sql .= ' drop '.$field;
        }
        self::$con->exec($sql);
        return true;
    }

    public function getErrors():array
    {
        return $this->errors;
    }

    public function fetchFirstError()
    {
        return reset($this->errors);
    }

    public function renameTable($name)
    {
        if (!$this->make_table['prefix']) $this->make_table['prefix'] = $this->config['prefix']??'';
        $table = $this->make_table['prefix'].$this->make_table['table'];
        $rename = $this->make_table['prefix'].$name;
        self::$con->exec("ALTER TABLE $table RENAME TO $rename");
        return true;
    }

    public function dropTables(string $table)
    {
        if (!$this->make_table['prefix']) $this->make_table['prefix'] = $this->config['prefix']??'';
        $prefix = $this->make_table['prefix'];
        $table = explode(',', $table);
        $table = array_map(function ($value) use ($prefix) {
            return $prefix.$value;
        },$table);
        $table = implode(',',$table);
        self::$con->exec("DROP TABLE $table");
        return true;
    }

    private function filterComment($input)
    {
        $input = str_replace('/', '', $input);
        $input = str_replace('#', '', $input);
        $input = str_replace('-', '', $input);
        return $input;
    }

    private function filterQuotationMarks($input)
    {
        $input = str_replace('\'', '', $input);
        $input = str_replace('"', '', $input);
        return $input;
    }

    private function filterBlack($input)
    {
        $input = preg_replace('/\s/i','', $input);
        return $input;
    }

}
