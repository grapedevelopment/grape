<?php
/**
 *
 */

// no direct call
if(!defined('GRAPE')) die('Direct access not permitted');

/**
 * 
 */
function grape_get_elections($id=false){
	global $grape;
	$sql = "SELECT
					`grape_elections`.`election_id` AS id,
					`grape_elections`.`name` AS name,
					`grape_election_types`.`name` AS type,
					`grape_elections`.`date`,
					`grape_election_types`.`election_type_id`
			FROM `grape_elections`
			LEFT JOIN `grape_election_types` ON `grape_election_types`.`election_type_id` = `grape_elections`.`election_type_id`
			".($id!==false?"WHERE `grape_elections`.`election_id` = ".intval($id):"")."
			ORDER BY `grape_elections`.`date` DESC";
	$grape->db->query($sql);
	//$grape->output->content->html.= $grape->output->dump_var($sql);
	return $grape->db->get_results();
}
/**
 * 
 */
function grape_get_election_types($id=false){
	global $grape;
	$sql = "SELECT
					`grape_election_types`.`election_type_id` AS id,
					`grape_election_types`.`name` AS name
			FROM `grape_election_types`
			".($id!==false?"WHERE `grape_election_types`.`grape_election_type_id` = ".intval($id):"")."
			ORDER BY `grape_election_types`.`name`";
	$grape->db->query($sql);
	return $grape->db->get_results();
}
?>