<?php


namespace Base;


abstract class Queue
{
    protected  $every_workload = 1;
    protected  $exec_time = 0;
    protected  $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public static function dispatch($data)
    {
        return new static($data);
    }

    abstract public function task();

    public function isExecutable()
    {
        return ($this->exec_time == 0 || $this->exec_time <= time())?true:false;
    }

    public function exec_time($time)
    {
        if (strtotime(date('Y-m-d H:i:s', $time)) === $time && $time > time()) $this->exec_time = $time;
        $time = strtotime($time);
        if ($time && $time > time()) $this->exec_time = $time;
        return $this;
    }

    public function delaySeconds(int $seconds)
    {
        $this->exec_time = strtotime("+$seconds seconds", $this->exec_time??time());
        return $this;
    }

    public function delayMinutes(int $minutes)
    {
        $this->exec_time = strtotime("+$minutes minutes", $this->exec_time??time());
        return $this;
    }
    public function delayHours(int $hours)
    {
        $this->exec_time = strtotime("+$hours hours", $this->exec_time??time());
        return $this;
    }

    public function delayDays(int $days)
    {
        $this->exec_time = strtotime("+$days days", $this->exec_time??time());
        return $this;
    }

    public function delayWeeks(int $weeks)
    {
        $this->exec_time = strtotime("+$weeks weeks", $this->exec_time??time());
        return $this;
    }

    public function delayMonth(int $month)
    {
        $this->exec_time = strtotime("+$month months", $this->exec_time??time());
        return $this;
    }

    public function __call($name, $arguments)
    {
        return self::$name(...$arguments);
    }

    public function __destruct()
    {
        $class = explode('\\', static::class);
        $class = array_pop($class);
        if (!isset($this->name) || empty($this->name)) $this->name = $class;
        Redis::getObj()->rPush($class,serialize($this));
    }
}
