<?php

class auto_loginAdminController extends auto_login {


    public function init(){

    }

    /**
     * @info :  관리자 페이지에서 설정 변경을 처리합니다. 설정 default 처리는 auto_login.class.php 에서 처리합니다.
     * @return Object
     */
    public function procAuto_loginAdminConfigChange(){

        if(checkCSRF() !== true){
            return new Object(-1,'AutoLogin Error 403');
        }
        $oModuleModel = getModel('module');
        $config = $oModuleModel->getModuleConfig('auto_login');
        $args = Context::getRequestVars();

        if(!empty($args->skin)){
            $config->skin = $args->skin;
        }
        if(!empty($args->mskin)){
            $config->mskin = $args->mskin;
        }

        // set Layout info
        if(!empty($args->layout_srl)){
            $config->layout_srl = $args->layout_srl;
        }
        if(!empty($args->mlayout_srl)){
            $config->mlayout_srl = $args->mlayout_srl;
        }
        if(!empty($args->auto_login_module_enabled)){
            $config->auto_login_module_enabled = $args->auto_login_module_enabled;
        }

        if(!empty($args->auto_login_mobile_prefer)){
            $config->auto_login_mobile_prefer = $args->auto_login_mobile_prefer;
        }


        if(!empty($args->auto_login_cookie_name)){
            $config->auto_login_cookie_name = $args->auto_login_cookie_name;
        }


        //auto_login_mobile_prefer
        // set Layout info
        if(!empty($args->auto_login_mobile_prefer)){
            $config->layout_srl = $args->auto_login_mobile_prefer;
        }


        if(!empty($args->auto_login_max_time_pc)){
            $config->auto_login_max_time_pc = (int)$args->auto_login_max_time_pc;
        }

        if(!empty($args->auto_login_update_required_time_pc)){
            $config->auto_login_update_required_time_pc = (int)$args->auto_login_update_required_time_pc;
        }

        if(!empty($args->auto_login_max_time_mobile)){
            $config->auto_login_max_time_mobile = (int)$args->auto_login_max_time_mobile;
        }

        if(!empty($args->auto_login_update_required_time_mobile)){
            $config->auto_login_update_required_time_mobile = (int)$args->auto_login_update_required_time_mobile;
        }

        $oMemberModel = getModel('member');
        $group_list = $oMemberModel->getGroups();
        foreach($group_list as $key => $value){

            if(isset($args->{'auto_login_limit_by_group_'.$key} )){
                $config->auto_login_limit_by_group_[$key] = (int)$args->{'auto_login_limit_by_group_'.$key};
            }
        }

        if(!empty($args->auto_login_limit_by_is_admin)){
            $config->auto_login_limit_by_is_admin = (int)$args->auto_login_limit_by_is_admin;
        }

        $oModuleController = getController('module');
        $oModuleController->insertModuleConfig('auto_login',$config);

        $returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispAuto_loginAdminConfig');
        $this->setRedirectUrl($returnUrl);

    }
}