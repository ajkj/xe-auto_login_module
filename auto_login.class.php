<?php
require_once ('./modules/auto_login/vendor/autoload.php');
class auto_login extends ModuleObject {

    protected $config;
    protected $config_static;
    protected $module_self_info;
    protected $config_session;

    function __construct(){
        parent::__construct();
        $oModuleModel = getModel('module');
        $this->config = $oModuleModel->getModuleConfig('auto_login');

        $this->config_static = new stdClass();
        $this->config_static->cookie_ssl = (Context::getSslStatus() === 'always') ? true : false;
        $this->config_static->cookie_httponly = true;
        $this->config_static->cookie_expire = 1;


        $this->module_self_info = new stdClass();
        $this->module_self_info->module_name = 'auto_login';
        $this->module_self_info->module_version_code = 3;
        $this->module_self_info->module_version_name = '1.0.2';


        $this->config_session = new stdClass();
        // 자동로그인의 경우 개인이 혼자서 이용하는 장치 구분을 위해 isMobileCheckByAgent 를 이용합니다.
        if(Mobile::isMobileCheckByAgent()) {
            $this->config_session->auto_login_max_time = $this->config->auto_login_max_time_mobile;
            $this->config_session->auto_login_update_required_time  = $this->config->auto_login_update_required_time_mobile;
        }else {
            $this->config_session->auto_login_max_time = $this->config->auto_login_max_time_pc;
            $this->config_session->auto_login_update_required_time  = $this->config->auto_login_update_required_time_pc;
        }

    }


    private $triggers = array(
        array('name' =>'moduleHandler.init',
            'module'=>'auto_login',
            'type' => 'controller',
            'func' => 'triggerAutoLoginAlways',
            'position'=>'before'),
        array('name' =>'member.doLogin',
            'module'=>'auto_login',
            'type' => 'controller',
            'func' => 'triggerAfterDoLogin',
            'position'=>'after'),
        array('name' =>'member.doLogout',
            'module'=>'auto_login',
            'type' => 'controller',
            'func' => 'triggerAutoLoginLogout',
            'position'=>'after'),
    );

    function install()
    {
        $oModuleController = getController('module');
        foreach($this->triggers as $trigger)
        {
            $oModuleController->insertTrigger(
                $trigger['name'],
                $trigger['module'],
                $trigger['type'],
                $trigger['func'],
                $trigger['position']
            );
        }


        // add DB update
        $oDB = &DB::getInstance();

        if($oDB->isColumnExists("auto_login_info", "member_srl") === false){
            $oDB->addColumn("auto_login_info", "member_srl", "number", 11, -1, true);
        }

        if($oDB->isColumnExists("auto_login_info", "auto_login_token") === false){
            $oDB->addColumn("auto_login_info", "auto_login_token", "char", 64, "N", true);
        }

        if($oDB->isColumnExists("auto_login_info", "ip_address") == false){
            $oDB->addColumn("auto_login_info", "ip_address", "varchar", 39, '0.0.0.0', true);
        }


        if($oDB->isColumnExists("auto_login_info", "time_added") == false){
            $oDB->addColumn("auto_login_info", "time_added", "bignumber", 8, 0, true);
        }

        if($oDB->isColumnExists("auto_login_info", "time_max_valid_until") === false){
            $oDB->addColumn("auto_login_info", "time_max_valid_until", "bignumber", null, false);
        }

        if($oDB->isColumnExists("auto_login_info", "time_last_auto_login") === false){
            $oDB->addColumn("auto_login_info", "time_last_auto_login", "bignumber", 13, null, false);
        }

        if($oDB->isColumnExists("auto_login_info", "user_agent") === false){
            $oDB->addColumn("auto_login_info", "user_agent", "varchar", 300, null, false);
        }


        return new Object();
    }

