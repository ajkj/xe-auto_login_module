<?php

use UAParser\Parser as UAparser;
class auto_loginController extends auto_login {

    /**
     * @info trigger : 매번 접속시마다 xeak Cookie를 제거하고, 로그인상태가 아닐경우 AutoLogin을 진행합니다.
     * @return Object : AutoLogin을 진행하고 문제가 없을경우 new Object를 return 합니다.
     */
    public function triggerAutoLoginAlways(){

        if($this->config->auto_login_module_enabled !== 'Y'){
            return new Object();
        }

        if(isset($_COOKIE['xeak']) === false){
            setCookie('xeak',"null",1,'/');
        }
        $js = '<script>';
        $js .='document.cookie ="xeak=null; expires=Thu, 01-Jan-1970 00:00:01 GMT; Max-Age=-1427897740; path=/";';


        if(Mobile::isFromMobilePhone() === false)
        {
            if($this->config->auto_login_keep_signed_default_pc === 'Y')
            {
                $js .= 'jQuery(document).ready(function(){jQuery(\'input[name="keep_signed"]\').attr(\'checked\', true);});';
            }
            elseif($this->config->auto_login_keep_signed_default_pc =='S')
            {
                if($this->setKeepSigned() === true)
                {
                    $js .= 'jQuery(document).ready(function(){jQuery(\'input[name="keep_signed"]\').attr(\'checked\', true);});';
                }
            }
            elseif($this->config->auto_login_keep_signed_default_pc =='N')
            {
                ;
            }
        }
        else
        {
            if($this->config->auto_login_keep_signed_default_mobile === 'Y')
            {
                $js .= 'jQuery(document).ready(function(){jQuery(\'input[name="keep_signed"]\').attr(\'checked\', true);});';
            }
            elseif($this->config->auto_login_keep_signed_default_mobile =='S')
            {
                if($this->setKeepSigned() === true)
                {
                    $js .= 'jQuery(document).ready(function(){jQuery(\'input[name="keep_signed"]\').attr(\'checked\', true);});';
                }
            }
            elseif($this->config->auto_login_keep_signed_default_mobile =='N')
            {
                ;
            }
        }

        $js .= '</script>';
        Context::addHtmlHeader($js);



        if(Context::get('is_logged') === true){
            $oModule = getController('member');
            $oModule->addMemberMenu('dispAuto_loginAutoLoginManager', 'auto_login_menu_name');
            return new Object();
        }

        //if auto_login_cookie is set, do autoLogin;
        if(isset($_COOKIE[$this->config->auto_login_cookie_name])){
            return $this->doAutoLogin();
        }
        return new Object();

    }


    /**
     * @info trigger : 로그아웃시 AutoLogin Cookie 가 있다면 제거합니다.
     * @return Object : 비정상적인 경우를 제외하고 new Object() return;
     */
    public function triggerAutoLoginLogout()
    {
        return $this->removeAutoLoginToken();
    }

    /**@info trigger : 로그인시 AutoLogin을 진행합니다.
     * @param $logged_info : from member.controller.php after doLogin Trigger
     * @return Object : from member.controller.php after doLogin Trigger
     */
    public function triggerAfterDoLogin($logged_info)
    {
        if($this->config->auto_login_module_enabled !== 'Y'){
            return new Object();
        }



        $keep_signed = Context::get('keep_signed');

        if( Mobile::isFromMobilePhone() === false)
        {
          if($this->config->auto_login_keep_signed_default_pc ==='S'
              && isset($GLOBALS['AUTO_LOGIN_DOING']) === false
              && empty($keep_signed) === true){
              $this->updateSmartLoginCookie($logged_info);
          }
        }
        else
        {
            if($this->config->auto_login_keep_signed_default_mobile ==='S'
                && isset($GLOBALS['AUTO_LOGIN_DOING']) === false
                && empty($keep_signed) === true){
                $this->updateSmartLoginCookie($logged_info);
            }
        }

        return $this->makeAutoLogin($logged_info);
    }


