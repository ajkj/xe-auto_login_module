<?php

if(!defined('__XE__')) exit();

if($called_position ==='before_display_content'
	|| $called_position ==='before_module_proc' 
	|| $called_position ==='after_module_proc'
	|| $called_position ==='before_display_content') {


	$auto_login_debugger_log_path = _XE_PATH_.'files/config/auto_login_debug.php';

	if(Context::get('is_logged') !== true) return;


	if(isset($GLOBALS['auto_login_debugger']) === false)
	{
		$GLOBALS['auto_login_debugger'] = new stdClass();
		$GLOBALS['auto_login_debugger']->group_list = Context::get('logged_info')->group_list;
		return;
	}
	else
	{
		if($GLOBALS['auto_login_debugger']->group_list != Context::get('logged_info')->group_list){

			if(file_exists($auto_login_debugger_log_path) === false){
				file_put_contents($auto_login_debugger_log_path, '<?php exit(); /*');
			}
			
			$auto_login_debug_log = new stdClass();
			$auto_login_debug_log->called_position = $called_position;
			$auto_login_debug_log->act = Context::get('act');
			$auto_login_debug_log->REQUEST_TIME_FLOAT = $_SERVER['REQUEST_TIME_FLOAT'];
			$auto_login_debug_log->REQUEST_URI = $_SERVER['REQUEST_URI'];
			$auto_login_debug_log->REQUEST_METHOD = $_SERVER['REQUEST_METHOD'];
			$auto_login_debug_log->request_var_get = $_GET;
			$auto_login_debug_log->request_var_post = $_POST;

			$auto_login_debug_log_json = json_encode($auto_login_debug_log,JSON_UNESCAPED_UNICODE);
			$auto_login_debug_fp = fopen($auto_login_debugger_log_path, 'a');
			fwrite($auto_login_debug_fp, "\n".$auto_login_debug_log_json);
			fclose($auto_login_debug_fp);
		}

	}
}