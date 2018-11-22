<?php
/**
 *
 */

// no direct call
if(!defined('GRAPE')) die('Direct access not permitted');

foreach($grape->settings->modules as $module){
	if($module->active == 1){
		include_once(ABSPATH."grape-modules/".$module->code."/".$module->code.".php");
	}
}
/**
 *
 */
function grape_is_module_active($code){
	global $grape;
	foreach($grape->settings->modules as $module){
		if($module->active == 1 && $module->code == $code){
			return true;
		}
	}
	return false;
}
/**
 *
 */
function grape_get_modules_menu(){
	global $grape;
	$menu = array();
	foreach($grape->settings->modules as $module){
		if($module->active == 1){
			array_push($menu,array("url"=>URL."?module=".$module->code,"name"=>$module->name));
		}
	}
	return $menu;
}
/**
 *
 */
function grape_get_module_by_code($code){
	global $grape;
	foreach($grape->settings->modules as $module){
		if($module->code == $code){
			return $module;
		}
	}
	return false;
}

?>