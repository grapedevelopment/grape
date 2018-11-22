<?php
/**
 * manages terms and imprint
 */

// no direct call
if(!defined('GRAPE')) die('Direct access not permitted');

/**
 *
 */
function grape_accept_current_terms(){
	global $grape;
	$text_id = grape_get_current_terms()->text_id;
	grape_accept_terms($text_id);
}
/**
 *
 */
function grape_accept_terms($text_id){
	global $grape;
	$user_id = $grape->user->user_id;
	$sql = "INSERT INTO `grape_x_texts_users` (`user_id`,`text_id`) VALUES($user_id,$text_id);";
	$grape->db->query($sql);
}
/**
 *
 */
function grape_current_terms_accepted(){
	global $grape;
	$user_id = $grape->user->user_id;
	$current_text_id = grape_get_current_terms()->text_id;
	$sql = "SELECT * FROM `grape_x_texts_users` WHERE `user_id` = $user_id AND `text_id` = $current_text_id";
	$grape->db->query($sql);
	if($grape->db->num_rows > 0){
		return true;
	}
	else{
		return false;
	}
}
/**
 *
 */
function grape_get_current_terms(){
	return grape_get_current_legal_text("term");
}
/**
 *
 */
function grape_get_current_imprint(){
	return grape_get_current_legal_text("imprint");
}
/**
 *
 */
function grape_get_current_privacy_policy(){
	return grape_get_current_legal_text("privacy_policy");
}
/**
 *
 */
function grape_get_current_legal_text($type){
	global $grape;
	$result = array();
	if(in_array($type,array("term","imprint","privacy_policy"))){
		$sql = "SELECT * FROM `grape_legal_texts` WHERE `type` = '$type' ORDER BY `published` DESC LIMIT 1";
		$grape->db->query($sql);
		if($grape->db->num_rows > 0){
			$db_results = $grape->db->get_results();
			$result = $db_results[0];
		}
	}
	return $result;
}

?>