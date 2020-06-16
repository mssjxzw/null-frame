<?php


namespace Base;


abstract class RequestCheck
{
    private $required = 'the field must input!';
    private $number = 'the field must be a number!';
    private $string = 'the field must be a string!';
    private $array = 'the field must be a array!';
    abstract public function rules();
    public function check()
    {
        $rules = $this->rules();
        foreach ($rules as $field => $rule) {
            if (is_string($rule)) $rule = explode(',', $rule);
            foreach ($rule as $action) {
                $this->$action(request($field));
            }
        }
    }

    private function getMessage($field, $rule)
    {
        if (method_exists($this,'message')) {
            $message = $this->message();
            $key = "$field.$rule";
            return $message[$key];
        } else {
            return $this->$rule;
        }
    }

    private function required($field)
    {
        if (empty($field)) throw new \Exception($this->getMessage($field, 'required'));
    }

    private function number($field)
    {
        if (!is_numeric($field)) throw new \Exception($this->getMessage($field, 'number'));
    }

    private function string($field)
    {
        if (!is_string($field)) throw new \Exception($this->getMessage($field, 'string'));
    }

    private function array($field)
    {
        if (!is_array($field)) throw new \Exception($this->getMessage($field, 'array'));
    }

}