    /**
     * @info private : autoLogin token을 생성 하고 DB추가, Cookie 생성.
     * @param $logged_info : from controller.php
     * @return Object : 에러를 제외하고 new Object return;
     *
     */
    private function makeAutoLogin($logged_info)
    {
        // 자동로그인이 아니면 나가기.
        $keep_signed = Context::get('keep_signed');
        if(empty($keep_signed) === true) return new Object();

        // logged_info가 비어있으면, 가져오기
        if(!isset($logged_info))
        {
            $logged_info = Context::get('logged_info');
            if(isset($logged_info) === false) return new Object();
        }


        if($this->config->auto_login_keep_signed_default_pc =='S' ||
            $this->config->auto_login_keep_signed_default_mobile =='S'){
            $this->deleteSmartLoginCookie();
        }


        // 자동로그이 허용 상태 조회
        $auto_login_config = $this->checkAutoLoginStatus($logged_info);
        if($auto_login_config->status !== 0)
        {
            $return_url_after_auto_login_manager = Context::get('return_url');
            if(empty($return_url_after_auto_login_manager)){
                 $return_url_after_auto_login_manager = getNotEncodedUrl('', '', '', '', '');
            }
            $_SESSION[$this->module_self_info->module_name]['status'] = $auto_login_config;
            $_SESSION[$this->module_self_info->module_name]['return_url'] = $return_url_after_auto_login_manager;
            $return_url_now = getNotEncodedUrl('', 'module', '', 'act', 'dispAuto_loginAutoLoginManager');

            Context::set('success_return_url',$return_url_now );

            return new Object();
        }



        // AutoLogin을 해도 되면 Token을 생성합니다.
        // 만약 중복일 경우를 대비하여 2번까지 시도합니다.
        $i = 0;
        while(true)
        {
            $token = $this->createSecureRandom();
            $ua_key = $this->parseUAForAutoLoginToken();
            $token_hmac = hash_hmac('sha256', $token ,$ua_key, false);

            $now = time();
            $args = new stdClass();
            $args->member_srl = $logged_info->member_srl;
            $args->auto_login_token = $token_hmac;
            $args->time_max_valid_until = $now + $this->config_session->auto_login_max_time;
            $args->time_last_auto_login = $now;
            $args->time_added = $now;
            $args->ip_address = $_SERVER['REMOTE_ADDR'];
            $args->user_agent = $_SERVER['HTTP_USER_AGENT'];

            $query_result = executeQuery('auto_login.insertAutoLoginToken', $args);
            if ($query_result->toBool() === true ) {
                break;
            }

            $i++;
            if ($i > 1){
                return new Object(-1, "AutoLogin Erorr Occured");
            }

        }

        setcookie($this->config->auto_login_cookie_name, $token, time()+$this->config_session->auto_login_max_time ,'/' , $this->getDomain(), $this->config_static->cookie_secure, $this->config_static->cookie_httponly);
        return new Object();
    }


    /**
     * @info private : 만료된 AutoLoginToken 제거 및 특정 AutoLoginToken 제거.
     * @return Object : 성공여부
     */
    private function removeAutoLoginToken()
    {
        $this->removeExpiredAutoLoginToken();
        $cookie = $_COOKIE[$this->config->auto_login_cookie_name];



        if($this->config->auto_login_keep_signed_default_pc =='S' ||
            $this->config->auto_login_keep_signed_default_mobile =='S'){
            $this->deleteSmartLoginCookie();
        }
        if(isset($cookie)){
            setcookie($this->config->auto_login_cookie_name,'null',1,'/', $this->getDomain());
            $token_hmac = hash_hmac('sha256',$cookie,$this->parseUAForAutoLoginToken(),false);

            $args = new stdClass();
            $args->auto_login_token = $token_hmac;
            $output = executeQuery('auto_login.deleteByAutoLoginToken',$args);
            if($output->toBool() === true)
            {
                return new Object();
            }else
            {
                return new Object(-1,'AutoLogin Remove Error');
            }
        }

        return new Object();
    }


