<?php


namespace Base;


class FileSystem
{
    private int $size_limit;
    private array $type_limit = [];
    private array $extension = [];
    private string $path;

    public function __construct($ini = [])
    {
        if (isset($ini['size'])) {
            $this->setSize($ini['size']);
        } else {
            $this->setSize(config('filesystem','size'));
        }

        if (isset($ini['type'])) {
            $this->setType($ini['type']);
        } else {
            $this->setType(config('filesystem','type'));
        }

        if (isset($ini['ext'])) {
            $this->setExtension($ini['ext']);
        } else {
            $this->setExtension(config('filesystem','ext'));
        }

        if (isset($ini['path']) && $ini['path']) {
            $this->setPath(self::dirFormat($ini['path']));
        } elseif ($path = config('filesystem','path')) {
            $this->setPath($path);
        } else {
            $this->path = '/Public/uploads/';
        }
    }

    public static function getDirList(string $dir_uri, string $filter = '', bool $isDetail = false):array
    {
        $dir = BOOT.self::dirFormat($dir_uri);
        $return = [];
        if (!is_dir($dir)) return $return;
        $list = scandir($dir);
        foreach ($list as $key => $name) {
            if ($key > 1) {
                $type = filetype($dir.$name);
                if ($isDetail) {
                    $return[$type][$name] = self::getInfo($dir_uri.$name);
                } else {
                    $return[$type][] = $name;
                }
            }
        }
        return $filter?$return[$filter]:$return;
    }

    public static function getInfo($filename):array
    {
        $filename = BOOT.$filename;
        if (!file_exists($filename)) return [];
        $info = pathinfo($filename);
        $info['interview_time'] = fileatime($filename);
        $info['update_time'] = filemtime($filename);
        $info['size'] = filesize($filename);
        $info['type'] = filetype($filename);
        $info['auth'] = substr(sprintf("%o",fileperms($filename)),-4);
        return $info;
    }

    private static function dirFormat(string $dir):string
    {
        return (substr($dir, -1, 1) != '/')?$dir.'/':$dir;
    }

    public function setPath($path)
    {
        $this->path = self::dirFormat((string)$path);
        return $this;
    }

    public function setSize($size)
    {
        $this->size_limit = (int)$size;
        return $this;
    }

    public function setType($type)
    {
        $var_type = gettype($type);
        switch (true) {
            case ($var_type == 'string' && $type):
                $this->type_limit[] = $type;
                break;

            case ($var_type == 'array'):
                $this->type_limit = array_unique(array_merge($this->type_limit,$type));
                break;
        }
        return $this;
    }

    public function setExtension($type)
    {
        $var_type = gettype($type);
        switch (true) {
            case ($var_type == 'string' && $type):
                $this->extension[] = $type;
                break;

            case ($var_type == 'array'):
                $this->extension = array_unique(array_merge($this->type_limit,$type));
                break;
        }
        return $this;
    }

    public function uplodaImages(string $var)
    {
        return $this->upload($var,true);
    }

    public function upload(string $var, bool $is_img = false)
    {
        $prefix = 'image/';
        $file = $_FILES[$var];
        $type_limit = $is_img?['image/gif','image/jpeg','image/jpg','image/pjpeg','image/x-png','image/png']:$this->type_limit;
        if (!file_exists(BOOT.$this->path)) mkdir(BOOT.$this->path);

        foreach ($file['name'] as $key => $name) {
            $type = $file['type'][$key];
            $temp = explode(".", $name);
            $extension = end($temp);

            if ($file['error'][$key]) {
                return ['res'=>false,'message'=>'File error!','upload'=>[]];
            }

            if ($type_limit && !in_array($type, $type_limit)) {
                return ['res'=>false,'message'=>'file type error!','upload'=>[]];
            }

            if ($this->extension && !in_array($extension, $this->extension)) {
                return ['res'=>false,'message'=>'file extension error!','upload'=>[]];
            }

            if ($this->size_limit && $file['size'][$key] > $this->size_limit) {
                return ['res'=>false,'message'=>'File can not exceed ' . $this->size_limit,'upload'=>[]];
            }
            $dayPath = self::dirFormat(BOOT.$this->path.date('Ymd'));
            if (!file_exists($dayPath)) mkdir($dayPath);
            $uploadPath = self::getMoveName($dayPath,$extension);
            move_uploaded_file($file['tmp_name'][$key],$uploadPath['path']);
            $uploadList[] = self::dirFormat(date('Ymd')).$uploadPath['name'];
        }
        return  ['res'=>true,'message'=>'','upload'=>$uploadList??[],'path'=>$this->path];
    }

    public function download($filename,$name = '')
    {
        //对中文文件应该进行转码
        //$file_name=iconv("utf-8","gb2312",$file_name);
        $file_path=BOOT.$this->path.$filename;
        $temp = explode(".", $filename);
        $extension = end($temp);
        if(!file_exists($file_path)){
            return false;
        }
        $fp=fopen($file_path,"r");
        //获取下载文件的大小
        $file_size=filesize($file_path);
        //返回的文件
        if(in_array($extension, ['jpeg','png','gif'])) {
            header("Content-type:image/$extension");
        } elseif (in_array($extension, ['jpg','jpe'])) {
            header("Content-type:image/jpeg");
        }else{
            header("Content-type:application/octet-stream");
        }
        //按照字节大小返回
        header("Accept-Ranges:bytes");
        //返回文件大小
        header("Accept-Length:$file_size");
        //这里客户端弹出的对话框，对应的文件名
        header("Content-Disposition:attachment;filename=".$name??$filename);
        //向客户端回送数据
        $buffer=1024;
        $file_count=0;
        //这句话用于判断文件是否结束
        while(!feof($fp) && ($file_size-$file_count>0)){
            $file_data=fread($fp,$buffer);
            //统计读了多少个字节
            $file_count+=$buffer;
            echo $file_data; //将数据完整的输出
        }
        //关闭文件
        fclose($fp);
        return true;
    }

    private static function getMoveName($dir,$extension)
    {
        $name = uniqid(rand(0,9999));
        if (file_exists($dir.$name.'.'.$extension)) {
            return self::getMoveName($dir,$extension);
        } else {
            return ['path'=>$dir.$name.'.'.$extension, 'name'=>$name.'.'.$extension];
        }
    }

    public function __call($name, $arguments)
    {
        return self::$name(...$arguments);
    }
}
