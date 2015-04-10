<?php

use UAParser\Parser as UAparser;

class auto_loginView extends auto_login
{

    function init()
    {
        $oLayoutModel = getModel('layout');
        $layout_info = $oLayoutModel->getLayout($this->config->layout_srl);
        if (isset($layout_info)) {
            $this->module_info->layout_srl = $this->config->layout_srl;
            $this->setLayoutPath($layout_info->path);
        }

        $template_path = sprintf('%sskins/%s', $this->module_path, $this->config->skin);
        $this->setTemplatePath($template_path);
    }



    public function dispAuto_loginAutoLoginManager(){

        if(Context::get('is_logged') !== true)
        {
            return new Object(-1, 'Login Required');
        }

        if(isset($_SESSION[$this->module_self_info->module_name]['status']))
        {
            if($_SESSION[$this->module_self_info->module_name]['status']->status === 0 )
            {
                Context::set('keep_signed','Y');
                $oController = getController('auto_login');
                $auto_login_result = $oController->triggerAfterDoLogin(Context::get('logged_info'));

                if($auto_login_result->toBool() === true)
                {
                    $this->setRedirectUrl($_SESSION[$this->module_self_info->module_name]['return_url']);
                    unset($_SESSION[$this->module_self_info->module_name]['status']);
                    unset($_SESSION[$this->module_self_info->module_name]['return_url']);
                    return new Object();
                }
                else
                {
                    return $auto_login_result;
                }
            }
            $max_auto_login = $_SESSION[$this->module_self_info->module_name]['status']->max_auto_login;
            $current_auto_login = $_SESSION[$this->module_self_info->module_name]['status']->current_auto_login;

            Context::set('max_auto_login',$max_auto_login);
            Context::set('need_to_remove_login',($current_auto_login-$max_auto_login+1));
            Context::set('no_auto_login_return_url',$_SESSION[$this->module_self_info->module_name]['return_url']);
        }
        else
        {
            Context::addJsFile('./modules/auto_login/tpl/js/main.js');
        }



        $logged_info = Context::get('logged_info');

        $args = new stdClass();
        $args->member_srl = $logged_info->member_srl;
        $query_result = executeQueryArray('auto_login.getAutoLoginTokenByMemberSrl', $args);



        if($query_result->toBool() !== true)
        {
            return new Object(-1, 'AutoLogin Module : Error Code 842');
        }

        if(count($query_result->data) === 0){
            Context::set('auto_login_no_record',true);
        }

        $auto_login_info = array();


        if(!isset($_SESSION[$this->module_self_info->module_name]['map']))
        {
            $_SESSION[$this->module_self_info->module_name]['map'] = array();
        }

        if(!isset($_SESSION[$this->module_self_info->module_name]['SESSION_SECRET']))
        {
            $oController = getController('auto_login');
            $_SESSION[$this->module_self_info->module_name]['SESSION_SECRET'] = $oController->createSecureRandom();
        }


        $i=0;
        foreach($query_result->data as $key => $val){
            $i++;
            $new = new stdClass();
            $new->count = $i;
            $new->time_added = $this->unixTimeToDate($val->time_added);
            $new->time_last_auto_login = $this->unixTimeToDate($val->time_last_auto_login);
            $new->device_info = $this->userAgnetParser($val->user_agent);
            $new->user_agent = $val->user_agent;
            $new->ip_address = $val->ip_address;

            $mapped_key = $this->base64_encode_uri(hash_hmac('sha256',$val->auto_login_token,$_SESSION[$this->module_self_info->module_name]['SESSION_SECRET'],true));
            $_SESSION[$this->module_self_info->module_name]['map'][$mapped_key] = $val->auto_login_token;

            $new->auto_login_token_mapping = $mapped_key;

            array_push($auto_login_info,$new);
        }


        Context::set('auto_login_info', $auto_login_info);

        $this->setTemplateFile('auto_login_manager');

        return new Object();
    }

    protected function unixTimeToDate($time =0)
    {
        $timezone = $GLOBALS['_time_zone'];
        $timezone = $timezone/100;

        $otimezone = new DateTimeZone('UTC');
        $date = new DateTime();
        $date->setTimestamp($time+$timezone*3600);
        $date->setTimezone($otimezone);


        return $date->format('Y-m-d H:i:s' );
    }

    protected function userAgnetParser($ua)
    {
        if($ua==null){
            $ua = $_SERVER['HTTP_USER_AGENT'];
        }

        $UAParser = UAParser::create();
        $parsed = $UAParser->parse($ua);



        $count=0;
        // if more than 2 other, show raw UA
        $count += ($parsed->ua->toString() === 'Other') ? 1 : 0;
        $count += ($parsed->os->toString() === 'Other') ? 1 : 0;
        $count += ($parsed->device->toString() === 'Other') ? 1 : 0;

        if($count >1){
            return 'Not Detected';
        }


        $device = '';
        // for android default browser
        if($parsed->ua->toString() === $parsed->os->toString() ){
            $device .= $parsed->os->toString();
        }else{
            $device .= ' '.$parsed->os->toString();
            $device .= ' '. ($parsed->ua->family);
            $device .= ' '. ($parsed->ua->major);
        }
        $device .= ' '.($parsed->device->toString() !== 'Other' ? $parsed->device->toString() : '');
        return $device;
    }


}