    /**
     * @param $logged_info : Context::get('logged_info')
     * @return Stdclass : $return->status == 0 -> ok, else Error
     */
    private function checkAutoLoginStatus($logged_info)
    {
        $group_list =$logged_info->group_list;

        $max_device = PHP_INT_MAX;
        if($logged_info->is_admin==='Y')
        {
            $max_device = $this->config->auto_login_limit_by_is_admin;
        }
        else
        {
            foreach($group_list as $key => $val)
            {
                if($this->config->auto_login_limit_by_group_[$key] < $max_device )
                {
                    $max_device = $this->config->auto_login_limit_by_group_[$key];
                }
            }
        }

        // COUNT AND GET
        $args = new stdClass();
        $args->member_srl = $logged_info->member_srl;
        $query_result = executeQuery('auto_login.countAutoLoginTokenByMemberSrl',$args);



        $result = new Stdclass;
        if($query_result->data->count >= $max_device)
        {
            $result->status = 1;
            $result->max_auto_login = $max_device;
            $result->current_auto_login = $query_result->data->count;
        }
        else
        {
            $result->status = 0;
        }

        return $result;
    }


    /**
     * @info private : 기한이 만료된 AutoLoginToken을 제거합니다.
     * @return bool : 만료된 AutoLoginToken 제거 Query 성공여부
     */
    private function removeExpiredAutoLoginToken()
    {
        if(isset($this->removeExpiredAutoLoginToken_executed)) return true;
        $this->removeExpiredAutoLoginToken_executed = true;
        $now = time();
        $args = new stdClass();
        $args->time_max_valid_until = $now;
        $args->time_last_auto_login = $now - $this->config_session->auto_login_update_required_time ;
        $query_result = executeQuery('auto_login.deleteExpiredLoginToken', $args);
        return $query_result->toBool();
    }



    /**
     * @info private : AutoLogin 을 진행하는 Method 입니다.
     * @return Object : Erorr 가 있는 경우를 제외하고 빈 Object 생성.
     */
    private function doAutoLogin(){

        if(empty($_COOKIE[$this->config->auto_login_cookie_name]))
        {
            return new Object();
        }


        $this->removeExpiredAutoLoginToken();
        $this->deleteSmartLoginCookie();

        $token = $_COOKIE[$this->config->auto_login_cookie_name];
        $token_hmac = hash_hmac('sha256',$token,$this->parseUAForAutoLoginToken(), false);


        $args = new stdClass();
        $args->auto_login_token = $token_hmac;
        $query_result = executeQueryArray('auto_login.getInfoByAutoLoginToken', $args);

        if($query_result->toBool() !== true)
        {
            setcookie($this->config->auto_login_cookie_name,'null',1,'/', $this->getDomain());
            return new Object();
        }

        if(count($query_result->data)  < 1)
        {
            setcookie($this->config->auto_login_cookie_name,'null',1,'/', $this->getDomain());
            return new Object();
        }


        // remove XE Auto Login
        $args = new stdClass();
        $args->member_srl = $query_result->data[0]->member_srl;
        executeQuery('member.deleteAuto_login',$args);

        // get member information
        $oMemberModel = getModel('member');
        $member_info = $oMemberModel->getMemberInfoByMemberSrl($query_result->data[0]->member_srl);
        $oMemberConfig = $oMemberModel->getMemberConfig();

        $login_target = null;
        if($oMemberConfig->identifier === "user_id")
        {
            $login_target = $member_info->user_id;
        }
        elseif($oMemberConfig->identifier === "email_address")
        {
            $login_target = $member_info->email_address;
        }
        else
        {
            return new Object(-1, 'AutoLogin Error : Controller');
        }



        // check for Password Update
        $oModuleModel = getModel('module');
        $member_config = $oModuleModel->getModuleConfig('member');
        $limit_date = $member_config->change_password_date;

        if(isset($limit_date) && $limit_date > 0)
        {
            if($member_info->change_password_date >= date('YmdHis', strtotime('-'.$limit_date.' day')) ){
                return $this->removeAutoLoginToken();
            }
        }

        $oMemberController = getController('member');

        $GLOBALS['AUTO_LOGIN_DOING'] = true;
        $login_result = $oMemberController->doLogin($login_target,'', false);

        if($login_result->toBool() === true)
        {
            Context::set('is_logged',$oMemberModel->isLogged());
            Context::set('logged_info',$oMemberModel->getLoggedInfo());
            // update last login date
            $args = new stdClass();
            $args->time_last_auto_login = time();
            $args->auto_login_token = $token_hmac;
            $args->user_agent = $_SERVER['HTTP_USER_AGENT'];
            executeQuery('auto_login.updateTimeLastAutoLogin',$args);
            return new Object();
        }else{
            return $login_result;
        }
    }