    function moduleInstall()
    {

        // check for Trigger
        $oModuleController = getController('module');
        foreach($this->triggers as $trigger)
        {
            $oModuleController->insertTrigger(
                $trigger['name'],
                $trigger['module'],
                $trigger['type'],
                $trigger['func'],
                $trigger['position']
            );
        }

        // add DB update
        $oDB = &DB::getInstance();

        if($oDB->isColumnExists("auto_login_info", "member_srl") == false){
            $oDB->addColumn("auto_login_info", "member_srl", "number", 11, -1, true);
        }

        if($oDB->isColumnExists("auto_login_info", "auto_login_token") == false){
            $oDB->addColumn("auto_login_info", "auto_login_token", "char", 64, "N", true);
        }

        if($oDB->isColumnExists("auto_login_info", "ip_address") == false){
            $oDB->addColumn("auto_login_info", "ip_address", "varchar", 39, '0.0.0.0', true);
        }

        if($oDB->isColumnExists("auto_login_info", "time_added") == false){
            $oDB->addColumn("auto_login_info", "time_added", "bignumber", 8, 0, true);
        }
        if($oDB->isColumnExists("auto_login_info", "time_max_valid_until") == false){
            $oDB->addColumn("auto_login_info", "time_max_valid_until", "bignumber", 8, 0, true);
        }

        if($oDB->isColumnExists("auto_login_info", "time_last_auto_login") == false){
            $oDB->addColumn("auto_login_info", "time_last_auto_login", "bignumber", 8, 0, true);
        }

        if($oDB->isColumnExists("auto_login_info", "user_agent") ==false){
            $oDB->addColumn("auto_login_info", "user_agent", "varchar", 300, null, true);
        }

        $oModuleModel = getModel('module');
        $this->config = $oModuleModel->getModuleConfig('auto_login');

        if(!isset($this->config)){
            $this->config = new stdClass();
        }
        // set Config default
        if(empty($this->config->skin)){
            $this->config->skin = 'default';
        }

        if(empty($this->config->mskin)){
            $this->config->mskin = 'default';
        }

        // set Layout info
        if(empty($this->config->layout_srl)){
            $this->config->layout_srl = 'default';
        }

        if(empty($this->config->mlayout_srl)){
            $this->config->mlayout_srl = 'default';
        }

        if(empty($this->config->auto_login_module_enabled)){
            $this->config->auto_login_module_enabled = 'N';
        }

        if(empty($this->config->auto_login_mobile_prefer)){
            $this->config->auto_login_mobile_prefer = 'Y';
        }

        if(empty($this->config->auto_login_cookie_name)){
            $this->config->auto_login_cookie_name = '_atk';
        }


        if(empty($this->config->auto_login_max_time_pc)){
            $this->config->auto_login_max_time_pc = 2592000;
        }

        if(empty($this->config->auto_login_update_required_time_pc)){
            $this->config->auto_login_update_required_time_pc = 604800;
        }

        if(empty($this->config->auto_login_max_time_mobile)){
            $this->config->auto_login_max_time_mobile = 5184000;
        }

        if(empty($this->config->auto_login_update_required_time_mobile)){
            $this->config->auto_login_update_required_time_mobile = 1209600;
        }

        $oMemberModel = getModel('member');
        $group_list = $oMemberModel->getGroups();
        if(!isset($this->config->auto_login_limit_by_group_)){
            $this->config->auto_login_limit_by_group_ = array();
        }
        foreach($group_list as $key => $value){
            if(empty($this->config->auto_login_limit_by_group_[$key] )) {
                $this->config->auto_login_limit_by_group_[$key] = 5;
            }
        }

        if(empty($this->config->auto_login_limit_by_is_admin)){
            $this->config->auto_login_limit_by_is_admin = 1;
        }

        $oModuleController = getController('module');
        $oModuleController->insertModuleConfig('auto_login',$this->config);
        return new Object();
    }


    function checkUpdate()
    {

        $oModule = getModel('module');
        foreach($this->triggers as $trigger)
        {
            $result = $oModule->getTrigger(
                $trigger['name'],
                $trigger['module'],
                $trigger['type'],
                $trigger['func'],
                $trigger['position']
            );
            if(!$result)
            {
                return true;
            }
        }
        $oDB = &DB::getInstance();
        if($oDB->isColumnExists("auto_login_info", "member_srl") == false){
            return true;
        }

        if($oDB->isColumnExists("auto_login_info", "auto_login_token") == false){
            return true;
        }

        if($oDB->isColumnExists("auto_login_info", "ip_address") == false){
            return true;
        }

        if($oDB->isColumnExists("auto_login_info", "time_added") == false){
            return true;
        }
        if($oDB->isColumnExists("auto_login_info", "time_max_valid_until") == false){
            return true;
        }

        if($oDB->isColumnExists("auto_login_info", "time_last_auto_login") == false){
            return true;
        }

        if($oDB->isColumnExists("auto_login_info", "user_agent") ==false){
            return true;
        }


        // check for config
        if(empty($this->config->skin)){
            return true;
        }

        if(empty($this->config->mskin)){
            return true;
        }


        // set Layout info
        if(empty($this->config->layout_srl)){
            return true;
        }

        if(empty($this->config->mlayout_srl)){
            return true;
        }

        if(empty($this->config->layout_srl)){
            return true;
        }


        if(empty($this->config->auto_login_module_enabled)){
            return true;
        }
        if(empty($this->config->auto_login_mobile_prefer)){
            return true;
        }


        if(empty($this->config->auto_login_cookie_name)){
            return true;
        }

        if(empty($this->config->auto_login_max_time_pc)){
            return true;
        }


        if(empty($this->config->auto_login_update_required_time_pc)){
            return true;
        }

        if(empty($this->config->auto_login_max_time_mobile)){
            return true;
        }


        if(empty($this->config->auto_login_update_required_time_mobile)){
            return true;
        }

        $oMemberModel = getModel('member');
        $group_list = $oMemberModel->getGroups();
        foreach($group_list as $key => $value){
            if(empty($this->config->auto_login_limit_by_group_[$key] )) {
                return true;
            }
        }

        if(empty($this->config->auto_login_limit_by_is_admin)){
            return true;
        }


        return false;
    }

    function moduleUpdate()
    {
        $this->moduleInstall();
    }

    function recompileCache() {
    }



    /**
     * @info private BASE64 URI Encoding
     * @param $str : raw binary string
     * @return string : base64_encoded string
     */
    protected function base64_encode_uri($str){
        return str_replace(array('+','/','='),array('-','_',''),base64_encode($str));
    }
}