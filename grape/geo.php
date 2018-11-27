<?php
/**
 * @todo probably deprecated
 */

// no direct call
if(!defined('GRAPE')) die('Direct access not permitted');

/**
 * @param int $district_id ID of district
 * @return object Result of query containing voting districts
 * @version changedAWBEZ_T
 */
function grape_get_voting_districts_by_wahlkreis($wahlkreis_id){
	global $grape;
	$wahlkreis_id = floatval($wahlkreis_id);
	$sql = "SELECT `wahlbezirk_id` as id,
					CONCAT(`AWBEZ_T`,': ',`name`,' (Potential: ',`potential`,')') as value
			FROM `grape_electoral_wards`
			WHERE `wahlkreis_id` = '$wahlkreis_id'
			AND `visible` = 1
			ORDER BY `AWBEZ_T`";
	//echo $sql;
	$grape->db->query($sql);
	return $grape->db->get_results();
}
/**
 *
 */
function grape_get_x_ward_by_x_street($x_street_id){
	global $grape;
	$x_street_id = intval($x_street_id);
	$sql = "SELECT `x_ward_id` FROM `grape_x_streets` WHERE `x_street_id` = $x_street_id";
	$grape->db->query($sql);
	$results = $grape->db->get_results();
	return $results[0]->x_ward_id;
}

?>
