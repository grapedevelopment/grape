<?php
/**
 * @description Loads active theme
 */

// no direct call
if(!defined('GRAPE')) die('Direct access not permitted');

foreach($grape->settings->themes as $theme){
	if($theme->active == 1){
		include_once(ABSPATH."grape-themes/".$theme->code."/index.php");
		$grape->output = new grape_theme();
		break;
	}
}

?>
