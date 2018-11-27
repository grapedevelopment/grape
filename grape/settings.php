<?php
/**
 * @description Loads settings from database
 */

// no direct call
if(!defined('GRAPE')) die('Direct access not permitted');

$grape->settings = grape_get_settings();
function grape_get_settings(){
	global $grape;
	$sql = "SELECT * FROM `grape_settings`";
	$grape->db->query($sql);
	$db_results = $grape->db->get_results();
	$results = new stdClass();
	foreach($db_results as &$db_result){
		//print_r($result);
		$value = json_decode($db_result->value);
		if(isset($value->value)) $value = $value->value;
		$results->{$db_result->name} = $value;
		//print_r(json_decode('{"x":"Y"}'));
	}
	// load module stettings
	$sql = "SELECT * FROM `grape_modules`";
	$grape->db->query($sql);
	$db_results = $grape->db->get_results();
	$results->modules = $db_results;
	// load auth settings
	$sql = "SELECT *
			FROM `grape_authentification`
			ORDER BY `auth_id`";
	$grape->db->query($sql);
	$db_results = $grape->db->get_results();
	$results->auth_methods = $db_results;
	return $results;
}
?>
