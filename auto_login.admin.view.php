<?php

class auto_loginAdminView extends auto_login {


    /**
     * @info init :  초기화
     */
    public function init(){
        $oModuleModel = getModel('module');
        $config = $oModuleModel->getModuleConfig('auto_login');
        Context::set('config', $config);
        $this->setTemplatePath($this->module_path.'tpl');
    }


    /**
     * @info public : 관리자 페이지에서 설정을 보여줍니다.
     */
    public function dispAuto_loginAdminConfig(){

        // set Groups
        $oMemberModel = getModel('member');
        $group_list = $oMemberModel->getGroups();
        Context::set('group_list', $group_list);


        // set LayoutInfo
        $oLayoutModel = getModel('layout');
        $layout_list = $oLayoutModel->getLayoutList();
        $mlayout_list = $oLayoutModel->getLayoutList(0, 'M');
        Context::set('layout_list', $layout_list);
        Context::set('mlayout_list', $mlayout_list);



        // setSkin
        $oModuleModel = getModel('module');
        $skin_list = $oModuleModel->getSkins($this->module_path);
        Context::set('skin_list', $skin_list);

        // list of skins for member module
        $mskin_list = $oModuleModel->getSkins($this->module_path, 'm.skins');
        Context::set('mskin_list', $mskin_list);


        $this->setTemplateFile('admin_config');

    }
}