    /**
     * @info public : 자동로그인 token(Cookie 용)을 생성합니다.
     * @return string (BASE64 URI encoded String, length 43)
     */
    public function createSecureRandom()
    {

        if(function_exists('openssl_random_pseudo_bytes') === true)
        {
            $entropy = openssl_random_pseudo_bytes(32);
            for($i=0; $i<32; $i++)
            {
                $entropy = hash('sha256',$entropy.$i, true);
            }
        }
        else{
            $entropy = microtime().session_id();
            for($i=0; $i<32; $i++)
            {
                $entropy = hash('sha256',(mt_rand() ^ rand()).$entropy.$i, true);
            }
        }

        return $this->base64_encode_uri($entropy);
    }



    public function procAuto_loginRemoveAutoLoginToken()
    {
        if(checkCSRF() !== true) return new Object(-1, "CSRF ERROR");

        $auto_login_mapping = Context::get('auto_login_mapping');
        if($auto_login_mapping === null ||
            !isset($_SESSION[$this->module_self_info->module_name]['map'][$auto_login_mapping]))
        {
            return new Object(-1, "Error : procAuto_loginRemoveAutoLoginToken Empty Session");
        }
        $auto_login_token = $_SESSION[$this->module_self_info->module_name]['map'][$auto_login_mapping];




        $args = new stdClass();
        $args->auto_login_token = $auto_login_token;
        $query_result = executeQuery('auto_login.deleteByAutoLoginToken',$args);

        if($query_result->toBool() === false)
        {
            return new Object(-1, 'AutoLoginToken Remove Error');
        }

        if(isset($_SESSION[$this->module_self_info->module_name]['status']))
        {
            $_SESSION[$this->module_self_info->module_name]['status'] =
                $this->checkAutoLoginStatus($_SESSION[$this->module_self_info->module_name]['status']);
        }

        $returnUrl = getNotEncodedUrl('', 'module', '', 'act', 'dispAuto_loginAutoLoginManager');
        $this->setRedirectUrl($returnUrl);

        return new Object();
    }


    /**@info private :  자동로그인 Token 인증에 사용할 User-agent를 파싱합니다.
     * 브라우저 업데이트가 되어도 자동로그인 처리를 위해서 OS명, 브라우저명, Major 버젼, 장치명만 출력합니다.
     * @param null $ua
     * @return string
     */
    private function parseUAForAutoLoginToken($ua=null)
    {
        if(empty($ua))
        {
            $ua = $_SERVER['HTTP_USER_AGENT'];
        }

        $UAparser = UAparser::create();
        $parsed = $UAparser->parse($ua);


        $return = '';
        $return .= $parsed->ua->family;
        $return .= $parsed->ua->major;
        $return .= $parsed->os->toString();
        $return .= $parsed->device->toString();
        return $return;
    }










    // 14일내에 2번 이상 로그인시시 (단 1번 로그인 이후 12시간 경과해야 함)


    // add smart login always
   // return true or false;
    private function setKeepSigned()
    {
        if( Context::get('is_logged') === true) return false;

        if(file_exists(_XE_PATH_.'modules/auto_login/auto_login.user_config.php')){
            require_once(_XE_PATH_.'modules/auto_login/auto_login.user_config.php');
            $auto_loginUser_config = new auto_loginUser_config();
            return $auto_loginUser_config->setKeepSigned();
        }
        else
        {
            if($_COOKIE[$this->config->auto_login_cookie_name.'_smt'] === 'Y'){
                return true;
            }
        }
    }

