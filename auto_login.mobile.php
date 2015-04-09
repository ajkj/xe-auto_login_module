<?php

require_once(_XE_PATH_.'modules/auto_login/auto_login.view.php');

class auto_loginMobile extends auto_loginView
{
    /**
     * @info init :  초기화
     */
    function init()
    {
        $oLayoutModel = getModel('layout');
        $layout_info = $oLayoutModel->getLayout($this->config->mlayout_srl);
        if (!isset($layout_info)) {
            $this->setLayoutPath($layout_info->path);
        }
        $this->setLayoutPath($layout_info->path);

        $template_path = sprintf('%sm.skins/%s', $this->module_path, $this->config->skin);
        $this->setTemplatePath($template_path);
    }
}