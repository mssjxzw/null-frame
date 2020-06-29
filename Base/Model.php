<?php


namespace Base;


class Model
{
    protected  $timestamp = true;
    protected  $created = 'created';
    protected  $updated = 'updated';
    private  $original = false;
    private  $type = 'obj';
    private static  $mysqls = [];

    public function __call($name, $arguments)
    {
        $mysql_action = ['table','field','where','limit','offset','join','select','first','find','create','createAll','update','updateDiff','deleteId','delete','exec','fetchSql','isFetchSql','explain','isExplain'];
        if (in_array($name, $mysql_action)) {
            if (!property_exists($this, 'table')) {
                throw new \Exception('Unset table attributes!', 500);
            }
            $class = get_class($this);
            if (!isset(self::$mysqls[$class])) {
                self::$mysqls[$class] = new Mysql($this->table);
            }
            $mysql = self::$mysqls[$class];
            switch (true) {
                case (($name == 'create' || $name == 'update') && $this->timestamp):
                    $arguments[0][$this->created] = $arguments[0][$this->updated] = time();
                    break;

                case ($name == 'createAll' && $this->timestamp):
                    foreach ($arguments[0] as &$argument) {
                        $argument[$this->created] = $argument[$this->updated] = time();
                    }
                    break;
            }
            $result = $mysql->$name(...$arguments);
            if ($name == 'updateDiff' && $this->timestamp) {
                $mysql->update([$this->updated=>time()]);
            }
            if (($mysql->isFetchSql() && $name !== 'exec') or $mysql->isExplain()) return $result;
            switch (true) {
                case ($name == 'first' || $name == 'find'):
                    $return = $this->makeArrtibute($result);
                    break;

                case ($name == 'select'):
                    $return = [];
                    foreach ($result as $value) {
                        $return[] = $this->makeArrtibute($value);
                    }
                    break;

                case ($name == 'exec'):
                    $return = $result;
                    break;

                default:
                    if (is_object($result)) {
                        self::$mysqls[$class] = $result;
                        $return = $this;
                    } else {
                        $return = $result;
                    }
            }
            return $return;
        }
    }

    private function makeArrtibute(array $array)
    {
        if ($this->type !== 'obj') return $array;
        $obj = clone $this;
        foreach ($array as $key => $value) {
            $obj->$key = $value;
        }
        if ($this->timestamp) {
            $create_field = $this->created;
            $update_field = $this->updated;
            $create_text = $this->created.'_text';
            $update_text = $this->updated.'_text';
            $obj->$create_text = date('Y-m-d H:i:s', $obj->$create_field);
            $obj->$update_text = date('Y-m-d H:i:s', $obj->$update_field);
        }
        if ($obj->original == false) $obj = $obj->makeAppend($obj);
        return $obj;
    }

    private function makeAppend(Model $obj)
    {
        $appends = property_exists($obj,'append')?$obj->append:[];
        foreach ($appends as $append) {
            $attr_method = 'append'.convertUnderline($append);
            if (method_exists($obj, $attr_method)) {
                $attr = $obj->$attr_method();
                $obj->$append = $attr;
            }
        }
        return $obj;
    }

    public function getOriginal()
    {
        $this->original = true;
        return $this;
    }

    public function setTypeArray()
    {
        $this->type = 'array';
        return $this;
    }

}
