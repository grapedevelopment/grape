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
/**
 * Build a HTML select for modules
 * @param array $elections
 * @param int $selected_id
 * @return string HTML optgroups and options
 */
function grape_module_select($collection,$selected_id){
	global $grape;
	$html = '';
	
	foreach($collection as $item){
		if($item->active==1 && $item->code !== "admin")
			$html.= '<option value="'.$item->module_id.'"'.(($item->module_id==$selected_id)?' selected="selected"':'').'>'.$item->name.'</option>';
	}
	return $html;
}
/**
 *
 */

?>