    // postion at after login success
    // return nothing
    private function updateSmartLoginCookie($logged_info)
    {
        if( $logged_info == null) return;

        $now = time();

        if(file_exists(_XE_PATH_.'modules/auto_login/auto_login.user_config.php')){
            require_once(_XE_PATH_.'modules/auto_login/auto_login.user_config.php');
            $auto_loginUser_config = new auto_loginUser_config();
            $auto_loginUser_config->updateSmartLoginCookie();
            return;
        }


        // 스마트 로그인 쿠키가 없다면, 쿠키를 생성한다.
        if(isset($_COOKIE[$this->config->auto_login_cookie_name.'_smt']) === false)
        {
            $this->resetSmartLoginCookie($logged_info);
            return;
        }
        else
        {
            // 우선적으로 암호화한 쿠키를 풀어준다.
            $oAES = new AesPhpSimple($this->config->auto_login_cookie_encryption_cipher_mode);
            $auto_login_cookie = $oAES->decrypt($_COOKIE[$this->config->auto_login_cookie_name.'_smt'], $this->config->auto_login_cookie_encryption_password, 'base64_uri');

            if($auto_login_cookie == -1 || $auto_login_cookie == null){
                $this->resetSmartLoginCookie($logged_info);
                return;
            }


            // 그리고 형식을 분리한다.
            $arr = explode('@',$auto_login_cookie);

            // 구조가 비정상적이면 리셋.
            if($arr === false){
                $this->resetSmartLoginCookie($logged_info);
                return;
            }

            // member_srl이 다르다면 쿠키를 리셋
            elseif($arr[0] !== $logged_info->member_srl)
            {
                $this->resetSmartLoginCookie($logged_info);
                return;
            }

            // 2번째 로그인 시간이 존재한하고, 2번째 시간이 현재시간보다 12시간 이전이라면, 자동로그인을 특정 시간동안 On해줍니다.
            elseif( isset($arr[2])
                && isset($arr[1])
                && $arr[2]+43200 < $now
                && $arr[1] > $now -$this->config->auto_login_smart_time
                && $arr[2] > ($arr[1] + 43200))
            {
                setcookie($this->config->auto_login_cookie_name.'_smt', 'Y', $now+$this->config->auto_login_smart_time,'/', $this->getDomain(), $this->config_static->cookie_secure, $this->config_static->cookie_httponly);
                return;
            }
            // 2번째 시간이 없이 1번째 시간만 존재하고, 1번째 접속 시간이 현재시간보다 12시간 이전이라면 2번째 쿠키를 추가해 줍니다.
            elseif(isset($arr[1]) && $arr[1]+43200 < $now && $arr[1] && $arr[1] > $now -$this->config->auto_login_smart_time )
            {
                $auto_login_cookie = $oAES->encrypt($auto_login_cookie.'@'.$now, $this->config->auto_login_cookie_encryption_password, 'base64_uri');
                setcookie($this->config->auto_login_cookie_name.'_smt', $auto_login_cookie , $now+$this->config->auto_login_smart_time,'/', $this->getDomain(), $this->config_static->cookie_secure, $this->config_static->cookie_httponly);
            }
        }


    }
    private function resetSmartLoginCookie($logged_info){
        $auto_login_cookie_string = $logged_info->member_srl .'@'. time();
        $oAES = new AesPhpSimple($this->config->auto_login_cookie_encryption_cipher_mode);
        $result = $oAES->encrypt($auto_login_cookie_string,$this->config->auto_login_cookie_encryption_password, 'base64_uri');
        setcookie($this->config->auto_login_cookie_name.'_smt', $result, time()+1209600,'/', $this->getDomain(), $this->config_static->cookie_secure, $this->config_static->cookie_httponly);
        return;
    }

    public function deleteSmartLoginCookie(){
        if(file_exists(_XE_PATH_.'modules/auto_login/auto_login.user_config.php')){
            require_once(_XE_PATH_.'modules/auto_login/auto_login.user_config.php');
            $auto_loginUser_config = new auto_loginUser_config();
            $auto_loginUser_config->deleteSmartLoginCookie();
            return;
        }
        else{
            setcookie($this->config->auto_login_cookie_name.'_smt', 'null', 1,'/', $this->getDomain(), $this->config_static->cookie_secure, $this->config_static->cookie_httponly);
        }
    }

	/** @return string */
	private function getDomain() {
		return substr($_SERVER['HTTP_HOST'],0,strpos($_SERVER['HTTP_HOST'], ':'));
	}
}