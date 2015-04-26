<?php
/**
 * Class AesPhpSimple
 * Cookie를 암호화 하기 위해 제작한 AesPhpSimple Class 입니다.
 *
 * 사용방법 : getPreferedMode을 저장하여 현재 사용환경에 맞는 AES mode를 이용하세요
 * AES CTR 모드가 지원되는 경우 CTR모드를 이용하고, 그렇지 않을경우 AES CBC모드를 이용합니다.
 *
 * sha256을 이용하여 120bit의 HMAC을 이용합니다.(Base64를 최대한으로 활용하면서도 쿠키를 최대한 줄이기 위해 120bit를 이용합니다.);
 *
 * API 안내
 * $oAES = new AesPhpSimple($mode); $mode = AES-128-CBC or AES-CBC-CTR 중 하나이며, 둘다 지원하지 않으면 -1
 *
 * $oAES =  getPreferedMode ->
 *
 * $a = new AesPhpSimple('AES-128-CBC');
 * var_dump($x = $a->encrypt('1235233.1429659834.1429659834',"iddddddddddxdsdfsdfviddddddddddxdsdfsdfv","base64_uri"));
 * var_dump($a->decrypt($x,"iddddddddddxdsdfsdfviddddddddddxdsdfsdfv","base64_uri"));
 */




Class AesPhpSimple {

    private $mode;


    /**
     * @param $mode : AES-128-CTR 또는 AES-128-CBC만 지원합니다.
     * @retrun error_code : 성공시 1 비성공시 0
     */
    function __construct($mode){
        if($mode ==='AES-128-CTR' || $mode === 'AES-128-CBC' ){
            $this->mode = $mode;
            return 0;
        }
        return -1;

    }


    /**
     * @return string :  AES-128-CTR or AES-128-CBC 만 지원합니다. 비정상시 return false
     */
    static public function getPreferedMode(){
        $arr =  openssl_get_cipher_methods();
        foreach( $arr as $key => $val){
            if($val === 'AES-128-CTR'){
                return 'AES-128-CTR';
            }
        }
        foreach( $arr as $key => $val){
            if($val === 'AES-128-CBC'){
                return 'AES-128-CBC';
            }
        }
        return null;
    }


    /**
     * @param $data : 암호화할 데이터 입니다.
     * @param $password : 암호화에 사용하는 비밀번호 입니다.
     * @param $format : format이며 현재는 base64_uri만 지원합니다.
     * @return string : method . iv . hmac. data 형태의 string을 return합니다.
     */
    public function encrypt($data, $password, $format)
    {
        $data = gzcompress($data);
        $password = hash('sha256', $password,true);

        $iv = openssl_random_pseudo_bytes(16);
        $output = openssl_encrypt($data, $this->mode, $password, OPENSSL_RAW_DATA, $iv);
        $hmac = substr(hash_hmac('sha256', $output, $password, true),0,15);

        if ($format === 'base64_uri') {
            $iv = str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode($iv));
            $hmac = str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode($hmac));
            $output = str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode($output));
        } elseif($format === 'hex'){
            // not yet
            /*
            $hmac = bin2hex($hmac);
            $output = bin2hex($output);
            $output = 'aes-128-cbc.hmacsha256.'.$output.'.'.$hmac;
            */
        }
        $output = substr($this->mode,-2,1).'.'.$iv.'.'.$hmac.'.'.$output;
        return $output;

    }


    /**
     * @param $data : method . iv . hmac. data 형태로 제공되는 풀어야 하는 data 입니다.
     * @param $password : 비밀번호 입니다.
     * @param $format : 입력한 format이며 현재는 base64_uri만 지원됩니다.
     * @return int|string : 비정상시 -1, 정상인경우, String을 return 합니다.
     */
    public function decrypt($data, $password, $format){
        $password = hash('sha256', $password,true);

        $arr = explode('.',$data);
        $type = $arr[0];
        $iv = $arr[1];
        $hmac = $arr[2];
        $ciphertext = $arr[3];
        if($type==='T'){
            $type = 'AES-128-CTR';
        }elseif($type ==='B'){
            $type = 'AES-128-CBC';
        }else{
            return -1;
        }



        if($format ==='base64_uri'){
            $iv = base64_decode(str_replace(array('-', '_'), array('+', '/'), $iv));
            $hmac = base64_decode(str_replace(array('-', '_'), array('+', '/'), $hmac));
            $ciphertext = base64_decode(str_replace(array('-', '_'), array('+', '/'), $ciphertext));
        }

        if($hmac !==substr(hash_hmac('sha256', $ciphertext, $password, true),0,15)) {
            return -1;
        }
        $ciphertext = openssl_decrypt($ciphertext,$type,$password,OPENSSL_RAW_DATA,$iv);
        $ciphertext = gzuncompress($ciphertext);
        return $ciphertext;
    }

}