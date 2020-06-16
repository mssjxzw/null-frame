<?php


namespace Base;


class RSA
{

    private $public_key_resource; //公钥资源
    private $private_key_resource; //私钥资源


    public function __construct($public_key = '',$private_key = '')
    {
        $private_key_path = config('app','rsa_private')?config('app','rsa_private'):'/Config/Rsa/rsa_private_key.pem';
        $public_key_path = config('app','rsa_public')?config('app','rsa_public'):'/Config/Rsa/rsa_public_key.pem';
        $this->public_key_resource = !empty($public_key) ? openssl_pkey_get_public($this->get_public_key($public_key)) : file_get_contents(BOOT.$public_key_path);
        $this->private_key_resource = !empty($private_key) ? openssl_pkey_get_private($this->get_private_key($private_key)) : file_get_contents(BOOT.$private_key_path);
    }

    /**
     * 获取私有key字符串 重新格式化  为保证任何key都可以识别
     * @param $private_key
     * @return bool|string
     */
    public function get_private_key($private_key)
    {
        if (!$private_key) return false;
        $search = [
            "-----BEGIN RSA PRIVATE KEY-----",
            "-----END RSA PRIVATE KEY-----",
            "\n",
            "\r",
            "\r\n"
        ];

        $private_key=str_replace($search,"",$private_key);
        return $search[0] . PHP_EOL . wordwrap($private_key, 64, "\n", true) . PHP_EOL . $search[1];
    }


    /**
     * 获取公共key字符串  重新格式化 为保证任何key都可以识别
     * @param $public_key
     * @return bool|string
     */

    public function get_public_key($public_key)
    {
        if (!$public_key) return false;
        $search = [
            "-----BEGIN PUBLIC KEY-----",
            "-----END PUBLIC KEY-----",
            "\n",
            "\r",
            "\r\n"
        ];
        $public_key=str_replace($search,"",$public_key);
        return $search[0] . PHP_EOL . wordwrap($public_key, 64, "\n", true) . PHP_EOL . $search[1];
    }

    /**
     * 生成一对公私钥 成功返回 公私钥数组 失败 返回 false
     * @return array|false|string
     */
    public function create_key()
    {
        $res = openssl_pkey_new();
        if($res == false) return openssl_error_string();
        openssl_pkey_export($res, $private_key);
        $public_key = openssl_pkey_get_details($res);
        return array('public_key'=>$public_key["key"],'private_key'=>$private_key);
    }

    /**
     * 用私钥加密
     * @param $input
     * @return string
     */
    public function private_encrypt($input)
    {
        if (openssl_private_encrypt($input,$output,$this->private_key_resource)) {
            return base64_encode($output);
        } else {
            return false;
        }
    }

    /**
     * 解密 私钥加密后的密文
     * @param $input
     * @return mixed
     */
    public function public_decrypt($input)
    {
        if (openssl_public_decrypt(base64_decode($input),$output,$this->public_key_resource)) {
            return $output;
        } else {
            return false;
        }
    }

    /**
     * 用公钥加密
     * @param $input
     * @return string
     */
    public function public_encrypt($input)
    {
        if (openssl_public_encrypt($input,$output,$this->public_key_resource,OPENSSL_PKCS1_OAEP_PADDING)) {
            return base64_encode($output);
        } else {
            return false;
        }
    }

    /**
     * 解密 公钥加密后的密文
     * @param $input
     * @return mixed
     */
    public function private_decrypt($input)
    {
        if (openssl_private_decrypt(base64_decode($input),$output,$this->private_key_resource,OPENSSL_PKCS1_OAEP_PADDING)) {
            return $output;
        } else {
            return false;
        }
    }

    /**
     * MD5签名
     * @param $data
     * @return bool|string
     */
    public function signMd5($data)
    {
        $p_key_id = openssl_get_privatekey($this->private_key_resource);
        if (empty($p_key_id)) return false;

        openssl_sign($data, $sign, $p_key_id, OPENSSL_ALGO_MD5);
        openssl_free_key($p_key_id);

        return base64_encode($sign);
    }

    /**
     * MD5验签
     * @param $data
     * @param $sign
     * @return bool
     */
    public function verifyMD5($data,$sign)
    {
        $p_key_id = openssl_get_publickey($this->public_key_resource);
        if (empty($p_key_id)) return false;

        $res = openssl_verify(base64_decode($data), $sign, $p_key_id, OPENSSL_ALGO_MD5);

        if ($res == 1) return true;

        return false;
    }
}
